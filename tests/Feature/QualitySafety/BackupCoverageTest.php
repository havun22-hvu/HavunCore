<?php

namespace Tests\Feature\QualitySafety;

use App\Services\QualitySafety\BackupCoverageDetector;
use App\Services\QualitySafety\QualitySafetyScanner;
use Tests\TestCase;

/**
 * Een backup kan op vier manieren stil falen: het bestand is er niet, het is
 * van eergisteren, het is vers en leeg, of er wordt iets bewaard dat niet meer
 * bestaat. Geen van vieren meldt zichzelf.
 *
 * Gemeten 01-08-2026 op de echte server: `infosyst` en `havunclub_production`
 * werden elke nacht leeg gedumpt terwijl beide apps 18-07 van de server af
 * waren, en van `vpdupdate` -- waar `users.json` de enige plek is waar die
 * gebruikers bestaan -- werd niets bewaard.
 */
class BackupCoverageTest extends TestCase
{
    private const DREMPELS = ['max_backup_age_hours' => 25, 'min_backup_size_bytes' => 1024];

    private function detect(array $canoniek, array $verwacht, array $uitgezonderd, array $gevonden, array $appDatabases = [], ?array $meting = null): array
    {
        return (new BackupCoverageDetector)->detect(
            $canoniek,
            $verwacht,
            $uitgezonderd,
            $gevonden,
            self::DREMPELS,
            $appDatabases,
            $meting ?? ['bron' => 'ssh', 'leeftijd_uren' => 0.0],
        );
    }

    private function berichtenMet(array $findings, string $severity): array
    {
        return array_values(array_map(
            fn (array $f): string => $f['message'],
            array_filter($findings, fn (array $f): bool => $f['severity'] === $severity)
        ));
    }

    /** @return array{leeftijd_uren:float,bytes:int} */
    private function bestand(float $uren = 6.0, int $bytes = 50_000): array
    {
        return ['leeftijd_uren' => $uren, 'bytes' => $bytes];
    }

    public function test_ontbrekende_backup_is_high(): void
    {
        $findings = $this->detect(
            ['vpdupdate' => ['server_path' => '/var/www/vpdupdate']],
            ['vpdupdate' => ['vpdupdate_users.json.gz']],
            [],
            [],
        );

        $high = $this->berichtenMet($findings, 'high');

        $this->assertCount(1, $high);
        $this->assertStringContainsString('niet aangetroffen', $high[0]);
    }

    public function test_verouderde_backup_is_high(): void
    {
        $findings = $this->detect(
            ['havuncore' => ['server_path' => '/var/www/havuncore/production']],
            ['havuncore' => ['havuncore.sql.gz']],
            [],
            ['havuncore.sql.gz' => $this->bestand(uren: 49.0)],
        );

        $high = $this->berichtenMet($findings, 'high');

        $this->assertCount(1, $high);
        $this->assertStringContainsString('draait niet meer', $high[0]);
    }

    public function test_verse_maar_lege_dump_is_medium(): void
    {
        // Het gevaarlijkste geval: in een mapweergave niet te onderscheiden van
        // een geslaagde backup. mysqldump geeft exit 0 op een lege database.
        $findings = $this->detect(
            ['infosyst' => ['server_path' => '/var/www/infosyst/production']],
            ['infosyst' => ['infosyst.sql.gz']],
            [],
            ['infosyst.sql.gz' => $this->bestand(uren: 6.0, bytes: 368)],
        );

        $medium = $this->berichtenMet($findings, 'medium');

        $this->assertCount(1, $medium);
        $this->assertStringContainsString('stil mislukt', $medium[0]);
    }

    public function test_eigen_ondergrens_per_bestand(): void
    {
        // users.json is 1,6 KB en comprimeert naar ~600 bytes. Onder de
        // SQL-drempel van 1 KB zou dat elke nacht als "lege dump" gelden — een
        // melding die altijd afgaat, en dus niet meer gelezen wordt.
        $gevonden = ['vpdupdate_users.json.gz' => $this->bestand(bytes: 614)];

        $metEigenGrens = $this->detect(
            ['vpdupdate' => ['server_path' => '/var/www/vpdupdate']],
            ['vpdupdate' => ['vpdupdate_users.json.gz' => 300]],
            [],
            $gevonden,
        );

        $metStandaardGrens = $this->detect(
            ['vpdupdate' => ['server_path' => '/var/www/vpdupdate']],
            ['vpdupdate' => ['vpdupdate_users.json.gz']],
            [],
            $gevonden,
        );

        $this->assertSame([], $metEigenGrens);
        $this->assertCount(1, $this->berichtenMet($metStandaardGrens, 'medium'));
    }

    public function test_eigen_ondergrens_vangt_alsnog_een_leeg_bestand(): void
    {
        $findings = $this->detect(
            ['vpdupdate' => ['server_path' => '/var/www/vpdupdate']],
            ['vpdupdate' => ['vpdupdate_users.json.gz' => 300]],
            [],
            ['vpdupdate_users.json.gz' => $this->bestand(bytes: 20)],
        );

        $this->assertCount(1, $this->berichtenMet($findings, 'medium'));
    }

    public function test_verse_gevulde_backup_meldt_niets(): void
    {
        $findings = $this->detect(
            ['safehavun' => ['server_path' => '/var/www/safehavun/production']],
            ['safehavun' => ['safehavun.sql.gz']],
            [],
            ['safehavun.sql.gz' => $this->bestand()],
        );

        $this->assertSame([], $findings);
    }

    public function test_live_project_zonder_verwachting_is_medium(): void
    {
        $findings = $this->detect(
            ['nieuwapp' => ['server_path' => '/var/www/nieuwapp']],
            [],
            [],
            [],
        );

        $medium = $this->berichtenMet($findings, 'medium');

        $this->assertCount(1, $medium);
        $this->assertStringContainsString('nergens wát ervan bewaard moet blijven', $medium[0]);
    }

    public function test_project_zonder_server_pad_telt_niet_mee(): void
    {
        $findings = $this->detect(
            ['judoscoreboard' => ['server_path' => null]],
            [],
            [],
            [],
        );

        $this->assertSame([], $findings);
    }

    public function test_uitzondering_met_reden_blijft_zichtbaar(): void
    {
        $findings = $this->detect(
            ['havun' => ['server_path' => '/var/www/havun.nl']],
            [],
            ['havun' => 'statische site, alles staat in git'],
            [],
        );

        $this->assertSame([], $this->berichtenMet($findings, 'high'));
        $this->assertSame([], $this->berichtenMet($findings, 'medium'));
        $this->assertCount(1, $this->berichtenMet($findings, 'informational'));
    }

    public function test_uitzondering_zonder_reden_is_medium(): void
    {
        $findings = $this->detect(
            ['havun' => ['server_path' => '/var/www/havun.nl']],
            [],
            ['havun' => '  '],
            [],
        );

        $medium = $this->berichtenMet($findings, 'medium');

        // Twee keer terecht: de uitzondering is leeg, én er staat dus nergens
        // wat er bewaard moet blijven.
        $this->assertContains('havun is uitgezonderd van backup zonder reden — een lege reden is geen keuze.', $medium);
    }

    public function test_uitzondering_voor_een_dood_project_is_achterhaald(): void
    {
        $findings = $this->detect(
            ['havunvet' => ['server_path' => null]],
            [],
            ['havunvet' => 'geen data van waarde'],
            [],
        );

        $info = $this->berichtenMet($findings, 'informational');

        $this->assertCount(1, $info);
        $this->assertStringContainsString('achterhaald', $info[0]);
    }

    public function test_backup_van_iets_dat_niemand_verwacht_is_medium(): void
    {
        $findings = $this->detect(
            [],
            ['havuncore' => ['havuncore.sql.gz']],
            [],
            [
                'havuncore.sql.gz' => $this->bestand(),
                'havunvet_staging.sql.gz' => $this->bestand(),
            ],
        );

        $medium = $this->berichtenMet($findings, 'medium');

        $this->assertCount(1, $medium);
        $this->assertStringContainsString('havunvet_staging.sql.gz', $medium[0]);
    }

    public function test_checksumbestanden_tellen_niet_als_overtollig(): void
    {
        $findings = $this->detect(
            [],
            ['havuncore' => ['havuncore.sql.gz']],
            [],
            [
                'havuncore.sql.gz' => $this->bestand(),
                'checksums.sha256' => $this->bestand(bytes: 908),
            ],
        );

        $this->assertSame([], $findings);
    }

    public function test_database_van_de_app_zonder_backup_is_high(): void
    {
        // Het geval van 15-03 t/m 27-07-2026: het script dumpte
        // `herdenkingsportaal_production` (dood restant, 47 rijen) terwijl de
        // app op `herdenkingsportaal_prod` draait (50.520 rijen). Vier maanden
        // lang elke nacht een vers, plausibel bestand van de verkeerde
        // database. Naam, versheid en omvang zagen er alle drie goed uit.
        $findings = $this->detect(
            [],
            ['herdenkingsportaal' => ['herdenkingsportaal_production.sql.gz']],
            [],
            ['herdenkingsportaal_production.sql.gz' => $this->bestand(bytes: 5_100)],
            appDatabases: ['herdenkingsportaal_prod' => '/var/www/herdenkingsportaal/production/.env'],
        );

        $high = $this->berichtenMet($findings, 'high');

        $this->assertCount(1, $high);
        $this->assertStringContainsString("'herdenkingsportaal_prod'", $high[0]);
    }

    public function test_gedekte_app_database_meldt_niets(): void
    {
        $findings = $this->detect(
            [],
            ['judotoernooi' => ['judo_toernooi.sql.gz']],
            [],
            ['judo_toernooi.sql.gz' => $this->bestand()],
            appDatabases: ['judo_toernooi' => '/var/www/judotoernooi/repo-prod/laravel/.env'],
        );

        $this->assertSame([], $findings);
    }

    public function test_zonder_env_gegevens_geen_valse_meldingen(): void
    {
        // Lukt het niet de .env's te lezen, dan is dat geen bewijs dat er iets
        // mis is — dan is er alleen niets gemeten.
        $findings = $this->detect(
            [],
            ['havuncore' => ['havuncore.sql.gz']],
            [],
            ['havuncore.sql.gz' => $this->bestand()],
            appDatabases: [],
        );

        $this->assertSame([], $findings);
    }

    /**
     * De check ging van 01-08 tot 02-08-2026 elke nacht over de meetketen zelf
     * onderuit: hij vroeg de backupmap via SSH op bij `root@`, maar op de
     * server draait hij als `www-data` en die heeft die sleutel niet. Resultaat:
     * `errors=1`, `high=0` -- en niets las dat eerste veld. Precies de
     * faalmodus die deze check moest afvangen, nu in de check zelf.
     *
     * Daarom oordeelt de detector voortaan ook over de *meting*: hoe oud is
     * hij, en is er überhaupt gemeten.
     */
    public function test_meting_die_niet_gelukt_is_is_critical(): void
    {
        $findings = $this->detect(
            [],
            ['havuncore' => ['havuncore.sql.gz']],
            [],
            ['havuncore.sql.gz' => $this->bestand()],
            meting: ['bron' => 'geen', 'leeftijd_uren' => null],
        );

        $critical = $this->berichtenMet($findings, 'critical');

        $this->assertCount(1, $critical);
        $this->assertStringContainsString('niet gemeten', $critical[0]);
    }

    public function test_verouderde_meting_is_high(): void
    {
        // Het manifest wordt na elke backuprun herschreven. Is het ouder dan
        // een etmaal, dan staat de meetketen stil en zegt de uitkomst niets
        // meer over vannacht -- ook al zien de bestanden erin er prima uit.
        $findings = $this->detect(
            [],
            ['havuncore' => ['havuncore.sql.gz']],
            [],
            ['havuncore.sql.gz' => $this->bestand()],
            meting: ['bron' => 'manifest', 'leeftijd_uren' => 40.0],
        );

        $high = $this->berichtenMet($findings, 'high');

        $this->assertCount(1, $high);
        $this->assertStringContainsString('40', $high[0]);
    }

    public function test_verse_meting_levert_geen_extra_finding(): void
    {
        $findings = $this->detect(
            [],
            ['havuncore' => ['havuncore.sql.gz']],
            [],
            ['havuncore.sql.gz' => $this->bestand()],
            meting: ['bron' => 'manifest', 'leeftijd_uren' => 2.0],
        );

        $this->assertSame([], $findings);
    }

    /**
     * De scannerkant: staat er een manifest, dan is dát de meting en gaat er
     * geen SSH meer aan te pas. Dat is de enige route die op de server werkt --
     * daar draait de scan als `www-data`, zonder root-sleutel.
     */
    public function test_scanner_meet_via_het_manifest_zonder_ssh(): void
    {
        $pad = tempnam(sys_get_temp_dir(), 'manifest-') . '.json';
        file_put_contents($pad, json_encode([
            'gemaakt_op' => time(),
            'root' => '/var/backups/havun/2026-08-03',
            'bestanden' => [
                ['naam' => 'havuncore.sql.gz', 'bytes' => 50_000, 'mtime' => time() - 7200],
            ],
            'app_databases' => ['havuncore' => '/var/www/havuncore/production/.env'],
        ]));

        config([
            'havun-backup.verificatie.manifest' => $pad,
            'havun-backup.verificatie.verwacht' => ['havuncore' => ['havuncore.sql.gz']],
            'havun-backup.verificatie.uitgezonderd' => [],
            'havun-projects' => [],
        ]);

        $run = (new QualitySafetyScanner)->scan([], ['backup-coverage']);

        unlink($pad);

        $this->assertSame([], $run['errors'] ?? [], 'geen SSH-fout: het manifest is de meting');
        $this->assertSame(0, $run['totals']['critical'] ?? 0);
        $this->assertSame(0, $run['totals']['high'] ?? 0);
    }

    /**
     * Zonder manifest én zonder werkende SSH mag de scan niet als schoon
     * langskomen. Tot 02-08-2026 deed hij dat wel: `errors=1, high=0`.
     */
    public function test_scanner_zonder_manifest_en_zonder_ssh_meldt_critical(): void
    {
        config([
            'havun-backup.verificatie.manifest' => '/pad/dat/niet/bestaat/manifest.json',
            'havun-backup.verificatie.verwacht' => ['havuncore' => ['havuncore.sql.gz']],
            'havun-backup.verificatie.uitgezonderd' => [],
            'havun-projects' => [],
            // Een host die niet bestaat: de SSH-terugval faalt gegarandeerd,
            // zonder een echte server aan te raken.
            'quality-safety.residu.host' => '127.0.0.1',
            'quality-safety.residu.user' => 'havun-bestaat-niet',
        ]);

        $run = (new QualitySafetyScanner)->scan([], ['backup-coverage']);

        $this->assertSame(1, $run['totals']['critical'] ?? 0);
        $this->assertStringContainsString(
            'niet gemeten',
            (string) ($run['findings'][0]['message'] ?? ''),
        );
    }

    public function test_de_echte_verwachting_dekt_elk_draaiend_project(): void
    {
        // Regressiebewaking op de config: elk project met een server_path hoort
        // óf een verwachting óf een uitzondering-met-reden te hebben. Komt er
        // een app bij zonder dat iemand opschrijft wat ervan bewaard moet
        // blijven, dan faalt deze test -- niet pas een incident later.
        $findings = (new BackupCoverageDetector)->detect(
            (array) config('havun-projects'),
            (array) config('havun-backup.verificatie.verwacht'),
            (array) config('havun-backup.verificatie.uitgezonderd'),
            [],                       // geen bestandslijst: alleen de config toetsen
            self::DREMPELS,
            [],
            // Wél een geslaagde meting meegeven: zonder dat stopt de detector
            // bij de meetketen en komt hij aan de config-arm niet meer toe.
            ['bron' => 'manifest', 'leeftijd_uren' => 1.0],
        );

        $zonderVerwachting = array_filter(
            $this->berichtenMet($findings, 'medium'),
            fn (string $m): bool => str_contains($m, 'nergens wát ervan bewaard moet blijven'),
        );

        $this->assertSame([], array_values($zonderVerwachting));
    }
}
