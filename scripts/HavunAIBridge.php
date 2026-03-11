<?php
declare(strict_types=1);

/**
 * HavunAIBridge – Vraag → vector-KB (PDO + cosine similarity) → Ollama
 */

// De rest van je script (autoload, bootstrap, etc.) komt hieronder...

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$baseDir = dirname(__DIR__);
if (!is_dir($baseDir . '/vendor')) {
    die("[FATAL] Geen vendor in {$baseDir}. Run: composer install\n");
}

require $baseDir . '/vendor/autoload.php';
$app = require_once $baseDir . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// --- Config ---
define('OLLAMA_URL', env('OLLAMA_URL', 'http://127.0.0.1:11434'));
define('OLLAMA_MODEL', env('OLLAMA_MODEL', 'command-r'));
define('HAVUN_AI_DB_PATH', $baseDir . '/database/doc_intelligence.sqlite');
const CONTEXT_LIMIT_CHARS = 12000;
const CONTEXT_PER_DOC_CHARS = 3500;
const SEARCH_LIMIT = 8;

// --- User question ---
$question = $argv[1] ?? null;
if ($question === null || $question === '') {
    $question = stream_get_contents(STDIN);
}
$question = trim((string) $question);
if ($question === '') {
    echo "Gebruik: php scripts/HavunAIBridge.php \"Jouw vraag\"\n";
    exit(1);
}

try {
    $bridge = new HavunAIBridge();
    $bridge->run($question);
} catch (Throwable $e) {
    (new HavunAIBridge())->failDeep($e, $question, 'run');
    exit(1);
}

final class HavunAIBridge
{
    private string $baseDir;
    private \PDO $pdo;

    /** Stopwords (zelfde als DocIndexer voor compatibele embeddings) */
    private const STOPWORDS = [
        'the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
        'may', 'might', 'must', 'shall', 'can', 'need', 'dare', 'ought', 'used',
        'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into',
        'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under',
        'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how',
        'all', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not',
        'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'and', 'but', 'if', 'or',
        'because', 'until', 'while', 'this', 'that', 'these', 'those', 'it',
    ];

    public function __construct()
    {
        $this->baseDir = dirname(__DIR__);
        $this->pdo = $this->connectDb();
    }

    /**
     * Direct PDO naar de embeddings-database (zelfde als /kb en docs:search)
     */
    private function connectDb(): \PDO
    {
        $path = defined('HAVUN_AI_DB_PATH') ? HAVUN_AI_DB_PATH : ($this->baseDir . '/database/doc_intelligence.sqlite');
        if (!is_file($path)) {
            throw new \RuntimeException(
                "Database niet gevonden: {$path}. Run: php artisan migrate --database=doc_intelligence && php artisan docs:index all --force"
            );
        }
        $pdo = new \PDO('sqlite:' . $path, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        return $pdo;
    }

    public function run(string $question): void
    {
        echo "=== HavunAIBridge ===\n";
        echo "[1/3] Zoeken in Havun-kennisbank (SQLite via PDO)...\n";

        $context = $this->retrieveContext($question);
        $contextSize = strlen($context);

        echo "[2/3] Context verzameld ($contextSize tekens)...\n";

        $systemPrompt = $this->getSystemPrompt();
        $userPrompt = $this->buildUserPrompt($question, $context);

        echo "[3/3] Command-R aanroepen (laden kan 30-60 sec duren)...\n";

        $response = $this->callOllama($systemPrompt, $userPrompt);

        echo "\n--- Antwoord (Ollama) ---\n";
        echo $response . "\n";
    }

    /**
     * Context retrieval: vraag → embedding → PDO alle rijen → cosine similarity → top N
     */
    private function retrieveContext(string $question): string
    {
        $queryEmbedding = $this->generateEmbedding($question);

        $stmt = $this->pdo->query(
            "SELECT id, project, file_path, content, embedding, file_modified_at FROM doc_embeddings"
        );
        if ($stmt === false) {
            $this->failDeep(
                new \RuntimeException('PDO query doc_embeddings mislukt'),
                $question,
                'retrieveContext',
                ['path' => defined('HAVUN_AI_DB_PATH') ? HAVUN_AI_DB_PATH : $this->baseDir . '/database/doc_intelligence.sqlite']
            );
            throw new \RuntimeException('Query failed');
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return "[Geen documenten in de kennisbank. Run: php artisan docs:index all --force. Antwoord dat de KB leeg is.]";
        }

        $scored = [];
        foreach ($rows as $row) {
            $stored = is_string($row['embedding'] ?? '') ? json_decode($row['embedding'], true) : $row['embedding'];
            $stored = is_array($stored) ? $stored : [];
            $similarity = $this->cosineSimilarity($queryEmbedding, $stored);
            $scored[] = [
                'project' => $row['project'],
                'file_path' => $row['file_path'],
                'content' => $row['content'] ?? '',
                'similarity' => $similarity,
                'file_modified_at' => $row['file_modified_at'] ?? null,
            ];
        }

        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        $top = array_slice($scored, 0, SEARCH_LIMIT);

        $parts = [];
        $totalLen = 0;
        foreach ($top as $r) {
            if ($totalLen >= CONTEXT_LIMIT_CHARS) {
                break;
            }
            $content = $r['content'];
            if (strlen($content) > CONTEXT_PER_DOC_CHARS) {
                $content = substr($content, 0, CONTEXT_PER_DOC_CHARS) . "\n[... afgekapt ...]";
            }
            $block = sprintf(
                "--- [%s] %s (relevantie: %.0f%%) ---\n%s",
                $r['project'],
                $r['file_path'],
                $r['similarity'] * 100,
                $content
            );
            if ($totalLen + strlen($block) > CONTEXT_LIMIT_CHARS) {
                $block = substr($block, 0, CONTEXT_LIMIT_CHARS - $totalLen - 20) . "\n[...]";
            }
            $parts[] = $block;
            $totalLen += strlen($block);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Word-frequency embedding (identiek aan DocIndexer voor dezelfde cosine-uitkomst)
     */
    private function generateEmbedding(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCounts = array_count_values($words);
        foreach (self::STOPWORDS as $w) {
            unset($wordCounts[$w]);
        }
        arsort($wordCounts);
        $topWords = array_slice($wordCounts, 0, 100, true);
        $total = array_sum($topWords) ?: 1;
        $embedding = [];
        foreach ($topWords as $word => $count) {
            $embedding[$word] = $count / $total;
        }
        return $embedding;
    }

    /**
     * Cosine similarity tussen twee vectoren (woord → gewicht)
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }
        $allKeys = array_unique(array_merge(array_keys($a), array_keys($b)));
        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;
        foreach ($allKeys as $key) {
            $v1 = $a[$key] ?? 0;
            $v2 = $b[$key] ?? 0;
            $dotProduct += $v1 * $v2;
            $norm1 += $v1 * $v1;
            $norm2 += $v2 * $v2;
        }
        if ($norm1 == 0 || $norm2 == 0) {
            return 0.0;
        }
        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * System prompt: niet-passieve instructies (naar Ollama "system")
     */
    private function getSystemPrompt(): string
    {
        return <<<TEXT
Je bent een assistent die uitsluitend antwoordt op basis van de gegeven KB-context van Havun (documentatie, runbooks, patterns).
Je moet actief en concreet antwoorden: geen passieve zinnen zoals "In de documentatie staat dat...". Geef korte, bruikbare antwoorden.
Als het antwoord niet in de context staat, zeg dat expliciet en verzin geen informatie.
TEXT;
    }

    private function buildUserPrompt(string $question, string $context): string
    {
        return <<<TEXT
## KB Context

De volgende fragmenten komen uit de Havun kennisbank (vector search, cosine similarity op de vraag):

{$context}

## User Question

{$question}
TEXT;
    }

    /**
     * Ollama: POST naar 11434 met "system" (niet-passief) en "prompt" (context + vraag)
     */
    private function callOllama(string $systemPrompt, string $userPrompt): string
    {
        $url = OLLAMA_URL . '/api/generate';
        $body = [
            'model'  => OLLAMA_MODEL,
            'system' => $systemPrompt,
            'prompt' => $userPrompt,
            'stream' => false,
            'options' => [
                'num_ctx' => 24576, // Verhoog naar 24k voor jouw 15.5k context + antwoordruimte
                'temperature' => 0.2,
            ],
        ];

        $responseBody = null;
        $httpCode = null;

        try {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new \RuntimeException('curl_init failed');
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 600,          // Verhoog van 120 naar 600 (10 minuten)
                CURLOPT_CONNECTTIMEOUT => 10,     // Iets ruimer voor de initiële handshake
            ]);
            $responseBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);
            if ($curlErr !== '') {
                $this->failDeep(new \RuntimeException("cURL: {$curlErr}"), $userPrompt, 'callOllama', [
                    'url' => $url, 'http_code' => $httpCode, 'body_preview' => $responseBody ? substr($responseBody, 0, 500) : null,
                ]);
                throw new \RuntimeException($curlErr);
            }
        } catch (Throwable $e) {
            if (!str_contains($e->getMessage(), 'cURL')) {
                $this->failDeep($e, $userPrompt, 'callOllama', ['url' => $url]);
            }
            throw $e;
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($responseBody, true);
            $errMsg = $decoded['error'] ?? $responseBody;
            $this->failDeep(
                new \RuntimeException("Ollama HTTP {$httpCode}: {$errMsg}"),
                $userPrompt,
                'callOllama',
                ['url' => $url, 'http_code' => $httpCode, 'body' => $responseBody]
            );
            throw new \RuntimeException("Ollama HTTP {$httpCode}");
        }

        $data = json_decode($responseBody, true);
        if (!is_array($data)) {
            $this->failDeep(
                new \RuntimeException('Ollama response geen geldige JSON'),
                $userPrompt,
                'callOllama',
                ['raw' => $responseBody]
            );
            throw new \RuntimeException('Invalid Ollama JSON');
        }

        return $data['response'] ?? '';
    }

    public function failDeep(Throwable $e, string $input, string $phase, array $extra = []): void
    {
        $out = [
            '',
            '========== HAVUN AI BRIDGE – FOUTANALYSE ==========',
            'Fase: ' . $phase,
            get_class($e) . ' – ' . $e->getMessage(),
            $e->getFile() . ':' . $e->getLine(),
            '',
            '--- Mogelijke oorzaken ---',
        ];

        if (str_contains($e->getMessage(), 'Connection') || str_contains($e->getMessage(), 'curl')) {
            $out[] = '- Ollama niet bereikbaar op ' . OLLAMA_URL . '. Start: ollama serve';
            $out[] = '  Check: curl ' . OLLAMA_URL . '/api/tags';
        }
        if (isset($extra['http_code']) && (int) $extra['http_code'] === 404) {
            $body = $extra['body'] ?? '';
            if (is_string($body) && (str_contains($body, 'not found') || str_contains($body, 'model'))) {
                $out[] = '- Model ' . OLLAMA_MODEL . ' niet geïnstalleerd. Actie: ollama pull ' . OLLAMA_MODEL;
            }
        }
        if (str_contains($e->getMessage(), 'Database') || str_contains($e->getMessage(), 'doc_embeddings') || $phase === 'retrieveContext') {
            $out[] = '- Doc Intelligence DB ontbreekt of tabel leeg.';
            $out[] = '  Actie: php artisan migrate --database=doc_intelligence && php artisan docs:index all --force';
        }
        if (!empty($extra)) {
            $out[] = '';
            $out[] = 'Extra: ' . json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        $out[] = '';
        $out[] = 'Stack trace:';
        $out[] = $e->getTraceAsString();
        $out[] = '================================================';
        echo implode("\n", $out);
    }
}
