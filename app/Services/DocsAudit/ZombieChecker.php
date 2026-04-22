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
     * Globale PHP/Laravel/standard-library klassen die nooit "zombies" zijn
     * al staan ze niet in app/. Voorkomt false-positives op common types
     * die overal in docs voorkomen.
     */
    private const GLOBAL_WHITELIST = [
        // PHP built-ins
        'PDO', 'PDOException', 'Throwable', 'Exception', 'Error', 'TypeError',
        'RuntimeException', 'LogicException', 'InvalidArgumentException',
        'ArrayIterator', 'DateTime', 'DateTimeImmutable', 'DateTimeZone',
        'Generator', 'Closure', 'Iterator', 'IteratorAggregate', 'Countable',
        'ArrayAccess', 'JsonSerializable', 'ReflectionClass', 'ReflectionMethod',
        // Laravel / Carbon / common packages
        'Carbon', 'CarbonImmutable', 'Collection', 'Str', 'Arr',
        'Log', 'Cache', 'DB', 'Schema', 'Storage', 'Http', 'Mail',
        'Queue', 'Event', 'Gate', 'Auth', 'Session', 'Request', 'Response',
        'View', 'Route', 'Artisan', 'Broadcast', 'Validator', 'Hash',
        'Model', 'Builder', 'Controller', 'Command', 'Rule', 'FormRequest',
        'Mockery', 'RefreshDatabase', 'Storage', 'Process',
        // Laravel concerns/traits/contracts
        'Dispatchable', 'InteractsWithQueue', 'Queueable', 'SerializesModels',
        'ShouldQueue', 'Notifiable', 'HasFactory', 'HasApiTokens', 'SoftDeletes',
        'PersonalAccessToken', 'Sanctum', 'HasRoles', 'HasPermissions',
        // Eloquent types
        'HasOne', 'HasMany', 'BelongsTo', 'BelongsToMany', 'MorphTo',
        // Livewire
        'Component', 'Computed',
    ];

    /**
     * Basename-set van alle class/interface/trait/enum-declaraties in app/.
     * Geïnitialiseerd in de constructor via één tree-walk; daarna O(1)
     * membership-test per check(). Voorkomt O(refs × files_php) per doc
     * wanneer het project groeit.
     *
     * @var array<string,true>|null  null tot lazy-init
     */
    private ?array $classIndex = null;

    /**
     * Cross-project class-index: basenames van alle classes in andere
     * portfolio-projecten (via config/quality-safety.php paden). HavunCore-
     * docs refereren legitimately aan JudoToernooi/HP/etc. klassen —
     * whitelist die zodat ze geen HIGH zombie-finding krijgen.
     *
     * @var array<string,true>|null
     */
    private ?array $crossProjectIndex = null;

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
        // Begrens op closing-backtick zodat `php artisan foo:bar args` niet
        // als ongeldige "foo:bar args" wordt geparsed.
        preg_match_all('/`php artisan ([a-z][a-z0-9:_\-]+)[^`]*`/', $content, $m);
        foreach ($m[1] as $sig) {
            $sigs[] = $sig;
        }

        return array_values(array_unique($sigs));
    }

    private function classExists(string $ref): bool
    {
        $basename = str_contains($ref, '\\')
            ? substr($ref, strrpos($ref, '\\') + 1)
            : $ref;

        // Globale/built-in whitelist (PHP/Laravel/Carbon etc.) — nooit zombie.
        if (in_array($basename, self::GLOBAL_WHITELIST, true)) {
            return true;
        }

        // FQN: check direct via autoloader.
        if (str_contains($ref, '\\')) {
            if (class_exists($ref) || interface_exists($ref) || trait_exists($ref) || enum_exists($ref)) {
                return true;
            }
        } else {
            // Bareword — probeer Laravel facade-alias.
            $aliases = $this->loadFacadeAliases();
            if (isset($aliases[$ref]) && class_exists($aliases[$ref])) {
                return true;
            }
        }

        // Fallback laag 1: grep in deze app/.
        if ($this->grepClassName($basename)) {
            return true;
        }

        // Fallback laag 2: cross-portfolio check — doc mag legitiem refereren
        // naar een class in een ander Havun-project.
        return isset($this->crossProjectIndex()[$basename]);
    }

    /**
     * Standard Laravel/PHP built-in artisan commands die altijd bestaan,
     * ook al staat er geen Command-class voor in app/Console/Commands.
     * Stub-prefix scan: `migrate`, `make:foo`, `db:seed` etc. variants.
     */
    private const ARTISAN_BUILTIN_PREFIXES = [
        'about', 'cache:', 'clear-compiled', 'config:', 'db:', 'down', 'env',
        'event:', 'help', 'inspire', 'key:', 'lang:', 'list', 'migrate',
        'model:', 'optimize', 'package:', 'queue:', 'route:', 'sail:',
        'schedule:', 'serve', 'session:', 'storage:', 'stub:', 'test',
        'tinker', 'up', 'vendor:', 'view:', 'make:', 'install:',
    ];

    private function artisanCommandExists(string $signature): bool
    {
        $name = preg_split('/\s+/', $signature)[0] ?? $signature;

        // Built-in/standard Laravel artisan commands — altijd OK.
        foreach (self::ARTISAN_BUILTIN_PREFIXES as $prefix) {
            if ($name === rtrim($prefix, ':') || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        // Grep in target project's Commands/ directly — HavunCore's Artisan::all()
        // geeft alleen HavunCore-commands, nutteloos voor cross-project audits.
        if ($this->greppedInTargetProject($name)) {
            return true;
        }

        // Fallback: andere Havun-projecten (doc in HP kan refereren aan een
        // HavunAdmin command bijv.).
        return $this->greppedInOtherProjects($name);
    }

    private function greppedInTargetProject(string $commandName): bool
    {
        $cmdDir = $this->codebaseRoot . '/app/Console/Commands';
        if (! is_dir($cmdDir)) {
            return false;
        }

        return $this->grepCommandInDir($cmdDir, $commandName);
    }

    private function greppedInOtherProjects(string $commandName): bool
    {
        try {
            $projects = (array) config('quality-safety.projects', []);
        } catch (\Throwable) {
            return true;
        }

        foreach ($projects as $entry) {
            if (! is_array($entry) || ($entry['enabled'] ?? false) !== true) {
                continue;
            }
            $root = (string) ($entry['path'] ?? '');
            if ($root === '' || $root === $this->codebaseRoot) {
                continue;
            }
            $cmdDir = $root . '/app/Console/Commands';
            if (is_dir($cmdDir) && $this->grepCommandInDir($cmdDir, $commandName)) {
                return true;
            }
        }

        return false;
    }

    private function grepCommandInDir(string $cmdDir, string $commandName): bool
    {
        $escaped = preg_quote($commandName, '/');
        $pattern = "/\\\$signature\\s*=\\s*['\"]{$escaped}\\b/";

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cmdDir, \FilesystemIterator::SKIP_DOTS)
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
        } catch (\Throwable) {
            // unreadable dir
        }

        return false;
    }

    private function grepClassName(string $basename): bool
    {
        return isset($this->classIndex()[$basename]);
    }

    /**
     * Bouwt de class-index lazy on first call. Walk app/ + tests/ één keer,
     * regex-extract alle declared types, basename → true map. Tests zitten
     * erbij omdat docs legitiem refereren aan Test-class namen
     * (kritieke paden doc, mutation-baseline docs, etc.).
     *
     * @return array<string,true>
     */
    private function classIndex(): array
    {
        if ($this->classIndex !== null) {
            return $this->classIndex;
        }

        $index = [];
        foreach (['app', 'tests'] as $sub) {
            $dir = $this->codebaseRoot . '/' . $sub;
            if (! is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $src = @file_get_contents($file->getPathname());
                if ($src === false) {
                    continue;
                }
                if (preg_match_all('/\b(?:class|interface|trait|enum)\s+([A-Z][A-Za-z0-9_]*)/', $src, $m)) {
                    foreach ($m[1] as $name) {
                        $index[$name] = true;
                    }
                }
            }
        }

        return $this->classIndex = $index;
    }

    /**
     * Bouwt de cross-project class-index door alle Havun-projecten uit
     * quality-safety.php te walk'en. Lazy, één keer per ZombieChecker-
     * instance. Grote projecten → klein aantal ms per initialisatie.
     *
     * @return array<string,true>
     */
    private function crossProjectIndex(): array
    {
        if ($this->crossProjectIndex !== null) {
            return $this->crossProjectIndex;
        }

        $projects = [];
        try {
            $projects = (array) config('quality-safety.projects', []);
        } catch (\Throwable) {
            // Fallback naar lege config in niet-Laravel context.
        }

        $index = [];
        foreach ($projects as $entry) {
            if (! is_array($entry) || ($entry['enabled'] ?? false) !== true) {
                continue;
            }
            $root = (string) ($entry['path'] ?? '');
            if ($root === '' || $root === $this->codebaseRoot) {
                continue;
            }
            foreach (['app', 'tests'] as $sub) {
                $dir = $root . '/' . $sub;
                if (! is_dir($dir)) {
                    continue;
                }
                try {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
                    );
                    foreach ($iterator as $file) {
                        if (! $file->isFile() || $file->getExtension() !== 'php') {
                            continue;
                        }
                        $src = @file_get_contents($file->getPathname());
                        if ($src === false) {
                            continue;
                        }
                        if (preg_match_all('/\b(?:class|interface|trait|enum)\s+([A-Z][A-Za-z0-9_]*)/', $src, $m)) {
                            foreach ($m[1] as $name) {
                                $index[$name] = true;
                            }
                        }
                    }
                } catch (\Throwable) {
                    // Project-tree onbereikbaar → skippen, geen crash.
                }
            }
        }

        return $this->crossProjectIndex = $index;
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
