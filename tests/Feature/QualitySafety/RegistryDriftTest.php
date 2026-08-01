<?php

namespace Tests\Feature\QualitySafety;

use App\Services\QualitySafety\QualitySafetyScanner;
use App\Services\QualitySafety\RegistryDriftDetector;
use Tests\TestCase;

/**
 * Een project dat nergens in de scanlijst staat, meldt niets — dat is precies
 * waarom Veen maanden ongescand draaide (gemeten 31-07-2026: eerste scan ná
 * toevoegen gaf meteen een high). Deze tests pinnen elke driftregel apart.
 */
class RegistryDriftTest extends TestCase
{
    private function detect(array $canoniek, array $qv = []): array
    {
        return (new RegistryDriftDetector)->detect($canoniek, $qv);
    }

    private function berichtenMet(array $findings, string $severity): array
    {
        return array_values(array_map(
            fn (array $f): string => $f['message'],
            array_filter($findings, fn (array $f): bool => $f['severity'] === $severity)
        ));
    }

    public function test_live_project_zonder_scanregistratie_is_high(): void
    {
        $findings = $this->detect(
            ['veen' => ['path' => 'D:/GitHub/Veen', 'server_path' => '/var/www/veen/production']],
            qv: [],
        );

        $high = $this->berichtenMet($findings, 'high');

        $this->assertCount(1, $high);
        $this->assertStringContainsString('quality-safety.php', $high[0]);
        $this->assertStringContainsString('nooit een audit', $high[0]);
    }

    public function test_project_zonder_server_pad_wordt_niet_gemeld(): void
    {
        // Een mobiele app hoort niet in de server-scan; dat is geen drift.
        $findings = $this->detect(
            ['judoscoreboard' => ['path' => 'D:/GitHub/JudoScoreBoard', 'server_path' => null]],
        );

        $this->assertSame([], $findings);
    }

    public function test_uitzondering_met_reden_wordt_informational(): void
    {
        $findings = $this->detect([
            'demo' => [
                'path' => 'D:/GitHub/Demo',
                'server_path' => '/var/www/demo/production',
                'registry_exempt' => ['qv' => 'geparkeerd, wordt weggegooid'],
            ],
        ]);

        $this->assertSame([], $this->berichtenMet($findings, 'high'));
        $this->assertCount(1, $this->berichtenMet($findings, 'informational'));
    }

    public function test_overbodige_uitzondering_wordt_gemeld(): void
    {
        // Uitgezonderd én toch geregistreerd: de reden is achterhaald en moet
        // weg, anders blijft er een verklaring staan voor iets wat niet speelt.
        $findings = $this->detect(
            ['demo' => [
                'path' => 'D:/GitHub/Demo',
                'server_path' => '/var/www/demo/production',
                'registry_exempt' => ['qv' => 'geparkeerd'],
            ]],
            qv: ['demo' => ['path' => 'D:/GitHub/Demo']],
        );

        $info = $this->berichtenMet($findings, 'informational');

        $this->assertCount(1, $info);
        $this->assertStringContainsString('overbodig', $info[0]);
    }

    public function test_lege_uitzonderingsreden_telt_niet(): void
    {
        // Anders verdwijnt een bevinding achter een leeg stringetje.
        $findings = $this->detect([
            'demo' => [
                'path' => 'D:/GitHub/Demo',
                'server_path' => '/var/www/demo/production',
                'registry_exempt' => ['qv' => '   '],
            ],
        ]);

        $this->assertCount(1, $this->berichtenMet($findings, 'high'));
    }

    public function test_wees_in_scanlijst_wordt_gemeld(): void
    {
        $findings = $this->detect(
            canoniek: [],
            qv: ['munus' => ['path' => 'D:/GitHub/Munus', 'remote_path' => '/var/www/munus/production']],
        );

        $medium = $this->berichtenMet($findings, 'medium');

        $this->assertCount(1, $medium);
        $this->assertStringContainsString("kent 'munus'", $medium[0]);
    }

    public function test_padloze_entry_is_geen_wees(): void
    {
        // `server-prod` stuurt de server-health-check aan via host+user en is
        // geen checkout; die hoort niet in havun-projects.php.
        $findings = $this->detect(
            canoniek: [],
            qv: ['server-prod' => ['enabled' => true, 'host' => '188.245.159.115', 'user' => 'root']],
        );

        $this->assertSame([], $findings);
    }

    public function test_afwijkende_sleutel_op_hetzelfde_pad_is_geen_wees(): void
    {
        // De scanlijst gebruikt soms een andere sleutel voor hetzelfde project.
        $findings = $this->detect(
            ['studieplanner' => ['path' => 'D:/GitHub/Studieplanner', 'server_path' => null]],
            qv: ['studieplanner-mobile' => ['path' => 'D:/GitHub/Studieplanner']],
        );

        $this->assertSame([], $findings);
    }

    public function test_zelfde_sleutel_ander_project_is_medium(): void
    {
        // Gemeten geval: 'studieplanner' wees naar Studieplanner in het ene
        // register en naar Studieplanner-api in het andere. De scan draait dan,
        // meldt nul, en heeft een ander project gemeten.
        $findings = $this->detect(
            ['studieplanner' => ['path' => 'D:/GitHub/Studieplanner', 'server_path' => '/var/www/studieplanner/production']],
            qv: ['studieplanner' => ['path' => 'D:/GitHub/Studieplanner-api', 'remote_path' => '/var/www/studieplanner/production']],
        );

        $medium = $this->berichtenMet($findings, 'medium');

        $this->assertCount(1, $medium);
        $this->assertStringContainsString('ander project dan de sleutel suggereert', $medium[0]);
    }

    public function test_subpad_is_geen_mismatch(): void
    {
        // JudoToernooi scant `JudoToernooi/laravel` binnen `JudoToernooi`.
        $findings = $this->detect(
            ['judotoernooi' => ['path' => 'D:/GitHub/JudoToernooi', 'server_path' => '/var/www/judotoernooi/repo-prod']],
            qv: ['judotoernooi' => ['path' => 'D:/GitHub/JudoToernooi/laravel', 'remote_path' => '/var/www/judotoernooi/repo-prod/laravel']],
        );

        $this->assertSame([], $findings);
    }

    public function test_backslashes_en_slashes_zijn_hetzelfde_pad(): void
    {
        $findings = $this->detect(
            ['aeterna' => ['path' => 'D:\\GitHub\\Aeterna', 'server_path' => '/var/www/aeterna/production/']],
            qv: ['aeterna' => ['path' => 'D:/GitHub/Aeterna/']],
        );

        $this->assertSame([], $findings);
    }

    public function test_scanner_draait_de_check_eenmaal_ongeacht_projectaantal(): void
    {
        // Binnen de per-project-loop zou dezelfde drift N keer gemeld worden,
        // en zou een project dat níét in quality-safety staat nooit aan bod
        // komen — precies het geval waar de check voor bestaat.
        config([
            'havun-projects' => ['veen' => ['path' => 'D:/GitHub/Veen', 'server_path' => '/var/www/veen/production']],
            'quality-safety.projects' => [],
        ]);

        $run = (new QualitySafetyScanner)->scan(
            ['havunadmin' => ['path' => 'D:/GitHub/HavunAdmin'], 'safehavun' => ['path' => 'D:/GitHub/SafeHavun']],
            ['registries'],
        );

        $registryFindings = array_values(array_filter($run['findings'], fn (array $f): bool => $f['check'] === 'registries'));

        $this->assertCount(1, $registryFindings, 'één high, niet per project herhaald');
        $this->assertSame('veen', $registryFindings[0]['project']);
        $this->assertSame(1, $run['totals']['high']);
    }

    public function test_de_echte_configs_kennen_geen_high_drift(): void
    {
        // Regressiebewaking op de configs zelf: op 01-08-2026 stonden `havun`,
        // `vpdupdate` en `havuncore-webapp` live op de server zonder ooit
        // gescand te zijn. Zakt er weer een live project uit de scanlijst, dan
        // faalt deze test vóór de nachtelijke scan hem zou vinden.
        $findings = $this->detect(
            (array) config('havun-projects'),
            (array) config('quality-safety.projects'),
        );

        $this->assertSame([], $this->berichtenMet($findings, 'high'));
        $this->assertSame([], $this->berichtenMet($findings, 'medium'));
    }
}
