<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocEmbedding;
use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\CreatesDocIntelligenceTables;
use Tests\TestCase;

/**
 * Regressie op het incident van 19-07-2026: `docs:index` indexeerde `credentials.md`,
 * waarmee echte productie-secrets in `doc_embeddings` belandden (preview + embedding).
 * De `isSensitiveFile`-guard in DocIndexer is toen toegevoegd — maar er kwam geen test
 * mee, dus hij kon stil sneuvelen. Deze test is die vangnet.
 *
 * Gemeten 24-07-2026: zonder de guard indexeert `indexProject()` credentials.md gewoon.
 */
#[Group('doc-intelligence')]
class DocIndexerSecretsGuardTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();
        DocEmbedding::query()->delete();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response([
                'embedding' => array_map(static fn (int $i): float => $i / 768, range(1, 768)),
            ], 200),
        ]);

        $this->tempDir = sys_get_temp_dir() . '/secretsguard_' . uniqid();
        mkdir($this->tempDir . '/.claude', 0777, true);

        // De kluis staat in .claude/ — precies waar hij op 19-07 vandaan kwam.
        file_put_contents(
            $this->tempDir . '/.claude/credentials.md',
            "# Kluis\n\nadmin / hunter2-VOORBEELD\n"
        );
        file_put_contents(
            $this->tempDir . '/gewoon.md',
            "# Gewoon document\n\nDit hoort wél geïndexeerd te worden.\n"
        );

        config()->set('havun-projects', [
            'secretsguardtest' => [
                'path' => $this->tempDir,
                'server_path' => $this->tempDir,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir . '/.claude/credentials.md');
        @unlink($this->tempDir . '/gewoon.md');
        @rmdir($this->tempDir . '/.claude');
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    public function test_credentials_md_wordt_nooit_geindexeerd(): void
    {
        (new DocIndexer())->indexProject('secretsguardtest', includeCode: false);

        $paden = DocEmbedding::query()->pluck('file_path')->all();

        $this->assertNotContains(
            '.claude/credentials.md',
            $paden,
            'KRITIEK: credentials.md staat in de KB-index — secrets lekken via preview + embedding.'
        );
        $this->assertContains(
            'gewoon.md',
            $paden,
            'De guard slaat te veel over: een gewoon document werd niet geïndexeerd.'
        );
    }

    public function test_de_inhoud_van_de_kluis_belandt_nergens_in_de_index(): void
    {
        (new DocIndexer())->indexProject('secretsguardtest', includeCode: false);

        // Niet op bestandsnaam maar op inhoud: ook als het pad ooit anders heet,
        // mag de wachtwoordregel nergens in de index opduiken. De kolom heet
        // `content` en bevat de vólledige bestandsinhoud — daar zit het lek.
        $treffers = DocEmbedding::query()
            ->where('content', 'like', '%hunter2-VOORBEELD%')
            ->count();

        $this->assertSame(0, $treffers, 'KRITIEK: kluisinhoud staat in doc_embeddings.content.');
    }

    /**
     * `.env` en `.env.<omgeving>` zijn gevoelig, `.env.example` niet.
     *
     * Dit is een directe unit-test op de guard, want via `indexProject()` is deze tak
     * onbereikbaar: `findMdFiles()` zoekt `*.md` en `findCodeFiles()` alleen
     * php/js/ts/jsx/tsx/vue — een `.env` komt dus sowieso nooit langs de guard.
     * De bescherming is defensief; deze test legt vast dát ze klopt, mocht de scan
     * ooit worden verbreed.
     */
    public function test_env_bestanden_gelden_als_gevoelig_behalve_example(): void
    {
        $guard = new \ReflectionMethod(DocIndexer::class, 'isSensitiveFile');
        $guard->setAccessible(true);
        $indexer = new DocIndexer();

        foreach (['.env', '.env.production', '.env.local', 'config/.env.staging'] as $pad) {
            $this->assertTrue($guard->invoke($indexer, $pad), "{$pad} hoort gevoelig te zijn");
        }

        foreach (['.env.example', 'docs/readme.md', 'app/Models/User.php'] as $pad) {
            $this->assertFalse($guard->invoke($indexer, $pad), "{$pad} hoort NIET gevoelig te zijn");
        }
    }

    public function test_guard_kijkt_naar_de_bestandsnaam_niet_naar_de_map(): void
    {
        $guard = new \ReflectionMethod(DocIndexer::class, 'isSensitiveFile');
        $guard->setAccessible(true);
        $indexer = new DocIndexer();

        $this->assertTrue($guard->invoke($indexer, 'ergens/diep/credentials.md'));
        $this->assertTrue($guard->invoke($indexer, 'CREDENTIALS.MD'), 'hoofdletters mogen niet ontsnappen');
        $this->assertFalse($guard->invoke($indexer, 'docs/credentials-beleid.md'), 'geen valse treffer op een gewoon doc');
    }
}
