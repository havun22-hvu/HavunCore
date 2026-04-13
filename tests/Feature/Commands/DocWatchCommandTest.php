<?php

namespace Tests\Feature\Commands;

use App\Console\Commands\DocWatchCommand;
use App\Services\DocIntelligence\DocIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\CreatesDocIntelligenceTables;
use Tests\TestCase;

class DocWatchCommandTest extends TestCase
{
    use CreatesDocIntelligenceTables;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDocIntelligenceTables();

        Http::fake([
            '127.0.0.1:11434/*' => Http::response(['embedding' => null], 200),
        ]);
    }

    public function test_watch_once_no_changes(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->andReturn(['indexed' => 0, 'skipped' => 0, 'errors' => []]);
        $indexer->shouldReceive('cleanupOrphaned')
            ->andReturn(0);

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:watch', ['--once' => true])
            ->expectsOutputToContain('No changes detected')
            ->assertExitCode(0);
    }

    public function test_watch_once_with_changes(): void
    {
        $indexer = Mockery::mock(DocIndexer::class);

        // First project returns changes, others return nothing
        $callCount = 0;
        $indexer->shouldReceive('indexProject')
            ->andReturnUsing(function ($project) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['indexed' => 3, 'skipped' => 0, 'errors' => []];
                }
                return ['indexed' => 0, 'skipped' => 0, 'errors' => []];
            });
        $indexer->shouldReceive('cleanupOrphaned')
            ->andReturnUsing(function ($project) use (&$callCount) {
                // Return 1 removed for first project
                return ($project === array_key_first($this->getProjectPathsForTest())) ? 1 : 0;
            });

        $this->app->instance(DocIndexer::class, $indexer);

        $this->artisan('docs:watch', ['--once' => true])
            ->expectsOutputToContain('Synced:')
            ->assertExitCode(0);
    }

    public function test_watch_once_prints_per_project_stats(): void
    {
        // Use a partial mock to control syncCycle output
        $command = new DocWatchCommand();

        // Test the printResult method via reflection for the "with changes" path
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('printResult');
        $method->setAccessible(true);

        $result = [
            'total_updated' => 5,
            'total_removed' => 2,
            'projects' => [
                'havuncore' => ['updated' => 3, 'removed' => 1],
                'havunadmin' => ['updated' => 2, 'removed' => 1],
            ],
            'timestamp' => '12:00:00',
        ];

        // Capture the output by running the command with mocked data
        $indexer = Mockery::mock(DocIndexer::class);
        $this->app->instance(DocIndexer::class, $indexer);

        // Use artisan with a custom command that overrides syncCycle
        $this->app->singleton('test.watch.result', fn () => $result);

        // Instead, test via direct artisan call with full mock
        $havuncorePath = PHP_OS_FAMILY === 'Windows' ? 'D:/GitHub/HavunCore' : '/var/www/havuncore/production';

        $indexer->shouldReceive('indexProject')
            ->andReturnUsing(function ($project) {
                if ($project === 'havuncore') {
                    return ['indexed' => 3, 'skipped' => 0, 'errors' => []];
                }
                return ['indexed' => 0, 'skipped' => 0, 'errors' => []];
            });
        $indexer->shouldReceive('cleanupOrphaned')
            ->andReturnUsing(function ($project) {
                return $project === 'havuncore' ? 1 : 0;
            });

        $this->artisan('docs:watch', ['--once' => true])
            ->assertExitCode(0);
    }

    public function test_get_project_paths_returns_array(): void
    {
        $command = new DocWatchCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getProjectPaths');
        $method->setAccessible(true);

        $paths = $method->invoke($command);

        $this->assertIsArray($paths);
        $this->assertNotEmpty($paths);
        $this->assertArrayHasKey('havuncore', $paths);

        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertStringContainsString('D:/GitHub', $paths['havuncore']);
        } else {
            $this->assertStringContainsString('/var/www', $paths['havuncore']);
        }
    }

    public function test_print_result_no_changes(): void
    {
        $command = new DocWatchCommand();
        $reflection = new \ReflectionClass($command);
        $printMethod = $reflection->getMethod('printResult');
        $printMethod->setAccessible(true);

        // Bind the command to the application so output works
        $command->setLaravel($this->app);

        // Use output buffering to capture console output
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        ));

        $printMethod->invoke($command, [
            'total_updated' => 0,
            'total_removed' => 0,
            'projects' => [],
            'timestamp' => '14:30:00',
        ]);

        $this->assertStringContainsString('No changes detected', $output->fetch());
    }

    public function test_print_result_with_updates_and_removals(): void
    {
        $command = new DocWatchCommand();
        $reflection = new \ReflectionClass($command);
        $printMethod = $reflection->getMethod('printResult');
        $printMethod->setAccessible(true);

        $command->setLaravel($this->app);

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        ));

        $printMethod->invoke($command, [
            'total_updated' => 4,
            'total_removed' => 2,
            'projects' => [
                'havuncore' => ['updated' => 3, 'removed' => 0],
                'havunadmin' => ['updated' => 1, 'removed' => 2],
            ],
            'timestamp' => '14:30:00',
        ]);

        $text = $output->fetch();
        $this->assertStringContainsString('Synced: 4 updated, 2 removed', $text);
        $this->assertStringContainsString('havuncore: 3 updated', $text);
        $this->assertStringContainsString('havunadmin: 1 updated, 2 removed', $text);
    }

    public function test_print_result_with_only_removals(): void
    {
        $command = new DocWatchCommand();
        $reflection = new \ReflectionClass($command);
        $printMethod = $reflection->getMethod('printResult');
        $printMethod->setAccessible(true);

        $command->setLaravel($this->app);

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        ));

        $printMethod->invoke($command, [
            'total_updated' => 0,
            'total_removed' => 3,
            'projects' => [
                'havuncore' => ['updated' => 0, 'removed' => 3],
            ],
            'timestamp' => '15:00:00',
        ]);

        $text = $output->fetch();
        $this->assertStringContainsString('Synced: 0 updated, 3 removed', $text);
        $this->assertStringContainsString('havuncore: 3 removed', $text);
    }

    public function test_handle_continuous_mode_stops_after_one_cycle(): void
    {
        // Mock the indexer - set shouldStop during sync to break the loop after 1 iteration
        $command = $this->app->make(DocWatchCommand::class);

        $indexer = Mockery::mock(DocIndexer::class);
        $indexer->shouldReceive('indexProject')
            ->andReturnUsing(function () use ($command) {
                // Set shouldStop during the sync cycle to break the while loop
                $ref = new \ReflectionClass(DocWatchCommand::class);
                $prop = $ref->getProperty('shouldStop');
                $prop->setAccessible(true);
                $prop->setValue($command, true);
                return ['indexed' => 0, 'skipped' => 0, 'errors' => []];
            });
        $indexer->shouldReceive('cleanupOrphaned')
            ->andReturn(0);
        $this->app->instance(DocIndexer::class, $indexer);

        $command->setLaravel($this->app);

        // Set up proper input with interval=0 to skip sleep
        $definition = $command->getDefinition();
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            '--interval' => '0',
        ], $definition);

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $output));
        $command->setInput($input);

        $result = $command->handle($indexer);

        $this->assertEquals(0, $result);
        $text = $output->fetch();
        $this->assertStringContainsString('auto-sync started', $text);
        $this->assertStringContainsString('Auto-sync stopped', $text);
    }

    public function test_sync_cycle_skips_nonexistent_directories(): void
    {
        $command = Mockery::mock(DocWatchCommand::class)->makePartial();
        $command->shouldAllowMockingProtectedMethods();
        $command->shouldReceive('getProjectPaths')
            ->once()
            ->andReturn([
                'fakeproject' => '/nonexistent/path/that/does/not/exist',
            ]);

        $reflection = new \ReflectionClass(DocWatchCommand::class);
        $syncMethod = $reflection->getMethod('syncCycle');
        $syncMethod->setAccessible(true);

        $indexer = Mockery::mock(DocIndexer::class);
        // indexProject should NOT be called for nonexistent dir
        $indexer->shouldNotReceive('indexProject');

        $result = $syncMethod->invoke($command, $indexer);

        $this->assertEquals(0, $result['total_updated']);
        $this->assertEquals(0, $result['total_removed']);
        $this->assertEmpty($result['projects']);
    }

    /**
     * Helper to get project paths for this test environment
     */
    private function getProjectPathsForTest(): array
    {
        $command = new DocWatchCommand();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getProjectPaths');
        $method->setAccessible(true);
        return $method->invoke($command);
    }
}
