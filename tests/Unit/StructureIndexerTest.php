<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Models\DocIntelligence\DocRelation;
use App\Services\DocIntelligence\DocIndexer;
use App\Services\DocIntelligence\StructureIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StructureIndexerTest extends TestCase
{
    use RefreshDatabase;

    private StructureIndexer $structureIndexer;
    private DocIndexer $docIndexer;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        DocEmbedding::query()->delete();
        DocIssue::query()->delete();
        DocRelation::query()->delete();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);

        $this->docIndexer = new DocIndexer();
        $this->structureIndexer = new StructureIndexer($this->docIndexer);

        // Create temp directory for testing
        $this->tempDir = sys_get_temp_dir() . '/havuncore_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Cleanup temp dir
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    // ===================================================================
    // analyzeLaravel
    // ===================================================================

    public function test_analyze_laravel_detects_models(): void
    {
        // Create a Laravel-like structure
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Models/User.php', <<<'PHP'
<?php
namespace App\Models;
class User extends Model {
    public function posts() {
        return $this->hasMany(Post::class);
    }
    public function profile() {
        return $this->hasOne(Profile::class);
    }
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['models']);
        $this->assertEquals('User', $structure['models'][0]['name']);
        $this->assertGreaterThan(0, $structure['models'][0]['relations']);
    }

    public function test_analyze_laravel_detects_controllers(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Http/Controllers/UserController.php', <<<'PHP'
<?php
namespace App\Http\Controllers;
class UserController extends Controller {
    public function index() {}
    public function store() {}
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['controllers']);
        $this->assertEquals('UserController', $structure['controllers'][0]['name']);
        $this->assertEquals(2, $structure['controllers'][0]['methods']);
    }

    public function test_analyze_laravel_detects_services(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('app/Services/PaymentService.php', <<<'PHP'
<?php
namespace App\Services;
class PaymentService {
    public function charge() {}
    protected function validate() {}
}
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertNotEmpty($structure['services']);
        $this->assertEquals('PaymentService', $structure['services'][0]['name']);
    }

    public function test_analyze_laravel_counts_migrations(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('database/migrations/001_create_users.php', '<?php // migration');
        $this->createFile('database/migrations/002_create_posts.php', '<?php // migration');

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertEquals(2, $structure['migrations']);
    }

    public function test_analyze_laravel_extracts_routes(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('routes/web.php', <<<'PHP'
<?php
Route::get('/home', [HomeController::class, 'index']);
Route::post('/login', [AuthController::class, 'login']);
PHP);

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertCount(2, $structure['routes']);
        $this->assertEquals('GET', $structure['routes'][0]['method']);
        $this->assertEquals('/home', $structure['routes'][0]['uri']);
    }

    public function test_analyze_laravel_extracts_config_keys(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('config/services.php', '<?php return [];');
        $this->createFile('config/autofix.php', '<?php return [];');
        // Framework default should be skipped
        $this->createFile('config/app.php', '<?php return [];');

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertContains('services', $structure['config_keys']);
        $this->assertContains('autofix', $structure['config_keys']);
        $this->assertNotContains('app', $structure['config_keys']);
    }

    public function test_analyze_laravel_reads_composer_json(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('composer.json', json_encode([
            'require' => [
                'php' => '^8.2',
                'laravel/framework' => '^11.0',
                'guzzlehttp/guzzle' => '^7.0',
            ],
        ]));

        $structure = $this->invokeAnalyzeLaravel($this->tempDir);

        $this->assertEquals('^8.2', $structure['php_version']);
        $this->assertEquals('^11.0', $structure['laravel_version']);
        $this->assertContains('guzzlehttp/guzzle', $structure['packages']);
    }

    // ===================================================================
    // analyzeNode
    // ===================================================================

    public function test_analyze_node_reads_package_json(): void
    {
        $this->createFile('package.json', json_encode([
            'dependencies' => ['express' => '^4.0'],
            'devDependencies' => ['jest' => '^29.0'],
        ]));

        $structure = $this->invokeAnalyzeNode($this->tempDir);

        $this->assertContains('express', $structure['npm_packages']);
        $this->assertContains('jest', $structure['npm_packages']);
    }

    public function test_analyze_node_scans_src_directory(): void
    {
        $this->createFile('package.json', json_encode(['dependencies' => []]));
        $this->createFile('src/api.js', <<<'JS'
export default function fetchData() {}
export const API_URL = '/api';
JS);

        $structure = $this->invokeAnalyzeNode($this->tempDir);

        $this->assertNotEmpty($structure['services']);
    }

    // ===================================================================
    // isLaravel / isNode detection
    // ===================================================================

    public function test_is_laravel_detects_artisan_file(): void
    {
        $this->createFile('artisan', '#!/usr/bin/env php');

        $method = new \ReflectionMethod(StructureIndexer::class, 'isLaravel');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($this->structureIndexer, $this->tempDir));
    }

    public function test_is_laravel_detects_nested_artisan(): void
    {
        $this->createFile('laravel/artisan', '#!/usr/bin/env php');

        $method = new \ReflectionMethod(StructureIndexer::class, 'isLaravel');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($this->structureIndexer, $this->tempDir));
    }

    public function test_is_node_detects_package_json(): void
    {
        $this->createFile('package.json', '{}');

        $method = new \ReflectionMethod(StructureIndexer::class, 'isNode');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($this->structureIndexer, $this->tempDir));
    }

    public function test_is_node_false_when_also_laravel(): void
    {
        // If both artisan and package.json exist, it's Laravel not Node
        $this->createFile('artisan', '#!/usr/bin/env php');
        $this->createFile('package.json', '{}');

        $method = new \ReflectionMethod(StructureIndexer::class, 'isNode');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke($this->structureIndexer, $this->tempDir));
    }

    // ===================================================================
    // indexProject
    // ===================================================================

    public function test_index_project_returns_error_for_unknown_project(): void
    {
        $result = $this->structureIndexer->indexProject('nonexistent_xyz');

        $this->assertArrayHasKey('error', $result);
    }

    // ===================================================================
    // generateSummary
    // ===================================================================

    public function test_generate_summary_includes_project_name(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('testproject', 'Laravel');
        $summary = $method->invoke($this->structureIndexer, 'testproject', $structure);

        $this->assertStringContainsString('# Structure: testproject', $summary);
        $this->assertStringContainsString('Type: Laravel', $summary);
    }

    public function test_generate_summary_includes_models(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('testproject', 'Laravel');
        $structure['models'] = [
            ['name' => 'User', 'methods' => 5, 'lines' => 100, 'extends' => 'Model', 'relations' => 3],
        ];

        $summary = $method->invoke($this->structureIndexer, 'testproject', $structure);

        $this->assertStringContainsString('## Models (1)', $summary);
        $this->assertStringContainsString('User', $summary);
    }

    public function test_generate_summary_includes_controllers(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('testproject', 'Laravel');
        $structure['controllers'] = [
            ['name' => 'UserController', 'methods' => 4, 'lines' => 80],
        ];

        $summary = $method->invoke($this->structureIndexer, 'testproject', $structure);

        $this->assertStringContainsString('## Controllers (1)', $summary);
        $this->assertStringContainsString('UserController', $summary);
    }

    public function test_generate_summary_includes_routes(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'generateSummary');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('testproject', 'Laravel');
        $structure['routes'] = [
            ['method' => 'GET', 'uri' => '/home', 'file' => 'web.php'],
        ];

        $summary = $method->invoke($this->structureIndexer, 'testproject', $structure);

        $this->assertStringContainsString('## Routes (1)', $summary);
        $this->assertStringContainsString('GET /home', $summary);
    }

    // ===================================================================
    // getCounts
    // ===================================================================

    public function test_get_counts_returns_correct_structure(): void
    {
        $method = new \ReflectionMethod(StructureIndexer::class, 'getCounts');
        $method->setAccessible(true);

        $structure = $this->emptyStructure('test', 'Laravel');
        $structure['models'] = [['name' => 'A'], ['name' => 'B']];
        $structure['controllers'] = [['name' => 'C']];
        $structure['migrations'] = 5;

        $counts = $method->invoke($this->structureIndexer, $structure);

        $this->assertEquals(2, $counts['models']);
        $this->assertEquals(1, $counts['controllers']);
        $this->assertEquals(5, $counts['migrations']);
        $this->assertEquals(0, $counts['services']);
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
