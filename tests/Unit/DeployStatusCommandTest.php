<?php

namespace Tests\Unit;

use App\Console\Commands\DeployStatusCommand;
use Tests\TestCase;

/**
 * De waarde van dit commando zit niet in "meten hoeveel commits achter" — dat deed
 * het shell-blok in /end §2c ook al. Het zit in het ONDERSCHEID: een security-fix
 * moet eruit springen en een docs-achterstand moet stil blijven. Een melding die
 * altijd afgaat wordt genegeerd, en precies daardoor bleef de fix voor 34
 * composer-advisories 13 commits lang op productie liggen (25-07-2026).
 *
 * Deze tests bewaken dat onderscheid.
 */
class DeployStatusCommandTest extends TestCase
{
    private function roep(string $methode, mixed ...$args): mixed
    {
        $m = new \ReflectionMethod(DeployStatusCommand::class, $methode);
        $m->setAccessible(true);

        return $m->invoke(new DeployStatusCommand(), ...$args);
    }

    public function test_security_commits_worden_herkend(): void
    {
        $wel = [
            'security: rotate leaked henkvu login across havun.nl apps',
            'fix(security): verify the device token before creating a session',
            'chore(deps): bump laravel/framework for CVE-2026-45075',
            'fix: resolve all 34 composer advisories',
            'fix: patch vulnerability in upload handling',
        ];

        foreach ($wel as $onderwerp) {
            $this->assertTrue($this->roep('isSecurity', $onderwerp), "moet als security gelden: {$onderwerp}");
        }

        $niet = [
            'feat(gallery): let the lightbox image shrink',
            'docs: session handover 24-07',
            'refactor: split the controller',
        ];

        foreach ($niet as $onderwerp) {
            $this->assertFalse($this->roep('isSecurity', $onderwerp), "mag geen alarm geven: {$onderwerp}");
        }
    }

    public function test_docs_en_code_worden_uit_elkaar_gehouden(): void
    {
        foreach (['docs/kb/plans/x.md', '.claude/handover.md', 'CLAUDE.md', 'README.md', 'app/README.md'] as $bestand) {
            $this->assertTrue($this->roep('isDocs', $bestand), "moet als docs gelden: {$bestand}");
        }

        foreach (['app/Models/User.php', 'routes/web.php', 'resources/js/app.js', 'database/migrations/2026_01_01_x.php'] as $bestand) {
            $this->assertFalse($this->roep('isDocs', $bestand), "moet als code gelden: {$bestand}");
        }
    }

    public function test_een_batch_toont_de_code_commits_niet_de_handover_regels(): void
    {
        // Zoals Veen op 25-07: 181 commits waarvan de eerste drie docs waren,
        // terwijl er 238 codebestanden onder lagen.
        $onderwerpen = [
            'docs: coverage quality audit',
            'docs(start): a bugfix test must be seen red',
            'docs: session handover 24-07',
            'feat(staging): oude-app staging draait',
            'fix(sepa): weiger een batch zonder machtigingskenmerk',
        ];

        $resultaat = $this->roep('interessanteOnderwerpen', $onderwerpen);

        $this->assertSame('feat(staging): oude-app staging draait', $resultaat[0]);
        $this->assertCount(2, $resultaat, 'alleen de niet-docs commits horen over te blijven');
    }

    public function test_een_pure_docs_batch_valt_niet_stil(): void
    {
        // Zonder code-commits moet je alsnog iets te zien krijgen in plaats van
        // een lege lijst — anders lijkt de checkout leeg terwijl hij achterloopt.
        $onderwerpen = ['docs: handover', 'docs(kb): pattern toegevoegd'];

        $this->assertSame($onderwerpen, $this->roep('interessanteOnderwerpen', $onderwerpen));
    }

    public function test_parse_leest_checkouts_bestanden_en_migraties(): void
    {
        $output = implode("\n", [
            '###CHECKOUT|/var/www/demo/production|main|3',
            'Fapp/Models/User.php',
            'Fdocs/handover.md',
            'Fdatabase/migrations/2026_01_01_add_column.php',
            'Ssecurity: patch the thing',
            'Sfeat: add a button',
        ]);

        $resultaat = $this->roep('parse', $output);

        $this->assertCount(1, $resultaat);
        $this->assertSame('/var/www/demo/production', $resultaat[0]['pad']);
        $this->assertSame(3, $resultaat[0]['aantal']);
        $this->assertSame(2, $resultaat[0]['codeBestanden'], 'de .md hoort niet als code te tellen');
        $this->assertSame(1, $resultaat[0]['migraties']);
        $this->assertCount(1, $resultaat[0]['security']);
    }
}
