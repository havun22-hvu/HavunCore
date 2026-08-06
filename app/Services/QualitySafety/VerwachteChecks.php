<?php

namespace App\Services\QualitySafety;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/**
 * Welke `qv:scan`-checks horen er te draaien, en hoe lang mag er tussen zitten?
 *
 * Een check die stopt met draaien verdwijnt uit het rapport. Het rapport wordt
 * daar niet leger van in het oog — er staat gewoon één regel minder, en dat
 * leest als "niets aan de hand". Dezelfde faalmodus als `check_supervisor` had
 * tot 06-08-2026, en als `actions:watch` daarvoor: nul gemeten is niet groen.
 *
 * **De scheduler is de bron, niet een lijst in config.** Een tweede lijst gaat
 * uiteenlopen met `routes/console.php`, en dan bewaakt hij de verkeerde
 * verzameling — precies de drift die `plans/registry-drift-check-plan.md`
 * beschrijft. Voeg je een check toe aan de scheduler, dan wordt hij hier
 * vanzelf verwacht.
 *
 * Plan: docs/kb/plans/qv-rapportage-venster-plan.md
 */
class VerwachteChecks
{
    /**
     * Speling boven op de eigen periode. Een scheduler die één keer overslaat
     * hoort geen alarm te geven; twee keer wel.
     */
    private const SPELING_UREN = 12;

    /**
     * @return array<string,int> check => maximale leeftijd in uren
     */
    public function all(): array
    {
        $verwacht = [];

        foreach (app(Schedule::class)->events() as $event) {
            $commando = (string) ($event->command ?? '');

            if (! str_contains($commando, 'qv:scan')) {
                continue;
            }

            if (preg_match('/--only=([a-z0-9-]+)/', $commando, $m) !== 1) {
                continue;
            }

            $verwacht[$m[1]] = $this->maxLeeftijdUren((string) $event->expression);
        }

        ksort($verwacht);

        return $verwacht;
    }

    /**
     * Welke checks hebben te lang niets van zich laten horen?
     *
     * @param  array<string,string>  $gedraaid  check => ISO-tijd van de laatste run
     * @return list<array{check:string,reden:string,max_uren:int}>
     */
    public function ontbrekend(array $gedraaid): array
    {
        $gemist = [];
        $nu = Carbon::now();

        foreach ($this->all() as $check => $maxUren) {
            $laatste = $gedraaid[$check] ?? null;

            if ($laatste === null || $laatste === '') {
                $gemist[] = [
                    'check' => $check,
                    'reden' => 'heeft binnen het rapportvenster nooit gedraaid',
                    'max_uren' => $maxUren,
                ];

                continue;
            }

            try {
                $uren = (int) $nu->diffInHours(Carbon::parse($laatste), true);
            } catch (\Throwable) {
                $gemist[] = [
                    'check' => $check,
                    'reden' => "laatste run heeft een onleesbare tijd: {$laatste}",
                    'max_uren' => $maxUren,
                ];

                continue;
            }

            if ($uren > $maxUren) {
                $dagen = intdiv($uren, 24);
                $gemist[] = [
                    'check' => $check,
                    'reden' => "draaide {$dagen} dagen geleden ({$uren} uur); verwacht binnen {$maxUren} uur",
                    'max_uren' => $maxUren,
                ];
            }
        }

        return $gemist;
    }

    /**
     * Uit de cron-expressie: staat er een dag-van-de-week in, dan is het een
     * wekelijkse check en mag er een week tussen zitten. Anders dagelijks.
     *
     * Grover dan de expressie echt uitrekenen, en dat is hier genoeg: het gaat
     * om "draait hij nog", niet om "draaide hij precies op tijd".
     */
    private function maxLeeftijdUren(string $expressie): int
    {
        $velden = preg_split('/\s+/', trim($expressie)) ?: [];
        $dagVanDeWeek = $velden[4] ?? '*';

        $periodeUren = ($dagVanDeWeek === '*') ? 24 : 24 * 7;

        return $periodeUren + self::SPELING_UREN;
    }
}
