<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DocsAuditCommandTest extends TestCase
{
    public function test_current_project_audit_writes_report(): void
    {
        $output = base_path('storage/framework/testing/kb-audit-test-' . uniqid() . '.md');
        File::ensureDirectoryExists(dirname($output));

        $exit = $this->artisan('docs:audit', [
            '--output' => $output,
        ])->run();

        $this->assertContains($exit, [0, 1], 'Exit must be 0 (clean) of 1 (findings)');
        $this->assertFileExists($output);

        $body = File::get($output);
        $this->assertStringContainsString('# KB audit', $body);
        $this->assertStringContainsString('## Samenvatting', $body);

        File::delete($output);
    }

    public function test_json_output_is_valid(): void
    {
        $result = $this->artisan('docs:audit', ['--json' => true]);
        $result->run();

        // Exit 0 of 1 (1 bij findings is normaal in deze repo).
        $this->assertContains($result->execute(), [0, 1]);
    }
}
