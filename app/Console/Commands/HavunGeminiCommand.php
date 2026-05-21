<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\NormalizesPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Output\BufferedOutput;

class HavunGeminiCommand extends Command
{
    use NormalizesPath;

    protected $signature = 'havun:gemini
                            {prompt : De opdracht voor Gemini}
                            {--project= : Project om in te pakken via havun:pack (optioneel)}
                            {--model=gemini-2.5-flash : Gemini model}
                            {--out= : Schrijf output naar bestand}
                            {--include-source : Voeg broncode + live API toe aan pack context}';

    protected $description = 'Stuur een prompt naar Gemini, optioneel met havun:pack context';

    public function handle(): int
    {
        $apiKey = config('services.gemini.api_key');
        if (! $apiKey) {
            $this->error('GEMINI_API_KEY niet ingesteld.');
            return Command::FAILURE;
        }

        $prompt = $this->argument('prompt');
        $project = $this->option('project');
        $model = $this->option('model');
        $includeSource = (bool) $this->option('include-source');

        $context = $project ? $this->packProject($project, $includeSource) : '';

        $fullPrompt = $context
            ? "PROJECTCONTEXT:\n{$context}\n\nOPDRACHT:\n{$prompt}"
            : $prompt;

        $this->line("Gemini ({$model}) wordt aangesproken...", null, 'v');

        // withoutVerifying() is required: Windows TLS cert revocation check fails for googleapis.com
        $response = Http::withoutVerifying()
            ->timeout(300)
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}",
                ['contents' => [['parts' => [['text' => $fullPrompt]]]]]
            );

        if (! $response->successful()) {
            $this->error('Gemini API fout: ' . $response->body());
            return Command::FAILURE;
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text', '');

        $out = $this->option('out') ?: $this->defaultOutputPath($project);

        if ($out) {
            $content = $this->wrapWithHeader($text, $project, $model);
            if (file_put_contents($out, $content) === false) {
                $this->error("Schrijven naar {$out} mislukt.");
                return Command::FAILURE;
            }
            $this->line("Blueprint: {$out}");
        } else {
            $this->line($text);
        }

        return Command::SUCCESS;
    }

    private function wrapWithHeader(string $text, ?string $project, string $model): string
    {
        $timestamp = now()->format('Y-m-d H:i');
        $projectLabel = $project ? strtoupper($project) : 'geen project';

        $header = "> **Blueprint** — {$projectLabel} | Gegenereerd: {$timestamp} | Model: {$model}\n\n";

        return $header . $text;
    }

    private function defaultOutputPath(?string $project): ?string
    {
        if (! $project) {
            return null;
        }

        $projects = config('havun-projects');
        $config = $projects[strtolower($project)] ?? null;
        $projectPath = $config['path'] ?? null;

        return $projectPath
            ? $this->normalizePath($projectPath . '/.claude/blueprint.md')
            : null;
    }

    private function packProject(string $project, bool $includeSource): string
    {
        $label = $includeSource ? ' (+ broncode + live API)' : '';
        $this->line("Context inpakken voor project: {$project}{$label}...", null, 'v');

        $args = ['--project' => $project];
        if ($includeSource) {
            $args['--include-source'] = true;
        }

        $buffer = new BufferedOutput();
        $this->call('havun:pack', $args, $buffer);
        return $buffer->fetch();
    }
}
