<?php

namespace App\Services\DocsAudit;

use App\Enums\Severity;

/**
 * Cross-project markdown-audit orchestrator. Runs 4 detector passes
 * (obsolete / structure / link / zombie) over a project's docs tree
 * and returns a flat array of findings ready for rendering.
 *
 * Detectors are intentionally simple file-scanners. Semantic checks
 * (overlap via embeddings, cross-doc inconsistency) are delegated to
 * existing DocIntelligence services — this auditor is lightweight.
 */
class DocsAuditor
{
    /**
     * Auto-gegenereerde rapport-files skippen — ze zouden zichzelf als HIGH
     * flaggen (missende frontmatter, korte lengte, etc.) en dat is geen
     * actionable finding. Filtert in de orchestrator zodat detectors dumb
     * blijven (single responsibility per check).
     */
    private const SELF_EXCLUDED_BASENAMES = [
        'kb-audit-latest.md',
        'qv-scan-latest.md',
        'handover.md',
    ];

    /**
     * Directory-niveau exclusies. `archive/` is een portfolio-conventie
     * voor bedoeld-historische documentatie (broken links etc. horen daar
     * vaak bij en zijn geen actionable finding). `worktrees/` is Claude's
     * eigen git-worktree tijdelijke opslag.
     */
    private const EXCLUDED_PATH_SEGMENTS = [
        DIRECTORY_SEPARATOR . 'archive' . DIRECTORY_SEPARATOR,
        DIRECTORY_SEPARATOR . 'worktrees' . DIRECTORY_SEPARATOR,
    ];


    /**
     * @param  array<int,string>  $scanRoots  Absolute directories to recurse (e.g. ['/path/docs', '/path/.claude'])
     * @param  string              $codebaseRoot  Absolute project root for zombie-check code-lookup
     * @return array{findings:list<array<string,mixed>>,totals:array<string,int>,scanned:int}
     */
    public function audit(array $scanRoots, string $codebaseRoot): array
    {
        $files = $this->collectMarkdownFiles($scanRoots);

        $obsolete = new ObsoleteChecker();
        $structure = new StructureChecker();
        $link = new LinkChecker();
        $zombie = new ZombieChecker($codebaseRoot);

        $findings = [];
        foreach ($files as $absolutePath) {
            $findings = array_merge($findings, $obsolete->check($absolutePath));
            $findings = array_merge($findings, $structure->check($absolutePath));
            $findings = array_merge($findings, $link->check($absolutePath));
            $findings = array_merge($findings, $zombie->check($absolutePath));
        }

        return [
            'findings' => $findings,
            'totals' => $this->tallyBySeverity($findings),
            'scanned' => count($files),
        ];
    }

    /**
     * @param  array<int,string>  $roots
     * @return list<string>
     */
    private function collectMarkdownFiles(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                    continue;
                }
                if (in_array($file->getFilename(), self::SELF_EXCLUDED_BASENAMES, true)) {
                    continue;
                }
                $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file->getPathname());
                $skipDueToPath = false;
                foreach (self::EXCLUDED_PATH_SEGMENTS as $segment) {
                    if (str_contains($normalized, $segment)) {
                        $skipDueToPath = true;
                        break;
                    }
                }
                if ($skipDueToPath) {
                    continue;
                }
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @param  list<array<string,mixed>>  $findings
     * @return array<string,int>
     */
    private function tallyBySeverity(array $findings): array
    {
        $tally = [
            Severity::Critical->value => 0,
            Severity::High->value => 0,
            Severity::Medium->value => 0,
            Severity::Low->value => 0,
            Severity::Info->value => 0,
        ];
        foreach ($findings as $f) {
            $sev = $f['severity'] ?? Severity::Medium->value;
            if (isset($tally[$sev])) {
                $tally[$sev]++;
            }
        }

        return $tally;
    }
}
