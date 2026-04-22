<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Tests\CreatesDocIntelligenceTables;
use Tests\TestCase;

/**
 * Smoke-tests voor `docs:structure` command. Cover van mocked-versies
 * met assertions op output zit in `Tests\Feature\Commands\DocStructureCommandTest`
 * — die hoort de canonical te zijn. Deze file dekt alleen het negative-path
 * (unknown project) scenario dat geen project-fixtures vereist.
 *
 * Reason: StructureIndexer roept DocIndexer::generateEmbeddingPublic() aan
 * dat HTTP::timeout(30)->post(Ollama) doet. Zonder Http::fake hangt elke
 * call 30s per project/model — op CI cumulatief uren. Setup mockt nu de
 * Ollama-endpoint zodat de command snel kan falen op missing paths.
 */
#[Group('doc-intelligence')]
class DocStructureCommandTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);
    }

    public function test_structure_unknown_project_shows_error(): void
    {
        $this->artisan('docs:structure', ['project' => 'nonexistent_xyz'])
            ->expectsOutputToContain('not found')
            ->assertExitCode(1);
    }
}
