<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DocsHandoverCommandTest extends TestCase
{
    public function test_writes_a_handover_file_with_required_sections(): void
    {
        $tmp = base_path('storage/framework/testing/handover-test.md');
        File::ensureDirectoryExists(dirname($tmp));
        if (File::exists($tmp)) {
            File::delete($tmp);
        }

        $exitCode = $this->artisan('docs:handover', [
            '--days' => 1,
            '--output' => 'storage/framework/testing/handover-test.md',
        ])->run();

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($tmp);

        $body = File::get($tmp);
        $this->assertStringContainsString('# Handover (auto-generated)', $body);
        $this->assertStringContainsString('## Recente activiteit', $body);
        $this->assertStringContainsString('## V&K status', $body);
        $this->assertStringContainsString('## Verdiepende bronnen', $body);
        $this->assertStringContainsString('Bewerk dit bestand niet handmatig', $body);

        File::delete($tmp);
    }
}
