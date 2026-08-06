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

    public function test_a_command_is_not_excused_by_a_project_name_hiding_inside_it(): void
    {
        // `havun` is a registered project, and the "does this belong to another
        // project?" check did a bare substring match on the line. So every
        // havun:* command excused itself, and four dead havun:backup:* commands
        // sat in backup-system.md unreported.
        File::put($this->tmp . '/docs/backup.md', <<<'MD'
---
title: Backup
type: runbook
scope: havuncore
---

# Backup

Herstellen doe je met `php artisan havun:backup:restore`.
MD);

        $result = (new DocsAuditor())->audit([$this->tmp . '/docs'], base_path());

        $zombies = array_filter(
            $result['findings'],
            fn ($f) => ($f['detector'] ?? '') === 'zombie',
        );
        $this->assertNotEmpty($zombies, 'A dead havun:* command must still be reported.');
    }

    public function test_a_command_really_belonging_to_another_project_is_still_excused(): void
    {
        // The exemption itself is sound: a doc may name another project's
        // command. That must keep working after tightening the match.
        File::put($this->tmp . '/docs/headers.md', <<<'MD'
---
title: Headers
type: reference
scope: havuncore
---

# Headers

Draai `php artisan gtag:refresh` (zie Herdenkingsportaal) na een wijziging.
MD);

        $result = (new DocsAuditor())->audit([$this->tmp . '/docs'], base_path());

        $zombies = array_filter(
            $result['findings'],
            fn ($f) => ($f['detector'] ?? '') === 'zombie',
        );
        $this->assertSame([], $zombies, "Another project's command is correct documentation.");
    }

    public function test_a_normal_doc_still_gets_its_zombie_references_flagged(): void
    {
        // The exemption is for decisions/, not a way to switch the check off.
        File::put($this->tmp . '/docs/runbook.md', <<<'MD'
---
title: Runbook
type: runbook
scope: havuncore
---

# Runbook

Draai `php artisan qv:scan-residu` om te starten.
MD);

        // Tegen de echte codebase, niet tegen de lege tijdelijke map: alleen dan
        // kan de checker daadwerkelijk zoeken, en dat is hoe hij in productie
        // draait. `havun:orchestrate` is in mei verwijderd.
        $result = (new DocsAuditor())->audit([$this->tmp . '/docs'], base_path());

        $zombies = array_filter(
            $result['findings'],
            fn ($f) => ($f['detector'] ?? '') === 'zombie',
        );
        $this->assertNotEmpty($zombies, 'A runbook pointing at a dead command is still rot.');
    }
}
