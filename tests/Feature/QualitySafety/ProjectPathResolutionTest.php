<?php

namespace Tests\Feature\QualitySafety;

use App\Services\QualitySafety\QualitySafetyScanner;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * `havun-projects.php` noemt twee paden per project: `path` (Henks
 * werkkopie, `D:/GitHub/...`) en `server_path` (de checkout op de server).
 * Elke code-check gebruikte `path`.
 *
 * Draait de nachtelijke scan op de server, dan bestaat dat pad daar niet, en
 * meldt elke check `Project path not found: D:/GitHub/...` — een error die
 * nergens gelezen werd. Gemeten 03-08-2026 op de runs van die nacht: composer
 * 14, npm 13 en cargo 13 van zulke errors, dus **nul** van de veertien
 * projecten werd op kwetsbare dependencies gecontroleerd. Precies daardoor
 * bleven 34 composer-advisories op Herdenkingsportaal dertien commits liggen
 * tot Henk ze zelf zag (25-07-2026).
 */
class ProjectPathResolutionTest extends TestCase
{
    public function test_valt_terug_op_server_path_als_het_werkpad_er_niet_is(): void
    {
        // base_path() bestaat gegarandeerd op elke machine waar deze test draait
        // en heeft een composer.json — genoeg om te zien wélk pad gekozen wordt.
        $projects = [
            'havuncore' => [
                'path' => 'D:/GitHub/EenPadDatHierNietBestaat',
                'server_path' => base_path(),
            ],
        ];

        Process::fake(['*' => Process::result(output: json_encode(['advisories' => []]), exitCode: 0)]);

        $run = (new QualitySafetyScanner)->scan($projects, ['composer']);

        $this->assertSame([], $run['errors'], 'geen "path not found" meer: het server-pad bestaat wél');
        Process::assertRan(fn ($process) => str_contains((string) $process->path, basename(base_path())));
    }

    public function test_zonder_bruikbaar_pad_blijft_het_een_error(): void
    {
        // Allebei de paden weg = er valt niets te meten, en dat hoort te blijven
        // opvallen in plaats van als "schoon" langs te komen.
        $projects = [
            'havuncore' => [
                'path' => 'D:/GitHub/EenPadDatHierNietBestaat',
                'server_path' => '/var/www/bestaat-ook-niet',
            ],
        ];

        $run = (new QualitySafetyScanner)->scan($projects, ['composer']);

        $this->assertCount(1, $run['errors']);
        $this->assertStringContainsString('niet gevonden', $run['errors'][0]['message']);
    }

    /**
     * De twee registers noemen het serverpad anders: `havun-projects.php`
     * gebruikt `server_path`, de scanlijst in `quality-safety.php` gebruikt
     * `remote_path` — en de scanner leest die laatste. Alleen op `server_path`
     * terugvallen loste dus niets op; op 03-08 bleef de scan op de server
     * gewoon veertien keer "niet gevonden" melden.
     */
    public function test_kent_ook_de_sleutel_uit_de_scanlijst(): void
    {
        $projects = [
            'havuncore' => [
                'path' => 'D:/GitHub/EenPadDatHierNietBestaat',
                'remote_path' => base_path(),
            ],
        ];

        Process::fake(['*' => Process::result(output: json_encode(['advisories' => []]), exitCode: 0)]);

        $run = (new QualitySafetyScanner)->scan($projects, ['composer']);

        $this->assertSame([], $run['errors']);
    }

    /**
     * Een project zonder checkout op déze machine én zonder serverpad hoort
     * hier niet te staan — een mobiele app, een desktop-app, een geparkeerd
     * project. Dat is "niet van toepassing", geen storing.
     *
     * Het onderscheid is niet cosmetisch: zolang de nachtelijke scan elke nacht
     * `errors: 5` meldt voor projecten die er terecht niet zijn, leer je dat
     * getal negeren — en dan is het niets meer waard op de nacht dat er wél
     * iets stukgaat. Dat er niet gemeten is blijft zichtbaar, maar dan in de
     * lijst "niet gedraaid".
     */
    public function test_project_dat_hier_niet_hoort_te_staan_is_overgeslagen(): void
    {
        $projects = [
            'studieplanner-mobile' => ['path' => 'D:/GitHub/EenPadDatHierNietBestaat'],
        ];

        $run = (new QualitySafetyScanner)->scan($projects, ['composer']);

        $this->assertSame([], $run['errors']);
        $this->assertCount(1, $run['skipped']);
        $this->assertStringContainsString('geen checkout', $run['skipped'][0]['reason']);
    }

    public function test_bestaand_werkpad_wint_van_het_serverpad(): void
    {
        // Op Henks machine is de werkkopie de bron: die heeft dev-dependencies
        // en is actueler dan wat er op de server staat.
        $projects = [
            'havuncore' => [
                'path' => base_path(),
                'server_path' => '/var/www/bestaat-niet',
            ],
        ];

        Process::fake(['*' => Process::result(output: json_encode(['advisories' => []]), exitCode: 0)]);

        $run = (new QualitySafetyScanner)->scan($projects, ['composer']);

        $this->assertSame([], $run['errors']);
    }
}
