<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Models\DocIntelligence\DocRelation;
use App\Services\DocIntelligence\DocIndexer;
use App\Services\DocIntelligence\StructureIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\CreatesDocIntelligenceTables;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('doc-intelligence')]
class StructureIndexerCoverageTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    private StructureIndexer $structureIndexer;
    private DocIndexer $docIndexer;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();

        DocEmbedding::query()->delete();
        DocIssue::query()->delete();
        DocRelation::query()->delete();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);

        $this->docIndexer = new DocIndexer();
        $this->structureIndexer = new StructureIndexer($this->docIndexer);

        $this->tempDir = sys_get_temp_dir() . '/structindexer_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    // ===================================================================
    // generateSummary — more branches
    // ===================================================================

    public function test_generate_summary_includes_services(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Laravel');
        $structure['services'] = [
            ['name' => 'PaymentService', 'methods' => 3, 'lines' => 50],
        ];

        $summary = $method->invoke($this->structureIndexer, 'test', $structure);

        $this->assertStringContainsString('## Services (1)', $summary);
        $this->assertStringContainsString('PaymentService', $summary);
    }

    public function test_generate_summary_includes_middleware(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Laravel');
        $structure['middleware'] = [
            ['name' => 'Authenticate'],
        ];

        $summary = $method->invoke($this->structureIndexer, 'test', $structure);

        $this->assertStringContainsString('## Middleware (1)', $summary);
        $this->assertStringContainsString('Authenticate', $summary);
    }

    public function test_generate_summary_includes_enums(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Laravel');
        $structure['enums'] = [
            ['name' => 'Status'],
        ];

        $summary = $method->invoke($this->structureIndexer, 'test', $structure);

        $this->assertStringContainsString('## Enums (1)', $summary);
        $this->assertStringContainsString('Status', $summary);
    }

    public function test_generate_summary_includes_commands(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Laravel');
        $structure['commands'] = [
            ['name' => 'IndexDocs'],
        ];

        $summary = $method->invoke($this->structureIndexer, 'test', $structure);

        $this->assertStringContainsString('## Commands (1)', $summary);
        $this->assertStringContainsString('IndexDocs', $summary);
    }

    public function test_generate_summary_includes_config_keys(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Laravel');
        $structure['config_keys'] = ['services', 'autofix'];

        $summary = $method->invoke($this->structureIndexer, 'test', $structure);

        $this->assertStringContainsString('## Custom Config:', $summary);
        $this->assertStringContainsString('services', $summary);
    }

    public function test_generate_summary_includes_packages(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Laravel');
        $structure['laravel_version'] = '^11.0';
        $structure['php_version'] = '^8.2';
        $structure['packages'] = ['php', 'laravel/framework', 'guzzlehttp/guzzle'];

        $summary = $method->invoke($this->structureIndexer, 'test', $structure);

        $this->assertStringContainsString('Laravel: ^11.0', $summary);
        $this->assertStringContainsString('## Composer Packages', $summary);
        $this->assertStringContainsString('guzzlehttp/guzzle', $summary);
        // php and laravel/ should be filtered out
        $this->assertStringNotContainsString('- php', $summary);
    }

    public function test_generate_summary_includes_npm_packages(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Node.js');
        $structure['npm_packages'] = ['express', 'jest'];

        $summary = $method->invoke($this->structureIndexer, 'test', $structure);

        $this->assertStringContainsString('## NPM Packages (2)', $summary);
        $this->assertStringContainsString('express', $summary);
    }

    public function test_generate_summary_includes_migrations(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Laravel');
        $structure['migrations'] = 10;

        $summary = $method->invoke($this->structureIndexer, 'test', $structure);

        $this->assertStringContainsString('## Migrations: 10', $summary);
    }

    // ===================================================================
    // analyzeLaravel — additional branches
    // ===================================================================

    public function test_analyze_laravel_detects_middleware(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Http/Middleware/Auth.php', <<<'PHP'
<?php
namespace App\Http\Middleware;
class Auth {
    public function handle() {}
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['middleware']);
        $this->assertEquals('Auth', $structure['middleware'][0]['name']);
    }

    public function test_analyze_laravel_detects_enums(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Enums/Status.php', <<<'PHP'
<?php
namespace App\Enums;
enum Status: string {
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['enums']);
        $this->assertEquals('Status', $structure['enums'][0]['name']);
    }

    public function test_analyze_laravel_detects_commands(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Console/Commands/IndexDocs.php', <<<'PHP'
<?php
namespace App\Console\Commands;
class IndexDocs {
    public function handle() {}
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['commands']);
        $this->assertEquals('IndexDocs', $structure['commands'][0]['name']);
    }

    public function test_analyze_laravel_detects_jobs(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Jobs/SendEmail.php', <<<'PHP'
<?php
namespace App\Jobs;
class SendEmail {
    public function handle() {}
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['jobs']);
        $this->assertEquals('SendEmail', $structure['jobs'][0]['name']);
    }

    public function test_analyze_laravel_detects_events(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Events/UserCreated.php', <<<'PHP'
<?php
namespace App\Events;
class UserCreated {
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['events']);
    }

    public function test_analyze_laravel_detects_traits(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Traits/HasUuid.php', <<<'PHP'
<?php
namespace App\Traits;
trait HasUuid {
    public function initializeHasUuid() {}
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['traits']);
    }

    public function test_analyze_laravel_reads_package_json(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('package.json', json_encode([
            'dependencies' => ['vue' => '^3.0'],
            'devDependencies' => ['vite' => '^5.0'],
        ]));

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['npm_packages']);
        $this->assertContains('vue', $structure['npm_packages']);
        $this->assertContains('vite', $structure['npm_packages']);
    }

    public function test_analyze_laravel_nested_structure(): void
    {
        // Some projects have laravel/ subdirectory
        $this->createFile('laravel/artisan', '#!/usr/bin/env php');
        $this->createFile('laravel/app/Models/User.php', <<<'PHP'
<?php
namespace App\Models;
class User extends Model {
    public function posts() { return $this->hasMany(Post::class); }
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        // Should detect models from the nested laravel/app/Models path
        // Since it checks if laravel/app exists
        if (is_dir($this->tempDir . '/laravel/app')) {
            $this->assertNotEmpty($structure['models']);
        }
    }

    // ===================================================================
    // analyzeNode — frontend/src branch
    // ===================================================================

    public function test_analyze_node_scans_frontend_src(): void
    {
        $this->createFile('package.json', json_encode(['dependencies' => []]));
        $this->createFile('frontend/src/main.js', <<<'JS'
export default function init() {}
JS);

        $structure = $this->invokeAnalyzeNode($this->tempDir);

        $this->assertNotEmpty($structure['services']);
    }

    // ===================================================================
    // scanPhpClasses — interface/trait detection
    // ===================================================================

    public function test_scan_php_classes_detects_implements(): void
    {
        $this->createFile('app/Models/User.php', <<<'PHP'
<?php
namespace App\Models;
class User extends Model implements HasRoles, Authenticatable {
    public function name() {}
}
PHP);

        $method = new \ReflectionMethod(StructureIndexer::class, 'scanPhpClasses');
        $method->setAccessible(true);

        $classes = $method->invoke($this->structureIndexer, $this->tempDir . '/app/Models');

        $this->assertNotEmpty($classes);
        $this->assertEquals('User', $classes[0]['name']);
        $this->assertArrayHasKey('implements', $classes[0]);
    }

    public function test_scan_php_classes_skips_files_without_class(): void
    {
        $this->createFile('app/Models/helpers.php', '<?php function helper() {}');

        $method = new \ReflectionMethod(StructureIndexer::class, 'scanPhpClasses');
        $method->setAccessible(true);

        $classes = $method->invoke($this->structureIndexer, $this->tempDir . '/app/Models');

        $this->assertEmpty($classes);
    }

    public function test_scan_php_classes_returns_empty_for_missing_dir(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'scanPhpClasses');
        $method->setAccessible(true);

        $classes = $method->invoke($this->structureIndexer, $this->tempDir . '/nonexistent');

        $this->assertEmpty($classes);
    }

    // ===================================================================
    // scanJsModules
    // ===================================================================

    public function test_scan_js_modules_returns_empty_for_missing_dir(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'scanJsModules');
        $method->setAccessible(true);

        $modules = $method->invoke($this->structureIndexer, $this->tempDir . '/nonexistent');

        $this->assertEmpty($modules);
    }

    public function test_scan_js_modules_finds_exports(): void
    {
        $this->createFile('src/api.ts', <<<'JS'
export default function fetchData() {}
export const API_URL = '/api';
export class ApiClient {}
JS);

        $method = new \ReflectionMethod(StructureIndexer::class, 'scanJsModules');
        $method->setAccessible(true);

        $modules = $method->invoke($this->structureIndexer, $this->tempDir . '/src');

        $this->assertNotEmpty($modules);
        $this->assertEquals('api', $modules[0]['name']);
        $this->assertEquals(3, $modules[0]['exports']);
    }

    public function test_scan_js_modules_finds_vue_files(): void
    {
        $this->createFile('src/App.vue', '<template><div>App</div></template>');

        $method = new \ReflectionMethod(StructureIndexer::class, 'scanJsModules');
        $method->setAccessible(true);

        $modules = $method->invoke($this->structureIndexer, $this->tempDir . '/src');

        $this->assertNotEmpty($modules);
        $this->assertEquals('App', $modules[0]['name']);
    }

    // ===================================================================
    // extractRoutes
    // ===================================================================

    public function test_extract_routes_empty_dir(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'extractRoutes');
        $method->setAccessible(true);

        $routes = $method->invoke($this->structureIndexer, $this->tempDir . '/nonexistent');

        $this->assertEmpty($routes);
    }

    // ===================================================================
    // extractConfigKeys
    // ===================================================================

    public function test_extract_config_keys_empty_dir(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'extractConfigKeys');
        $method->setAccessible(true);

        $keys = $method->invoke($this->structureIndexer, $this->tempDir . '/nonexistent');

        $this->assertEmpty($keys);
    }

    // ===================================================================
    // Helpers
    // ===================================================================

    private function createFile(string $relativePath, string $content): void
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($fullPath, $content);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    private function emptyStructure(string $project, string $type): array
    {
        return [
            'project' => $project,
            'path' => '/tmp/test',
            'type' => $type,
            'models' => [],
            'controllers' => [],
            'services' => [],
            'middleware' => [],
            'enums' => [],
            'migrations' => 0,
            'routes' => [],
            'config_keys' => [],
            'commands' => [],
            'jobs' => [],
            'events' => [],
            'traits' => [],
        ];
    }

    private function invokeAnalyzeLaravel(string $basePath): array
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'analyzeLaravel');
        $method->setAccessible(true);
        return $method->invoke($this->structureIndexer, $basePath, $this->emptyStructure('test', 'Laravel'));
    }

    private function invokeAnalyzeNode(string $basePath): array
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'analyzeNode');
        $method->setAccessible(true);
        return $method->invoke($this->structureIndexer, $basePath, $this->emptyStructure('test', 'Node.js'));
    }
}
