<?php

namespace App\Services\QualitySafety;

/**
 * Vergelijkt het canonieke projectregister met de scanlijst.
 *
 * `havun-projects.php` is het overzicht van alles wat er is; `quality-safety.php`
 * bepaalt wat er gescand wordt. Ze lopen structureel uit de pas: drie keer in
 * twee dagen (29–31 juli 2026) stond een project wél in het ene en niet in het
 * andere — `vusista`, `vusista2` en Veen. Veen draaide daardoor maanden zonder
 * één `composer audit`; de eerste scan ná toevoegen gaf meteen een high.
 *
 * Waarom niemand het merkte: afwezigheid is stil. Elke andere V&K-check meldt
 * iets over wat hij vindt, maar een project dat niet in de lijst staat
 * produceert per definitie geen regel. Deze detector maakt het gat zelf de
 * bevinding — en vond op zijn eerste run drie live projecten die nooit gescand
 * waren: `havun`, `vpdupdate` en `havuncore-webapp`.
 *
 * ⚠️ Backup zit hier bewust NIET in. `config/havun-backup.php` leest niemand:
 * gemeten 01-08-2026 is er geen command of scheduler-regel die het gebruikt, en
 * de echte backups draaien uit `/usr/local/bin/havun-backup.sh` (cron 03:00) +
 * `havun-hotbackup.sh`. Een vergelijking met die dode config meldde vijf
 * projecten als "geen backup" terwijl het shellscript er twee wél meenam. Een
 * melding die altijd afgaat en niets betekent, is erger dan geen melding.
 * Backupdekking meten hoort tegen het script te gebeuren — open punt in het plan.
 *
 * Pure functie op arrays — geen filesystem, geen SSH — zodat elke regel apart
 * te testen is. Plan: docs/kb/plans/registry-drift-check-plan.md.
 */
class RegistryDriftDetector
{
    private const SCANLIJST = 'quality-safety.php';

    /**
     * @param  array<string,array<string,mixed>>  $canoniek  config('havun-projects')
     * @param  array<string,array<string,mixed>>  $qv        config('quality-safety.projects')
     * @return array<int,array<string,mixed>>
     */
    public function detect(array $canoniek, array $qv): array
    {
        $findings = [];

        foreach ($canoniek as $slug => $project) {
            $findings = array_merge($findings, $this->ontbrekendeRegistratie($slug, $project, $qv));
        }

        return array_merge(
            $findings,
            $this->wezen($qv, $canoniek),
            $this->padMismatches($canoniek, $qv),
        );
    }

    /**
     * Een project dat op de server draait maar niet in de scanlijst staat,
     * wordt nooit doorgemeten. Alleen live projecten tellen: een mobiele app
     * zonder `server_path` hoort er terecht niet in.
     *
     * @param  array<string,mixed>               $project
     * @param  array<string,array<string,mixed>> $qv
     * @return array<int,array<string,mixed>>
     */
    private function ontbrekendeRegistratie(string $slug, array $project, array $qv): array
    {
        if (empty($project['server_path'])) {
            return [];
        }

        $reden = $this->uitzonderingsreden($project, 'qv');

        if ($this->staatIn($slug, $project, $qv)) {
            // Uitgezonderd én toch geregistreerd: de reden is achterhaald.
            return $reden === null ? [] : [$this->finding(
                'informational',
                $slug,
                'Uitzondering op ' . self::SCANLIJST . " is overbodig — {$slug} staat er wél in ({$reden}).",
            )];
        }

        if ($reden !== null) {
            return [$this->finding(
                'informational',
                $slug,
                "{$slug} staat bewust niet in " . self::SCANLIJST . ": {$reden}",
            )];
        }

        return [$this->finding(
            'high',
            $slug,
            "{$slug} draait op {$project['server_path']} maar staat niet in " . self::SCANLIJST . ' — er is nooit een audit of secrets-scan op gedraaid.',
        )];
    }

    /**
     * Een sleutel in de scanlijst die het canonieke register niet kent:
     * verwijderd project of een typefout in de sleutel. Beide laten een scan
     * draaien op een pad dat niemand meer bijhoudt.
     *
     * @param  array<string,array<string,mixed>> $register
     * @param  array<string,array<string,mixed>> $canoniek
     * @return array<int,array<string,mixed>>
     */
    private function wezen(array $register, array $canoniek): array
    {
        $findings = [];

        foreach ($register as $slug => $entry) {
            // Een entry zonder enig pad verwijst niet naar een checkout maar
            // stuurt een check aan (`server-prod` draait de server-health via
            // host+user). Die hoort niet in het canonieke projectregister.
            if ($this->padenVan($entry) === []) {
                continue;
            }

            if (isset($canoniek[$slug]) || $this->hoortBijCanoniekPad($entry, $canoniek)) {
                continue;
            }

            $findings[] = $this->finding(
                'medium',
                $slug,
                self::SCANLIJST . " kent '{$slug}', maar havun-projects.php niet — verwijderd project of een typefout in de sleutel.",
            );
        }

        return $findings;
    }

    /**
     * Dezelfde sleutel die in beide registers naar een ander project wijst, is
     * gevaarlijker dan een ontbrekende regel: de scan draait, meldt netjes
     * nul, en heeft iets ánders gemeten dan je denkt. Gemeten geval:
     * `studieplanner` = `D:/GitHub/Studieplanner` in het ene register en
     * `D:/GitHub/Studieplanner-api` in het andere.
     *
     * @param  array<string,array<string,mixed>> $canoniek
     * @param  array<string,array<string,mixed>> $qv
     * @return array<int,array<string,mixed>>
     */
    private function padMismatches(array $canoniek, array $qv): array
    {
        $findings = [];

        foreach ($qv as $slug => $entry) {
            if (! isset($canoniek[$slug])) {
                continue; // al gemeld als wees
            }

            $links = $this->normaliseer($canoniek[$slug]['path'] ?? null);
            $rechts = $this->normaliseer($entry['path'] ?? null);

            if ($links === null || $rechts === null || $links === $rechts) {
                continue;
            }

            // Een subpad is legitiem: JudoToernooi scant `JudoToernooi/laravel`
            // binnen `JudoToernooi`. Alleen echt uiteenlopende bomen melden.
            if (str_starts_with($rechts, $links . '/') || str_starts_with($links, $rechts . '/')) {
                continue;
            }

            $findings[] = $this->finding(
                'medium',
                $slug,
                "'{$slug}' wijst in havun-projects.php naar {$canoniek[$slug]['path']} en in " . self::SCANLIJST . " naar {$entry['path']} — de scan meet een ander project dan de sleutel suggereert.",
            );
        }

        return $findings;
    }

    /**
     * Registratie op sleutel óf op pad: de scanlijst gebruikt soms een andere
     * sleutel voor hetzelfde project (`studieplanner-mobile`), en dan is het
     * wél geregistreerd.
     *
     * @param  array<string,mixed>               $project
     * @param  array<string,array<string,mixed>> $register
     */
    private function staatIn(string $slug, array $project, array $register): bool
    {
        if (isset($register[$slug])) {
            return true;
        }

        $serverPad = $this->normaliseer($project['server_path'] ?? null);

        if ($serverPad === null) {
            return false;
        }

        foreach ($register as $entry) {
            foreach ($this->padenVan($entry) as $pad) {
                if ($pad === $serverPad) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string,mixed>               $entry
     * @param  array<string,array<string,mixed>> $canoniek
     */
    private function hoortBijCanoniekPad(array $entry, array $canoniek): bool
    {
        $paden = $this->padenVan($entry);

        if ($paden === []) {
            return false;
        }

        foreach ($canoniek as $project) {
            foreach (['path', 'server_path'] as $veld) {
                $kandidaat = $this->normaliseer($project[$veld] ?? null);

                if ($kandidaat !== null && in_array($kandidaat, $paden, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Elk pad waarmee een scanlijst-entry naar een checkout wijst — lokaal
     * (`path`) en op de server (`remote_path`).
     *
     * @param  array<string,mixed> $entry
     * @return array<int,string>
     */
    private function padenVan(array $entry): array
    {
        $ruw = [
            $entry['path'] ?? null,
            $entry['remote_path'] ?? null,
        ];

        return array_values(array_filter(array_map(fn ($p) => $this->normaliseer($p), $ruw)));
    }

    /**
     * Een lege reden telt niet als uitzondering — anders verdwijnt een
     * bevinding met een leeg stringetje uit beeld.
     *
     * @param  array<string,mixed> $project
     */
    private function uitzonderingsreden(array $project, string $register): ?string
    {
        $reden = $project['registry_exempt'][$register] ?? null;

        if (! is_string($reden) || trim($reden) === '') {
            return null;
        }

        return trim($reden);
    }

    private function normaliseer(?string $pad): ?string
    {
        if ($pad === null || trim($pad) === '') {
            return null;
        }

        return rtrim(str_replace('\\', '/', trim($pad)), '/');
    }

    /**
     * @return array<string,mixed>
     */
    private function finding(string $severity, string $slug, string $message): array
    {
        return [
            'severity' => $severity,
            'title' => "Registry-drift: {$slug}",
            'config' => self::SCANLIJST,
            'slug' => $slug,
            'message' => $message,
        ];
    }
}
