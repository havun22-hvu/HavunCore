<?php

namespace Tests\Unit\Services\QualitySafety;

use App\Services\QualitySafety\MergedRunAssembler;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Elke `qv:scan --only=X` schrijft een eigen run-bestand, maar de rapportage
 * las er één: het nieuwste. Op 03-08-2026 betekende dat concreet dat de
 * observatory-run van 04:37 (high 1, safehavun grade C) nergens terechtkwam --
 * `qv:log` (03:27) en `docs:handover` (04:00) lazen allebei een eerdere run --
 * en dat de acht wekelijkse checks, die alle acht ná 04:00 draaien, nog nooit
 * een finding in een gerenderd document hadden gekregen.
 *
 * Plan: docs/kb/plans/qv-rapportage-venster-plan.md
 */
class MergedRunAssemblerTest extends TestCase
{
    private function schrijfRun(string $datum, string $bestand, array $run): void
    {
        Storage::disk('local')->put("qv-scans/{$datum}/{$bestand}", json_encode($run));
    }

    private function runData(array $checks, array $findings = [], array $errors = [], string $startedAt = '2026-08-03T03:00:00+02:00'): array
    {
        $totals = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'informational' => 0, 'errors' => count($errors)];

        foreach ($findings as $f) {
            $totals[$f['severity']] = ($totals[$f['severity']] ?? 0) + 1;
        }

        return ['started_at' => $startedAt, 'checks' => $checks, 'findings' => $findings, 'errors' => $errors, 'totals' => $totals];
    }

    public function test_voegt_runs_van_verschillende_checks_samen(): void
    {
        Storage::fake('local');
        $vandaag = now()->toDateString();

        $this->schrijfRun($vandaag, 'run-032403003-1.json', $this->runData(['deps-coverage']));
        $this->schrijfRun($vandaag, 'run-043719630-2.json', $this->runData(
            ['observatory'],
            [['severity' => 'high', 'project' => 'safehavun', 'check' => 'observatory', 'message' => 'grade C']],
            [],
            '2026-08-03T04:37:19+02:00',
        ));

        $samen = (new MergedRunAssembler)->assemble();

        $this->assertSame(1, $samen['totals']['high'], 'de observatory-high mag niet wegvallen');
        $this->assertSame(['deps-coverage', 'observatory'], $samen['checks']);
        $this->assertSame('grade C', $samen['findings'][0]['message']);
    }

    public function test_errors_uit_alle_runs_tellen_mee(): void
    {
        Storage::fake('local');
        $vandaag = now()->toDateString();

        $this->schrijfRun($vandaag, 'run-030704034-1.json', $this->runData(
            ['composer'],
            [],
            [['project' => 'havunadmin', 'check' => 'composer', 'message' => 'Project path not found: D:/GitHub/HavunAdmin']],
        ));
        $this->schrijfRun($vandaag, 'run-053004798-2.json', $this->runData(
            ['backup-coverage'],
            [],
            [['project' => '_global', 'check' => 'backup-coverage', 'message' => 'Permission denied (publickey)']],
        ));

        $samen = (new MergedRunAssembler)->assemble();

        $this->assertSame(2, $samen['totals']['errors']);
        $this->assertCount(2, $samen['errors']);
    }

    public function test_per_check_wint_de_nieuwste_run(): void
    {
        // Anders duikt een finding die gisteren gefixt is vandaag weer op.
        Storage::fake('local');
        $vandaag = now()->toDateString();
        $gisteren = now()->subDay()->toDateString();

        $this->schrijfRun($gisteren, 'run-030704034-1.json', $this->runData(
            ['composer'],
            [['severity' => 'high', 'project' => 'havunadmin', 'check' => 'composer', 'message' => 'oude advisory']],
        ));
        $this->schrijfRun($vandaag, 'run-030704034-2.json', $this->runData(['composer']));

        $samen = (new MergedRunAssembler)->assemble();

        $this->assertSame(0, $samen['totals']['high'], 'de run van vandaag vervangt die van gisteren');
        $this->assertSame([], $samen['findings']);
    }

    public function test_wekelijkse_check_van_zes_dagen_oud_telt_nog_mee(): void
    {
        // ssl draait maandag 04:07. Op zondag is die uitslag zes dagen oud en
        // nog steeds het enige wat er over ssl bekend is.
        Storage::fake('local');

        $this->schrijfRun(now()->subDays(6)->toDateString(), 'run-040703516-1.json', $this->runData(
            ['ssl'],
            [['severity' => 'high', 'project' => 'havun', 'check' => 'ssl', 'message' => 'cert verloopt']],
        ));

        $samen = (new MergedRunAssembler)->assemble();

        $this->assertSame(1, $samen['totals']['high']);
    }

    public function test_buiten_het_venster_telt_niet_meer_mee(): void
    {
        Storage::fake('local');

        $this->schrijfRun(now()->subDays(30)->toDateString(), 'run-040703516-1.json', $this->runData(
            ['ssl'],
            [['severity' => 'high', 'project' => 'havun', 'check' => 'ssl', 'message' => 'cert verloopt']],
        ));

        $this->assertNull((new MergedRunAssembler)->assemble());
    }

    public function test_zonder_runs_null(): void
    {
        Storage::fake('local');

        $this->assertNull((new MergedRunAssembler)->assemble());
    }

    public function test_meldt_hoe_oud_de_uitslag_per_check_is(): void
    {
        Storage::fake('local');

        $this->schrijfRun(now()->subDays(6)->toDateString(), 'run-040703516-1.json', $this->runData(
            ['ssl'], [], [], '2026-07-28T04:07:03+02:00',
        ));

        $samen = (new MergedRunAssembler)->assemble();

        $this->assertSame('2026-07-28T04:07:03+02:00', $samen['check_runs']['ssl']);
    }
}
