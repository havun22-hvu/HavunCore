<?php

namespace Tests\Unit;

use App\Models\DocIntelligence\DocIssue;
use App\Services\DocIntelligence\DocIndexer;
use App\Services\DocIntelligence\IssueDetector;
use Tests\TestCase;

class DocIntelligenceTest extends TestCase
{
    private DocIndexer $indexer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexer = new DocIndexer();
    }

    // -- Cosine Similarity --

    public function test_identical_embeddings_have_perfect_similarity(): void
    {
        $embedding = ['word1' => 0.5, 'word2' => 0.3, 'word3' => 0.2];

        $similarity = $this->indexer->calculateSimilarity($embedding, $embedding);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.001);
    }

    public function test_completely_different_embeddings_have_zero_similarity(): void
    {
        $embedding1 = ['word1' => 0.5, 'word2' => 0.5];
        $embedding2 = ['word3' => 0.5, 'word4' => 0.5];

        $similarity = $this->indexer->calculateSimilarity($embedding1, $embedding2);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.001);
    }

    public function test_empty_embedding_returns_zero_similarity(): void
    {
        $this->assertEquals(0.0, $this->indexer->calculateSimilarity([], ['a' => 1.0]));
        $this->assertEquals(0.0, $this->indexer->calculateSimilarity(['a' => 1.0], []));
        $this->assertEquals(0.0, $this->indexer->calculateSimilarity([], []));
    }

    public function test_partial_overlap_gives_intermediate_similarity(): void
    {
        $embedding1 = ['word1' => 0.5, 'word2' => 0.3, 'word3' => 0.2];
        $embedding2 = ['word1' => 0.5, 'word4' => 0.3, 'word5' => 0.2];

        $similarity = $this->indexer->calculateSimilarity($embedding1, $embedding2);

        $this->assertGreaterThan(0.0, $similarity);
        $this->assertLessThan(1.0, $similarity);
    }

    // -- File Type Detection --

    public function test_detect_file_type_docs(): void
    {
        // Use reflection to access protected method
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('docs', $method->invoke($this->indexer, 'README.md'));
        $this->assertEquals('docs', $method->invoke($this->indexer, 'docs/kb/runbooks/deploy.md'));
    }

    public function test_detect_file_type_model(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('model', $method->invoke($this->indexer, 'app/Models/User.php'));
        $this->assertEquals('model', $method->invoke($this->indexer, 'laravel/app/Models/Organisator.php'));
    }

    public function test_detect_file_type_controller(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('controller', $method->invoke($this->indexer, 'app/Http/Controllers/Api/TaskController.php'));
    }

    public function test_detect_file_type_service(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('service', $method->invoke($this->indexer, 'app/Services/AIProxyService.php'));
    }

    public function test_detect_file_type_migration(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('migration', $method->invoke($this->indexer, 'database/migrations/2025_01_01_create_users.php'));
    }

    public function test_detect_file_type_view(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('view', $method->invoke($this->indexer, 'resources/views/home.blade.php'));
    }

    public function test_detect_file_type_route(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('route', $method->invoke($this->indexer, 'routes/api.php'));
    }

    public function test_detect_file_type_config(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('config', $method->invoke($this->indexer, 'config/services.php'));
    }

    public function test_detect_file_type_structure(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'detectFileType');
        $method->setAccessible(true);

        $this->assertEquals('structure', $method->invoke($this->indexer, '_structure/havuncore.structure'));
    }

    // -- Code Summary Extraction --

    public function test_extract_code_summary_for_php_class(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $code = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }
}
PHP;

        $summary = $method->invoke($this->indexer, 'app/Models/User.php', $code);

        $this->assertStringContainsString('[NAMESPACE] App\\Models', $summary);
        $this->assertStringContainsString('[CLASS] class User extends Model', $summary);
        $this->assertStringContainsString('[METHOD]', $summary);
        $this->assertStringContainsString('posts', $summary);
        $this->assertStringContainsString('findByEmail', $summary);
    }

    public function test_extract_code_summary_for_blade(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $blade = <<<'BLADE'
@extends('layouts.app')
@section('content')
    <div><!-- DO NOT REMOVE --></div>
    @include('partials.header')
@endsection
BLADE;

        $summary = $method->invoke($this->indexer, 'resources/views/home.blade.php', $blade);

        $this->assertStringContainsString('[TYPE] Blade template', $summary);
        $this->assertStringContainsString('@extends', $summary);
        $this->assertStringContainsString('DO NOT REMOVE', $summary);
    }

    public function test_extract_code_summary_for_routes(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $routes = <<<'PHP'
<?php
Route::get('/api/health', [HealthController::class, 'index']);
Route::post('/api/tasks', [TaskController::class, 'store']);
PHP;

        $summary = $method->invoke($this->indexer, 'routes/api.php', $routes);

        $this->assertStringContainsString('[TYPE] Route definitions', $summary);
        $this->assertStringContainsString('Route::get', $summary);
        $this->assertStringContainsString('Route::post', $summary);
    }

    public function test_extract_code_summary_for_migration(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'extractCodeSummary');
        $method->setAccessible(true);

        $migration = <<<'PHP'
<?php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
});
PHP;

        $summary = $method->invoke($this->indexer, 'database/migrations/2025_create_users.php', $migration);

        $this->assertStringContainsString('[TYPE] Database migration', $summary);
        $this->assertStringContainsString('[TABLE] users', $summary);
    }

    // -- Token Count Estimation --

    public function test_estimate_token_count(): void
    {
        $method = new \ReflectionMethod(DocIndexer::class, 'estimateTokenCount');
        $method->setAccessible(true);

        $content = str_repeat('a', 400); // 400 characters
        $tokens = $method->invoke($this->indexer, $content);

        $this->assertEquals(100, $tokens); // 400 / 4
    }

    // -- Project Paths --

    public function test_get_project_path_returns_path_for_known_project(): void
    {
        $path = $this->indexer->getProjectPath('havuncore');
        $this->assertNotNull($path);
    }

    public function test_get_project_path_returns_null_for_unknown_project(): void
    {
        $path = $this->indexer->getProjectPath('nonexistent_project');
        $this->assertNull($path);
    }

    public function test_get_project_paths_returns_all_projects(): void
    {
        $paths = $this->indexer->getProjectPaths();
        $this->assertIsArray($paths);
        $this->assertArrayHasKey('havuncore', $paths);
    }

    // -- IssueDetector (unit-testable methods) --

    public function test_issue_detector_normalize_price_label(): void
    {
        $detector = new IssueDetector($this->indexer);

        $method = new \ReflectionMethod(IssueDetector::class, 'normalizePriceLabel');
        $method->setAccessible(true);

        // Normal label
        $this->assertEquals('toeslag', $method->invoke($detector, 'toeslag '));

        // Filters filler words
        $this->assertEquals('toeslag', $method->invoke($detector, 'van toeslag '));

        // Returns null for too short labels
        $this->assertNull($method->invoke($detector, 'de '));

        // Returns null for non-alphabetic labels
        $this->assertNull($method->invoke($detector, '12 '));
    }

    public function test_issue_detector_is_shared_file(): void
    {
        $detector = new IssueDetector($this->indexer);

        $method = new \ReflectionMethod(IssueDetector::class, 'isSharedFile');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($detector, 'CLAUDE.md'));
        $this->assertTrue($method->invoke($detector, '.claude/commands/deploy.md'));
        $this->assertTrue($method->invoke($detector, '_structure/project.structure'));
        $this->assertFalse($method->invoke($detector, 'docs/kb/runbooks/deploy.md'));
    }

    // -- DocIssue Model --

    public function test_doc_issue_type_labels(): void
    {
        $issue = new DocIssue();

        $issue->issue_type = DocIssue::TYPE_DUPLICATE;
        $this->assertStringContainsString('Duplicate', $issue->getTypeLabel());

        $issue->issue_type = DocIssue::TYPE_OUTDATED;
        $this->assertStringContainsString('Outdated', $issue->getTypeLabel());

        $issue->issue_type = DocIssue::TYPE_BROKEN_LINK;
        $this->assertStringContainsString('Broken Link', $issue->getTypeLabel());

        $issue->issue_type = DocIssue::TYPE_INCONSISTENT;
        $this->assertStringContainsString('Inconsistent', $issue->getTypeLabel());
    }

    public function test_doc_issue_severity_labels(): void
    {
        $issue = new DocIssue();

        $issue->severity = DocIssue::SEVERITY_HIGH;
        $this->assertStringContainsString('High', $issue->getSeverityLabel());

        $issue->severity = DocIssue::SEVERITY_MEDIUM;
        $this->assertStringContainsString('Medium', $issue->getSeverityLabel());

        $issue->severity = DocIssue::SEVERITY_LOW;
        $this->assertStringContainsString('Low', $issue->getSeverityLabel());
    }
}
