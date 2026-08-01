<?php

namespace Tests\Unit\Services\DocsAudit;

use App\Services\DocsAudit\DocsAuditor;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Vier soorten valse meldingen, allemaal gemeten op 01-08-2026.
 *
 * Het rapport van die dag had 39 findings — 1 critical en 29 high — waarvan er
 * 30 vals waren. Zoveel ruis maakt de echte bevindingen onvindbaar, en een
 * rapport dat je niet vertrouwt lees je niet meer. Deze tests houden de vier
 * filters op hun plaats, én bewaken dat een échte fout nog steeds afgaat.
 */
class RuisFilterTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/docs-ruis-' . uniqid();
        File::makeDirectory($this->tmp . '/docs/decisions', 0755, true);
        File::makeDirectory($this->tmp . '/docs/runbooks', 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function details(string $severity = 'high'): array
    {
        $result = (new DocsAuditor)->audit([$this->tmp . '/docs'], $this->tmp);

        return array_values(array_map(
            fn (array $f): string => $f['detail'],
            array_filter($result['findings'], fn (array $f): bool => $f['severity'] === $severity),
        ));
    }

    public function test_crlf_frontmatter_telt_gewoon_als_frontmatter(): void
    {
        // 20 van de 29 high-findings kwamen hier vandaan: bestanden met een
        // keurig ---blok, alleen op Windows opgeslagen.
        File::put(
            $this->tmp . '/docs/runbooks/windows.md',
            "---\r\ntitle: Windows\r\ntype: runbook\r\nscope: havuncore\r\n---\r\n\r\n# Windows\r\n\r\nTekst.\r\n"
        );

        $this->assertNotContains('Ontbrekende frontmatter', $this->details());
    }

    public function test_een_adr_wordt_niet_op_zombies_gecontroleerd(): void
    {
        // Een ADR beschrijft een besluit van toen. Eentje óver verwijdering
        // noemt per definitie klassen die niet meer bestaan.
        File::put(
            $this->tmp . '/docs/decisions/007-verwijdering.md',
            "---\ntitle: Verwijdering\ntype: reference\nscope: havuncore\n---\n\n"
            . "# Verwijderd\n\nWeg: `App\\Services\\WegService` en `php artisan weg:nu`.\n"
        );

        $this->assertSame([], $this->details());
    }

    public function test_een_link_in_backticks_is_een_voorbeeld_geen_link(): void
    {
        // De checker meldde een critical op de regel die uitlegt hóé hij
        // false-positives voorkomt — het voorbeeld stond in backticks.
        File::put(
            $this->tmp . '/docs/runbooks/uitleg.md',
            "---\ntitle: Uitleg\ntype: runbook\nscope: havuncore\n---\n\n"
            . "# Uitleg\n\nEen anchor wordt gestript: `[x](./README.md#sectie)` is geen dode link.\n"
        );

        $this->assertSame([], $this->details('critical'));
    }

    public function test_rust_static_call_is_geen_ontbrekende_php_class(): void
    {
        File::put(
            $this->tmp . '/docs/runbooks/rust.md',
            "---\ntitle: Rust\ntype: runbook\nscope: havuncore\n---\n\n"
            . "# Reproduceerbaar bouwen\n\nDraai `cargo build`; vermijd `SystemTime::now()` in de source.\n"
        );

        $this->assertSame([], $this->details());
    }

    public function test_een_echte_zombie_gaat_nog_steeds_af(): void
    {
        // De filters mogen de detector niet doof maken. Deze staat bewust in
        // runbooks/ en niet in decisions/, en noemt geen andere stack.
        File::put(
            $this->tmp . '/docs/runbooks/kapot.md',
            "---\ntitle: Kapot\ntype: runbook\nscope: havuncore\n---\n\n"
            . "# Kapot\n\nZie `App\\Services\\BestaatNietService` en `php artisan doet:niks`.\n\n"
            . "En [dode link](./bestaat-niet.md).\n"
        );

        $high = $this->details();

        $this->assertContains('Class-ref bestaat niet: `App\Services\BestaatNietService`', $high);
        $this->assertContains('Artisan command bestaat niet: `php artisan doet:niks`', $high);
        $this->assertNotSame([], $this->details('critical'), 'de dode link hoort een critical te geven');
    }
}
