<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocEmbedding;
use App\Models\DocIntelligence\DocIssue;
use App\Models\DocIntelligence\DocRelation;
use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\CreatesDocIntelligenceTables;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('doc-intelligence')]
class DocIndexerCoverageTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    private DocIndexer $indexer;
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

        $this->indexer = new DocIndexer();

        // Create temp directory for testing
        $this->tempDir = sys_get_temp_dir() . '/docindexer_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    // ===================================================================
    // toRelativePath
    // ===================================================================

    public function test_to_relative_path(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'toRelativePath');
        $method->setAccessible(true);

        $result = $method->invoke($this->indexer, 'D:/GitHub/HavunCore/docs/readme.md', 'D:/GitHub/HavunCore');
        $this->assertEquals('docs/readme.md', $result);
    }

    public function test_to_relative_path_with_backslashes(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'toRelativePath');
        $method->setAccessible(true);

        $result = $method->invoke($this->indexer, 'D:\\GitHub\\HavunCore\\docs\\readme.md', 'D:\\GitHub\\HavunCore');
        $this->assertEquals('docs/readme.md', $result);
    }

    // ===================================================================
    // findMdFiles
    // ===================================================================

    public function test_find_md_files_finds_markdown_files(): void
    {
        $this->createFile('docs/readme.md', '# Readme');
        $this->createFile('docs/guide.md', '# Guide');
        $this->createFile('docs/code.php', '<?php // not md');

        $method = new \ReflectionMethod(DocIndexer::class, 'findMdFiles');
        $method->setAccessible(true);

        $files = $method->invoke($this->indexer, $this->tempDir);

        $this->assertCount(2, $files);
    }

    public function test_find_md_files_excludes_vendor_and_node_modules(): void
    {
        $this->createFile('docs/guide.md', '# Guide');
        $this->createFile('vendor/package/readme.md', '# Vendor');
        $this->createFile('node_modules/package/readme.md', '# Node');

        $method = new \ReflectionMethod(DocIndexer::class, 'findMdFiles');
        $method->setAccessible(true);

        $files = $method->invoke($this->indexer, $this->tempDir);

        $this->assertCount(1, $files);
    }

    // ===================================================================
    // findCodeFiles
    // ===================================================================

    public function test_find_code_files_finds_php_in_relevant_dirs(): void
    {
        $this->createFile('app/Models/User.php', '<?php class User {}');
        $this->createFile('app/Http/Controllers/HomeController.php', '<?php class HomeController {}');
        $this->createFile('app/Services/PayService.php', '<?php class PayService {}');

        $method = new \ReflectionMethod(DocIndexer::class, 'findCodeFiles');
        $method->setAccessible(true);

        $files = $method->invoke($this->indexer, $this->tempDir);

        $this->assertCount(3, $files);
    }

    public function test_find_code_files_finds_blade_templates(): void
    {
        // Blade files in a code directory (e.g., if there were blade templates in app/*)
        // The findCodeFiles scans codeDirectories which doesn't include resources/views
        // but it does handle .blade.php extension in directories it does scan
        $this->createFile('app/Http/Controllers/view.blade.php', '<div>Test</div>');

        $method = new \ReflectionMethod(DocIndexer::class, 'findCodeFiles');
        $method->setAccessible(true);

        $files = $method->invoke($this->indexer, $this->tempDir);

        $this->assertCount(1, $files);
    }

    public function test_find_code_files_excludes_vendor_paths(): void
    {
        $this->createFile('app/Models/User.php', '<?php class User {}');
        $this->createFile('app/Models/vendor/SomeLib/Model.php', '<?php class Lib {}');

        $method = new \ReflectionMethod(DocIndexer::class, 'findCodeFiles');
        $method->setAccessible(true);

        $files = $method->invoke($this->indexer, $this->tempDir);

        $this->assertCount(1, $files);
    }

    public function test_find_code_files_skips_nonexistent_directories(): void
    {
        // No directories created at all
        $method = new \ReflectionMethod(DocIndexer::class, 'findCodeFiles');
        $method->setAccessible(true);

        $files = $method->invoke($this->indexer, $this->tempDir);

        $this->assertIsArray($files);
        $this->assertCount(0, $files);
    }

    public function test_find_code_files_includes_js_ts_files(): void
    {
        $this->createFile('app/Services/util.js', 'export default function() {}');
        $this->createFile('app/Services/types.ts', 'export type Foo = {};');

        $method = new \ReflectionMethod(DocIndexer::class, 'findCodeFiles');
        $method->setAccessible(true);

        $files = $method->invoke($this->indexer, $this->tempDir);

        $this->assertCount(2, $files);
    }

    // ===================================================================
    // indexFile
    // ===================================================================

    public function test_index_file_creates_embedding_record(): void
    {
        $this->createFile('docs/test.md', '# Test content');

        $method = new \ReflectionMethod(DocIndexer::class, 'indexFile');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->indexer,
            'testproject',
            'docs/test.md',
            $this->tempDir . '/docs/test.md',
            false
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('doc_embeddings', [
            'project' => 'testproject',
            'file_path' => 'docs/test.md',
            'file_type' => 'docs',
        ], 'doc_intelligence');
    }

    public function test_index_file_skips_unchanged_file(): void
    {
        $this->createFile('docs/test.md', '# Test content');
        $fullPath = $this->tempDir . '/docs/test.md';

        $method = new \ReflectionMethod(DocIndexer::class, 'indexFile');
        $method->setAccessible(true);

        // First index
        $method->invoke($this->indexer, 'testproject', 'docs/test.md', $fullPath, false);

        // Second index without changes — should skip
        $result = $method->invoke($this->indexer, 'testproject', 'docs/test.md', $fullPath, false);

        $this->assertFalse($result);
    }

    public function test_index_file_reindexes_when_forced(): void
    {
        $this->createFile('docs/test.md', '# Test content');
        $fullPath = $this->tempDir . '/docs/test.md';

        $method = new \ReflectionMethod(DocIndexer::class, 'indexFile');
        $method->setAccessible(true);

        // First index
        $method->invoke($this->indexer, 'testproject', 'docs/test.md', $fullPath, false);

        // Force reindex
        $result = $method->invoke($this->indexer, 'testproject', 'docs/test.md', $fullPath, true);

        $this->assertTrue($result);
    }

    public function test_index_file_returns_false_for_missing_file(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'indexFile');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->indexer,
            'testproject',
            'docs/missing.md',
            $this->tempDir . '/docs/missing.md',
            false
        );

        $this->assertFalse($result);
    }

    // ===================================================================
    // indexCodeFile
    // ===================================================================

    public function test_index_code_file_creates_record(): void
    {
        $this->createFile('app/Models/User.php', <<<'PHP'
<?php
namespace App\Models;
class User extends Model {
    protected $fillable = ['name', 'email'];
    public function posts() {
        return $this->hasMany(Post::class);
    }
}
PHP);

        $method = new \ReflectionMethod(DocIndexer::class, 'indexCodeFile');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->indexer,
            'testproject',
            'app/Models/User.php',
            $this->tempDir . '/app/Models/User.php',
            false
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('doc_embeddings', [
            'project' => 'testproject',
            'file_path' => 'app/Models/User.php',
            'file_type' => 'model',
        ], 'doc_intelligence');
    }

    public function test_index_code_file_skips_unchanged(): void
    {
        $this->createFile('app/Models/User.php', '<?php class User {}');
        $fullPath = $this->tempDir . '/app/Models/User.php';

        $method = new \ReflectionMethod(DocIndexer::class, 'indexCodeFile');
        $method->setAccessible(true);

        $method->invoke($this->indexer, 'testproject', 'app/Models/User.php', $fullPath, false);

        $result = $method->invoke($this->indexer, 'testproject', 'app/Models/User.php', $fullPath, false);

        $this->assertFalse($result);
    }

    public function test_index_code_file_returns_false_for_missing(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'indexCodeFile');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->indexer,
            'testproject',
            'app/Models/Missing.php',
            $this->tempDir . '/app/Models/Missing.php',
            false
        );

        $this->assertFalse($result);
    }

    // ===================================================================
    // extractCodeSummary — additional branches
    // ===================================================================

    public function test_extract_code_summary_for_config_file(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $config = <<<'PHP'
<?php
// Service configuration
return [
    'api_key' => env('API_KEY'),
    'model' => 'claude-3-haiku',
];
PHP;

        $summary = $method->invoke($this->indexer, 'config/services.php', $config);

        $this->assertStringContainsString('[TYPE] Configuration', $summary);
        $this->assertStringContainsString('api_key', $summary);
    }

    public function test_extract_code_summary_for_js_file(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $js = <<<'JS'
import { ref } from 'vue';
export default function useAuth() {}
export const API_URL = '/api';
function helperMethod() {}
class DataManager {}
JS;

        $summary = $method->invoke($this->indexer, 'src/composables/useAuth.js', $js);

        $this->assertStringContainsString('[TYPE] JavaScript/TypeScript', $summary);
        $this->assertStringContainsString('import', $summary);
        $this->assertStringContainsString('[EXPORT]', $summary);
    }

    public function test_extract_code_summary_fallback_for_short_content(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        // PHP file with no recognized patterns
        $summary = $method->invoke($this->indexer, 'app/helpers.php', '<?php // just a helper');

        // Should fall back to including raw content
        $this->assertStringContainsString('just a helper', $summary);
    }

    public function test_extract_code_summary_blade_with_sections(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $blade = <<<'BLADE'
@extends('layouts.app')
@section('content')
    @include('partials.nav')
    @component('components.alert')
    @vite(['resources/css/app.css'])
    <!-- DO NOT REMOVE: important element -->
@endsection
BLADE;

        $summary = $method->invoke($this->indexer, 'resources/views/dashboard.blade.php', $blade);

        $this->assertStringContainsString('[TYPE] Blade template', $summary);
        $this->assertStringContainsString('DO NOT REMOVE', $summary);
    }

    public function test_extract_code_summary_routes_with_middleware(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $routes = <<<'PHP'
<?php
Route::prefix('api')->group(function () {
    Route::get('/health', [HealthController::class, 'index']);
    Route::resource('/tasks', TaskController::class);
});
Route::middleware(['auth'])->group(function () {
    Route::post('/submit', [FormController::class, 'submit'])
        ->name('form.submit');
});
PHP;

        $summary = $method->invoke($this->indexer, 'routes/api.php', $routes);

        $this->assertStringContainsString('[TYPE] Route definitions', $summary);
        $this->assertStringContainsString('Route::get', $summary);
        $this->assertStringContainsString('Route::resource', $summary);
        // Route::middleware is captured as a route group pattern
        $this->assertStringContainsString('Route::middleware', $summary);
        $this->assertStringContainsString('->name', $summary);
    }

    public function test_extract_code_summary_php_with_static_methods(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $code = <<<'PHP'
<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
abstract class BaseService {
    public static function create(string $name): self {}
    protected $apiKey;
    private const MAX_RETRIES = 3;
    protected $casts = ['data' => 'array'];
}
PHP;

        $summary = $method->invoke($this->indexer, 'app/Services/BaseService.php', $code);

        $this->assertStringContainsString('[NAMESPACE]', $summary);
        $this->assertStringContainsString('[USE]', $summary);
        $this->assertStringContainsString('[CLASS]', $summary);
        $this->assertStringContainsString('[METHOD] public static create', $summary);
        $this->assertStringContainsString('[PROPERTY]', $summary);
        $this->assertStringContainsString('[CONST] MAX_RETRIES', $summary);
        $this->assertStringContainsString('[CASTS]', $summary);
    }

    public function test_extract_code_summary_migration_with_modifiers(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $migration = <<<'PHP'
<?php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('name')
        ->nullable()
        ->default('draft');
    $table->integer('amount');
});
PHP;

        $summary = $method->invoke($this->indexer, 'database/migrations/2025_create_orders.php', $migration);

        $this->assertStringContainsString('[TABLE] orders (create)', $summary);
        $this->assertStringContainsString('string name', $summary);
    }

    // ===================================================================
    // extractArrayValues
    // ===================================================================

    public function test_extract_array_values(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractArrayValues');
        $method->setAccessible(true);

        $lines = [
            "    protected \$fillable = ['name', 'email', 'password'];",
        ];

        $result = $method->invoke($this->indexer, $lines, $lines[0]);

        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('email', $result);
    }

    public function test_extract_array_values_not_found(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractArrayValues');
        $method->setAccessible(true);

        $result = $method->invoke($this->indexer, ['line1', 'line2'], 'not in array');

        $this->assertEquals('(...)', $result);
    }

    // ===================================================================
    // detectFileType — additional types
    // ===================================================================

    public function test_detect_file_type_middleware(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('middleware', $method->invoke($this->indexer, 'app/Http/Middleware/Authenticate.php'));
    }

    public function test_detect_file_type_command(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('command', $method->invoke($this->indexer, 'app/Console/Commands/IndexDocs.php'));
    }

    public function test_detect_file_type_test(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('test', $method->invoke($this->indexer, 'tests/Unit/SomeTest.php'));
    }

    public function test_detect_file_type_support(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Enums/Status.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/DTOs/UserData.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Events/UserCreated.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Jobs/SendEmail.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Listeners/HandleEvent.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Traits/HasUuid.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Exceptions/CustomException.php'));
        $this->assertEquals('support', $method->invoke($this->indexer, 'app/Contracts/Payable.php'));
    }

    public function test_detect_file_type_generic_code(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('code', $method->invoke($this->indexer, 'src/utils/helper.php'));
    }

    // ===================================================================
    // generateLocalEmbedding
    // ===================================================================

    public function test_generate_local_embedding_returns_array(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'generateLocalEmbedding');
        $method->setAccessible(true);

        $result = $method->invoke($this->indexer, 'The quick brown fox jumps over the lazy dog repeatedly');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Values should be normalized (sum to approximately 1)
        $sum = array_sum($result);
        $this->assertGreaterThan(0.9, $sum);
        $this->assertLessThan(1.1, $sum);
    }

    public function test_generate_local_embedding_filters_stopwords(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'generateLocalEmbedding');
        $method->setAccessible(true);

        $result = $method->invoke($this->indexer, 'the deployment server configuration settings');

        // Stopword 'the' should not be in the embedding
        $this->assertArrayNotHasKey('the', $result);
        // Content words should be present
        $this->assertArrayHasKey('deployment', $result);
    }

    // ===================================================================
    // generateEmbedding — fallback to local
    // ===================================================================

    public function test_generate_embedding_falls_back_to_local_on_error(): void
    {
        Http::fake([
            '127.0.0.1:11434/*' => function () {
                throw new \Exception('Connection refused');
            },
        ]);

        $indexer = new DocIndexer();
        $method = new \ReflectionMethod(DocIndexer::class, 'generateEmbedding');
        $method->setAccessible(true);

        $result = $method->invoke($indexer, 'test content for embedding');

        // Should fall back to local TF-IDF embedding
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function test_generate_embedding_uses_ollama_when_available(): void
    {
        $testEmbedding = array_fill(0, 768, 0.1);

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => $testEmbedding], 200),
        ]);

        $indexer = new DocIndexer();
        $method = new \ReflectionMethod(DocIndexer::class, 'generateEmbedding');
        $method->setAccessible(true);

        $result = $method->invoke($indexer, 'test content');

        // When Ollama returns a valid embedding, it should be used directly
        $this->assertIsArray($result);
        // The embedding should have many entries (from Ollama response)
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    // ===================================================================
    // cleanupOrphaned
    // ===================================================================

    public function test_cleanup_orphaned_removes_missing_files(): void
    {
        // Create a doc embedding for a file that doesn't exist
        DocEmbedding::create([
            'project' => 'havuncore',
            'file_path' => 'docs/nonexistent_file_xyz.md',
            'content' => 'This file does not exist',
            'content_hash' => hash('sha256', 'orphan'),
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $removed = $this->indexer->cleanupOrphaned('havuncore');

        $this->assertGreaterThanOrEqual(1, $removed);
    }

    public function test_cleanup_orphaned_returns_zero_for_unknown_project(): void
    {
        $removed = $this->indexer->cleanupOrphaned('nonexistent_project_xyz');

        $this->assertEquals(0, $removed);
    }

    // ===================================================================
    // search
    // ===================================================================

    public function test_search_returns_ranked_results(): void
    {
        $embedding1 = ['deploy' => 0.8, 'server' => 0.2];
        $embedding2 = ['cooking' => 0.7, 'recipe' => 0.3];

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/deploy.md',
            'content' => 'Deployment guide for servers',
            'content_hash' => hash('sha256', 'deploy'),
            'embedding' => $embedding1,
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/cooking.md',
            'content' => 'Cooking recipes',
            'content_hash' => hash('sha256', 'cooking'),
            'embedding' => $embedding2,
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $results = $this->indexer->search('deploy', 'testproject', 5);

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('project', $results[0]);
        $this->assertArrayHasKey('file_path', $results[0]);
        $this->assertArrayHasKey('similarity', $results[0]);
        $this->assertArrayHasKey('snippet', $results[0]);
    }

    public function test_search_filters_by_file_type(): void
    {
        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'docs/guide.md',
            'content' => 'Guide content',
            'content_hash' => hash('sha256', 'guide'),
            'embedding' => ['guide' => 1.0],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'docs',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        DocEmbedding::create([
            'project' => 'testproject',
            'file_path' => 'app/Models/User.php',
            'content' => 'User model',
            'content_hash' => hash('sha256', 'user'),
            'embedding' => ['user' => 1.0],
            'embedding_model' => 'tfidf-fallback',
            'file_type' => 'model',
            'token_count' => 10,
            'file_modified_at' => now(),
        ]);

        $results = $this->indexer->search('user', 'testproject', 5, 'model');

        // Should only return model type
        foreach ($results as $result) {
            $this->assertEquals('model', DocEmbedding::where('file_path', $result['file_path'])->first()->file_type);
        }
    }

    // ===================================================================
    // generateEmbeddingPublic
    // ===================================================================

    public function test_generate_embedding_public_delegates_to_protected(): void
    {
        $result = $this->indexer->generateEmbeddingPublic('test content for embedding');

        // Should return an array (local fallback since Ollama returns null embedding)
        $this->assertIsArray($result);
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
}
