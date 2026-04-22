<?php

namespace Tests\Unit\Services\DocsAudit;

use App\Services\DocsAudit\DocsAuditor;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DocsAuditorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/docs-audit-' . uniqid();
        File::makeDirectory($this->tmp . '/docs', 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    public function test_empty_directory_produces_no_findings(): void
    {
        $auditor = new DocsAuditor();

        $result = $auditor->audit([$this->tmp . '/docs'], $this->tmp);

        $this->assertSame(0, $result['scanned']);
        $this->assertSame([], $result['findings']);
    }

    public function test_file_without_frontmatter_is_high(): void
    {
        File::put($this->tmp . '/docs/no-frontmatter.md', "# Title\n\nSome body\n");

        $result = (new DocsAuditor())->audit([$this->tmp . '/docs'], $this->tmp);

        $highFindings = array_filter($result['findings'], fn ($f) => $f['severity'] === 'high' && $f['detector'] === 'structure');
        $this->assertNotEmpty($highFindings);
    }

    public function test_broken_internal_link_is_critical(): void
    {
        File::put(
            $this->tmp . '/docs/main.md',
            "---\ntitle: Main\n---\n\n# Main\n\nZie [ontbrekend](missing-file.md)\n"
        );

        $result = (new DocsAuditor())->audit([$this->tmp . '/docs'], $this->tmp);

        $linkFindings = array_filter($result['findings'], fn ($f) => $f['detector'] === 'link');
        $this->assertNotEmpty($linkFindings);
        $first = array_values($linkFindings)[0];
        $this->assertSame('critical', $first['severity']);
    }

    public function test_totals_count_by_severity(): void
    {
        File::put($this->tmp . '/docs/a.md', "# A\n"); // missing frontmatter -> high; no H1 is present actually, so low only for missing frontmatter
        File::put(
            $this->tmp . '/docs/b.md',
            "---\ntitle: B\n---\n\n# B\n\nZie [x](missing.md)\n"
        );

        $result = (new DocsAuditor())->audit([$this->tmp . '/docs'], $this->tmp);

        $this->assertSame(2, $result['scanned']);
        $this->assertGreaterThan(0, $result['totals']['critical']);
        $this->assertGreaterThan(0, $result['totals']['high']);
    }
}
