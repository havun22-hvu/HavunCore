<?php

namespace Tests\Feature\DocIntelligence;

use App\Services\DocIntelligence\DocIndexer;
use PHPUnit\Framework\Attributes\Group;
use ReflectionProperty;
use Tests\TestCase;

/**
 * The indexer must take its projects from config/havun-projects.php and nowhere else.
 *
 * It used to carry its own hardcoded $localPaths/$serverPaths next to that config, and the
 * two drifted: JudoScoreBoard (priority 1), Aeterna and LastMatch were missing from the
 * index until 15-07-2026, so 190 docs were unfindable while CLAUDE.md tells every session
 * to start with docs:search. That search silently returned nothing.
 */
#[Group('doc-intelligence')]
class ProjectPathsFromConfigTest extends TestCase
{
    private function projectPaths(): array
    {
        $indexer = new DocIndexer();
        $prop = new ReflectionProperty($indexer, 'projectPaths');
        $prop->setAccessible(true);

        return $prop->getValue($indexer);
    }

    public function test_every_configured_project_with_a_local_path_is_indexable(): void
    {
        $verwacht = collect(config('havun-projects'))
            ->filter(fn ($p) => ! empty($p['path']))
            ->keys()
            ->all();

        $this->assertNotEmpty($verwacht, 'config/havun-projects.php is leeg');
        $this->assertEqualsCanonicalizing($verwacht, array_keys($this->projectPaths()));
    }

    /** The projects that went missing for months. */
    public function test_the_projects_that_were_forgotten_are_present(): void
    {
        $paden = $this->projectPaths();

        foreach (['judoscoreboard', 'aeterna', 'lastmatch'] as $project) {
            $this->assertArrayHasKey($project, $paden, "{$project} ontbreekt in de index-projectlijst");
        }
    }

    public function test_there_is_no_second_hardcoded_project_list(): void
    {
        $bron = file_get_contents(app_path('Services/DocIntelligence/DocIndexer.php'));

        $this->assertStringNotContainsString('$localPaths = [', $bron, 'Tweede projectlijst terug in DocIndexer');
        $this->assertStringNotContainsString('$serverPaths = [', $bron, 'Tweede projectlijst terug in DocIndexer');
        $this->assertStringContainsString("config('havun-projects'", $bron);
    }

    /**
     * A server path must point at the actual git checkout. JudoToernooi's entry used to point
     * at /var/www/judotoernooi/laravel, which is a symlink without a .git of its own.
     */
    public function test_server_paths_are_absolute_and_judotoernooi_points_at_the_checkout(): void
    {
        foreach (config('havun-projects') as $naam => $project) {
            if (empty($project['server_path'])) {
                continue;
            }
            $this->assertStringStartsWith('/var/www/', $project['server_path'], "{$naam} heeft geen absoluut serverpad");
        }

        $this->assertSame(
            '/var/www/judotoernooi/repo-prod',
            config('havun-projects.judotoernooi.server_path'),
            'JudoToernooi moet naar repo-prod wijzen, niet naar de laravel-symlink'
        );
    }
}
