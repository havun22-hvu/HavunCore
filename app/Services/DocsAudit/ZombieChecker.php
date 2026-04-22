<?php

namespace App\Services\DocsAudit;

use App\Enums\Severity;

/**
 * Zombie-check: detecteert docs die verwijzen naar classes/commands die
 * niet meer in de codebase bestaan. Voorkomt dat KB een museum wordt.
 *
 * Patronen die we zoeken in MD-content:
 * - Backtick-quoted class/method refs (`App\Services\Foo`, `Foo::bar`)
 * - Artisan command refs (`php artisan foo:bar`)
 *
 * We zoeken de referentie in de codebase via ripgrep-achtige recursive
 * grep. Missend in app/ én app/ laravel-style aliases → Zombie.
 *
 * Facade-aware: `Log::` en `\Log::` matchen tegen Illuminate facade-
 * aliases EN tegen custom App-level facades (via grep op class-name
 * alleen, niet op namespace).
 */
class ZombieChecker
{
    /**
     * @param  string  $codebaseRoot  Absolute project root (e.g. D:/GitHub/HavunCore)
     */
    public function __construct(private readonly string $codebaseRoot)
    {
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function check(string $absolutePath): array
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false) {
            return [];
        }

        // Self-exclude en auto-gen files (geen waarde zombies daar te checken).
        if (str_ends_with($absolutePath, 'kb-audit-latest.md')
            || str_ends_with($absolutePath, 'qv-scan-latest.md')
            || str_ends_with($absolutePath, 'handover.md')) {
            return [];
        }

        $findings = [];

        foreach ($this->extractClassRefs($content) as $classRef) {
            if (! $this->classExists($classRef)) {
                $findings[] = $this->finding($absolutePath, "Class-ref bestaat niet: `{$classRef}`");
            }
        }

        foreach ($this->extractArtisanCommands($content) as $signature) {
            if (! $this->artisanCommandExists($signature)) {
                $findings[] = $this->finding($absolutePath, "Artisan command bestaat niet: `php artisan {$signature}`");
            }
        }

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function extractClassRefs(string $content): array
    {
        $refs = [];

        // Fully-qualified: App\Services\Foo of \Illuminate\...
        preg_match_all('/`(\\\\?[A-Z][A-Za-z0-9_]*(?:\\\\[A-Z][A-Za-z0-9_]*)+)`/', $content, $m);
        foreach ($m[1] as $ref) {
            $refs[] = ltrim($ref, '\\');
        }

        // Bareword class-name met ::method — facades, static calls.
        preg_match_all('/`([A-Z][A-Za-z0-9_]*)::[A-Za-z_][A-Za-z0-9_]*(?:\(\))?`/', $content, $m);
        foreach ($m[1] as $ref) {
            $refs[] = $ref;
        }

        return array_values(array_unique($refs));
    }

    /**
     * @return list<string>
     */
    private function extractArtisanCommands(string $content): array
    {
        $sigs = [];
        preg_match_all('/`php artisan ([a-z][a-z0-9:_\-]+)/', $content, $m);
        foreach ($m[1] as $sig) {
            $sigs[] = $sig;
        }

        return array_values(array_unique($sigs));
    }

    private function classExists(string $ref): bool
    {
        // FQN: check direct.
        if (str_contains($ref, '\\')) {
            if (class_exists($ref) || interface_exists($ref) || trait_exists($ref) || enum_exists($ref)) {
                return true;
            }

            // Fallback: grep op class-basename in codebase (handles
            // PSR-4 + autoload-class-map + facades die elders bestaan).
            $basename = substr($ref, strrpos($ref, '\\') + 1);

            return $this->grepClassName($basename);
        }

        // Bareword (e.g. "Log", "Cache") — probeer facade-alias resolve.
        $aliases = $this->loadFacadeAliases();
        if (isset($aliases[$ref]) && class_exists($aliases[$ref])) {
            return true;
        }

        // Fallback: grep op class-name in codebase.
        return $this->grepClassName($ref);
    }

    private function artisanCommandExists(string $signature): bool
    {
        // Probeer de geregistreerde commands. Kan alleen in Laravel-bootstrapped
        // context — we staan in artisan dus we kunnen de Artisan-facade gebruiken.
        // Bare vergelijking op command-name (alles voor spatie of eind).
        $name = preg_split('/\s+/', $signature)[0] ?? $signature;

        try {
            $all = array_keys(\Illuminate\Support\Facades\Artisan::all());

            return in_array($name, $all, true);
        } catch (\Throwable) {
            return true; // Kan niet verifieren → geen false-positive finding.
        }
    }

    private function grepClassName(string $basename): bool
    {
        // Quick file-based grep: find files die "class Basename" of
        // "interface Basename" of "trait Basename" of "enum Basename" bevatten.
        $pattern = '/\b(?:class|interface|trait|enum)\s+' . preg_quote($basename, '/') . '\b/';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->codebaseRoot . '/app',
                \FilesystemIterator::SKIP_DOTS
            )
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $src = @file_get_contents($file->getPathname());
            if ($src !== false && preg_match($pattern, $src)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,string>
     */
    private function loadFacadeAliases(): array
    {
        try {
            $aliases = config('app.aliases') ?? [];

            return is_array($aliases) ? $aliases : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function finding(string $path, string $detail): array
    {
        return [
            'severity' => Severity::High->value,
            'detector' => 'zombie',
            'file' => $path,
            'detail' => $detail,
            'action' => 'Update doc of herstel class/command',
        ];
    }
}
