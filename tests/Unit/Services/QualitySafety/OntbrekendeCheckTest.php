<?php

namespace Tests\Unit\Services\QualitySafety;

use App\Services\QualitySafety\MergedRunAssembler;
use App\Services\QualitySafety\VerwachteChecks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Een check die stopt met draaien verdwijnt uit het rapport, en een rapport
 * zonder die regel leest als "niets aan de hand". Dezelfde faalmodus als
 * check_supervisor had tot 06-08: nul gemeten is niet groen.
 *
 * Plan: docs/kb/plans/qv-rapportage-venster-plan.md
 */
class OntbrekendeCheckTest extends TestCase
{
    public function test_de_verwachte_checks_komen_uit_de_scheduler_zelf(): void
    {
        // Niet uit een lijst in config: die loopt uiteen met de scheduler, en
        // dan bewaakt hij de verkeerde verzameling. Voeg je een check toe aan
        // routes/console.php, dan hoort hij vanzelf verwacht te worden.
        $verwacht = (new VerwachteChecks())->all();

        $this->assertArrayHasKey('composer', $verwacht);
        $this->assertArrayHasKey('backup-coverage', $verwacht);
        $this->assertGreaterThanOrEqual(16, count($verwacht));
    }

    public function test_een_dagelijkse_check_mag_korter_wegblijven_dan_een_wekelijkse(): void
    {
        $verwacht = (new VerwachteChecks())->all();

        // composer draait dagelijks, ssl wekelijks. Dezelfde marge voor beide
        // zou of de dagelijkse te laat melden, of de wekelijkse vals alarm geven.
        $this->assertLessThan($verwacht['ssl'], $verwacht['composer']);
    }

    public function test_een_check_die_niet_gedraaid_heeft_wordt_gemeld(): void
    {
        $checks = new VerwachteChecks();

        // Alles op tijd behalve backup-coverage, die ontbreekt volledig.
        $gedraaid = [];
        foreach (array_keys($checks->all()) as $naam) {
            $gedraaid[$naam] = Carbon::now()->subHours(2)->toIso8601String();
        }
        unset($gedraaid['backup-coverage']);

        $gemist = $checks->ontbrekend($gedraaid);

        $this->assertCount(1, $gemist);
        $this->assertSame('backup-coverage', $gemist[0]['check']);
        $this->assertStringContainsString('nooit', strtolower($gemist[0]['reden']));
    }

    public function test_een_check_die_te_lang_geleden_draaide_wordt_gemeld(): void
    {
        $checks = new VerwachteChecks();

        $gedraaid = [];
        foreach (array_keys($checks->all()) as $naam) {
            $gedraaid[$naam] = Carbon::now()->subHours(2)->toIso8601String();
        }
        // Dagelijkse check die al een week stilstaat.
        $gedraaid['composer'] = Carbon::now()->subDays(7)->toIso8601String();

        $gemist = $checks->ontbrekend($gedraaid);

        $this->assertCount(1, $gemist);
        $this->assertSame('composer', $gemist[0]['check']);
        $this->assertStringContainsString('7', $gemist[0]['reden']);
    }

    public function test_alles_op_tijd_levert_geen_meldingen(): void
    {
        $checks = new VerwachteChecks();

        $gedraaid = [];
        foreach (array_keys($checks->all()) as $naam) {
            $gedraaid[$naam] = Carbon::now()->subHours(2)->toIso8601String();
        }

        $this->assertSame([], $checks->ontbrekend($gedraaid));
    }

    public function test_een_lege_uitslag_meldt_alle_checks_en_niet_niets(): void
    {
        // Het geval dat er het meest toe doet: de scheduler draait helemaal
        // niet meer. Dan is er geen enkele run, en juist dan moet het rapport
        // schreeuwen in plaats van leeg en groen te zijn.
        $gemist = (new VerwachteChecks())->ontbrekend([]);

        $this->assertGreaterThanOrEqual(16, count($gemist));
    }

    public function test_de_assembler_zet_een_ontbrekende_check_in_de_errors(): void
    {
        // Zonder deze koppeling meet VerwachteChecks wel, maar zegt het rapport
        // nog steeds niets -- en dat was het hele probleem.
        Storage::fake('qv-test');
        Storage::disk('qv-test')->put(
            'qv-scans/' . Carbon::now()->toDateString() . '/composer.json',
            json_encode([
                'started_at' => Carbon::now()->subHour()->toIso8601String(),
                'checks' => ['composer'],
                'projects' => ['havuncore'],
                'findings' => [],
                'errors' => [],
            ]),
        );

        $run = (new MergedRunAssembler())->assemble('qv-test', 'qv-scans');

        $this->assertNotNull($run);
        $redenen = implode(' ', array_column($run['errors'], 'message'));
        // composer draaide net, dus die hoort er niet bij; de rest wel.
        $this->assertStringNotContainsString('check `composer`', $redenen);
        $this->assertStringContainsString('backup-coverage', $redenen);
    }

    public function test_geen_enkele_run_geeft_een_rapport_vol_errors_in_plaats_van_niets(): void
    {
        // assemble() gaf null bij nul runs, en de aanroepers maakten daar een
        // leeg rapport van: scheduler helemaal stil las als alles in orde.
        Storage::fake('qv-leeg');

        $run = (new MergedRunAssembler())->assemble('qv-leeg', 'qv-scans');

        $this->assertNotNull($run, 'Nul runs is de ernstigste uitkomst, niet de stilste.');
        $this->assertGreaterThanOrEqual(16, count($run['errors']));
        $this->assertSame(0, $run['totals']['critical'] ?? 0);
    }
}
