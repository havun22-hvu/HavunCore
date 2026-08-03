<?php

namespace App\Services\QualitySafety;

/**
 * Toetst of er vannacht daadwerkelijk een backup gemaakt is van wat elk project
 * nodig heeft.
 *
 * Meet bewust de **uitkomst**, niet de intentie. `config/havun-backup.php` gaf
 * tot 01-08-2026 een lijst van vier projecten die door niets gelezen werd,
 * terwijl het serverscript er acht deed — inclusief `infosyst` en
 * `havunclub_production`, twee databases van apps die 18-07 van de server af
 * gingen en sindsdien elke nacht leeg gedumpt worden. Een register dat niemand
 * uitvoert, meldt niets als het fout staat.
 *
 * Vier dingen kunnen misgaan, en alle vier zijn stil:
 *  - het bestand is er niet (nooit gemaakt, of de dump faalde)
 *  - het is er wel maar van eergisteren (script draait niet meer)
 *  - het is er, vers, en leeg (dump faalde stil — mysqldump geeft exit 0 op een
 *    verdwenen database)
 *  - er wordt iets geback-upt dat niet meer bestaat (ruis die de map laat groeien)
 *
 * Pure functie op arrays: de SSH-uitvraag doet de scanner, hier komt alleen het
 * resultaat binnen. Plan: docs/kb/plans/registry-drift-check-plan.md.
 */
class BackupCoverageDetector
{
    /**
     * @param  array<string,array<string,mixed>>  $canoniek   config('havun-projects')
     * @param  array<string,array<int,string>>    $verwacht   config('havun-backup.verificatie.verwacht')
     * @param  array<string,string>               $uitgezonderd config('havun-backup.verificatie.uitgezonderd')
     * @param  array<string,array{leeftijd_uren:float,bytes:int}> $gevonden  bestandsnaam => meting
     * @param  array<string,mixed>                $drempels   config('havun-backup.monitoring')
     * @param  array<string,string>               $appDatabases  databasenaam => .env-pad
     * @param  float|null $metingLeeftijdUren  ouderdom van de meting; null = niet gemeten
     * @return array<int,array<string,mixed>>
     */
    public function detect(
        array $canoniek,
        array $verwacht,
        array $uitgezonderd,
        array $gevonden,
        array $drempels,
        array $appDatabases,
        ?float $metingLeeftijdUren,
    ): array {
        $maxUren = (float) ($drempels['max_backup_age_hours'] ?? 25);
        $minBytes = (int) ($drempels['min_backup_size_bytes'] ?? 1024);
        $maxMetingUren = (float) ($drempels['max_meting_age_hours'] ?? 26);

        // Is er niets gemeten, dan zegt élke uitspraak hieronder niets. Doorgaan
        // zou een lijst opleveren die niet van een echte meting te onderscheiden
        // is -- exact de fout die deze check moest afvangen.
        if ($metingLeeftijdUren === null) {
            return [$this->nietGemeten()];
        }

        return array_merge(
            $this->metingVerouderd($metingLeeftijdUren, $maxMetingUren),
            $this->perProject($verwacht, $gevonden, $maxUren, $minBytes),
            $this->zonderVerwachting($canoniek, $verwacht, $uitgezonderd),
            $this->uitzonderingen($canoniek, $uitgezonderd),
            $this->overtolligeBackups($verwacht, $gevonden),
            $this->appDatabasesGedekt($appDatabases, $verwacht),
        );
    }

    /**
     * Er is niets gemeten — de enige finding die de detector dan afgeeft.
     *
     * Van 01-08 tot 02-08-2026 draaide deze check elke nacht op de server en
     * mat hij niets: hij vroeg de backupmap via SSH op bij `root@` — de server
     * dus, vanaf de server. Die heeft geen sleutel naar zichzelf (en hoort die
     * ook niet te hebben). Uitkomst: `errors=1, high=0`, en niets las dat eerste
     * veld. Een bewaking die stil omvalt ziet er identiek uit aan een bewaking
     * die niets te melden heeft.
     *
     * @return array<string,mixed>
     */
    public function nietGemeten(): array
    {
        return $this->finding(
            'critical',
            '_meting',
            'De backupdekking is niet gemeten: er kwam geen bruikbaar manifest binnen. Alles wat hier normaal onder staat zou verzonnen zijn, dus er staat niets onder. Dit is dezelfde stille faalmodus die de check moest afvangen.',
        );
    }

    /**
     * Het manifest wordt na elke backuprun herschreven. Is het oud, dan staat
     * de meetketen stil en zegt de inhoud niets meer over vannacht — ook al
     * zien de bestanden erin er prima uit.
     *
     * @return array<int,array<string,mixed>>
     */
    private function metingVerouderd(float $leeftijd, float $maxMetingUren): array
    {
        if ($leeftijd <= $maxMetingUren) {
            return [];
        }

        return [$this->finding(
            'high',
            '_meting',
            sprintf(
                'De meting is %.1f uur oud (grens %.0f) — het backupmanifest wordt na elke run herschreven, dus de meetketen staat stil. Wat hieronder staat gaat over een eerdere nacht.',
                $leeftijd,
                $maxMetingUren,
            ),
        )];
    }

    /**
     * Wordt de database die de app zélf zegt te gebruiken ook geback-upt?
     *
     * Dit is de regel die het geval van 15 maart t/m 27 juli 2026 vangt: het
     * backupscript dumpte `herdenkingsportaal_production` — een dood restant
     * van 47 rijen — terwijl de app op `herdenkingsportaal_prod` draait, met
     * 50.520 rijen. Vier maanden lang stond er elke nacht een keurig bestand
     * van de verkeerde database.
     *
     * Geen enkele controle op naam, versheid of omvang ziet dat: het bestand
     * bestond, was vers, en had een plausibele naam. Alleen de `.env` van de
     * app weet welke database de echte is.
     *
     * @param  array<string,string>              $appDatabases
     * @param  array<string,array<int|string,string|int>> $verwacht
     * @return array<int,array<string,mixed>>
     */
    private function appDatabasesGedekt(array $appDatabases, array $verwacht): array
    {
        if ($appDatabases === []) {
            return [];
        }

        $bestanden = [];

        foreach ($verwacht as $lijst) {
            foreach ($lijst as $sleutel => $waarde) {
                $bestanden[] = is_int($sleutel) ? (string) $waarde : $sleutel;
            }
        }

        $findings = [];

        foreach ($appDatabases as $database => $envPad) {
            if (in_array("{$database}.sql.gz", $bestanden, true)) {
                continue;
            }

            $findings[] = $this->finding(
                'high',
                $database,
                "De app op {$envPad} draait op database '{$database}', maar daarvan wordt geen backup verwacht. Er kan wél een dump van een gelijkende naam bestaan — dat is precies hoe Herdenkingsportaal vier maanden de verkeerde database bewaarde.",
            );
        }

        return $findings;
    }

    /**
     * @param  array<string,array<int,string>> $verwacht
     * @param  array<string,array{leeftijd_uren:float,bytes:int}> $gevonden
     * @return array<int,array<string,mixed>>
     */
    private function perProject(array $verwacht, array $gevonden, float $maxUren, int $minBytes): array
    {
        $findings = [];

        foreach ($verwacht as $slug => $bestanden) {
            foreach ($this->metDrempel($bestanden, $minBytes) as $bestand => $ondergrens) {
                if (! isset($gevonden[$bestand])) {
                    $findings[] = $this->finding(
                        'high',
                        $slug,
                        "Geen backup van {$bestand} — verwacht in de nachtelijke run, niet aangetroffen.",
                    );

                    continue;
                }

                $meting = $gevonden[$bestand];

                if ($meting['leeftijd_uren'] > $maxUren) {
                    $findings[] = $this->finding(
                        'high',
                        $slug,
                        sprintf(
                            '%s is %.1f uur oud (grens %.0f) — de nachtelijke backup draait niet meer.',
                            $bestand,
                            $meting['leeftijd_uren'],
                            $maxUren,
                        ),
                    );

                    continue;
                }

                // Vers én leeg is het gevaarlijkste geval: het ziet er in elke
                // mapweergave uit als een geslaagde backup.
                if ($meting['bytes'] < $ondergrens) {
                    $findings[] = $this->finding(
                        'medium',
                        $slug,
                        sprintf(
                            '%s is vers maar %d bytes (grens %d) — de dump is stil mislukt, of de database is leeg.',
                            $bestand,
                            $meting['bytes'],
                            $ondergrens,
                        ),
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * Een artefact mag legitiem klein zijn: `users.json` comprimeert naar ~600
     * bytes en zou onder de SQL-drempel elke nacht als lege dump gelden. Daarom
     * mag een verwachting per bestand een eigen ondergrens meegeven — als
     * sleutel=>waarde in plaats van alleen een naam.
     *
     * @param  array<int|string,string|int> $bestanden
     * @return array<string,int>
     */
    private function metDrempel(array $bestanden, int $standaard): array
    {
        $resultaat = [];

        foreach ($bestanden as $sleutel => $waarde) {
            if (is_int($sleutel)) {
                $resultaat[(string) $waarde] = $standaard;

                continue;
            }

            $resultaat[$sleutel] = (int) $waarde;
        }

        return $resultaat;
    }

    /**
     * Een project dat op de server draait terwijl niemand heeft opgeschreven
     * wát ervan bewaard moet blijven.
     *
     * @param  array<string,array<string,mixed>> $canoniek
     * @param  array<string,array<int,string>>   $verwacht
     * @param  array<string,string>              $uitgezonderd
     * @return array<int,array<string,mixed>>
     */
    private function zonderVerwachting(array $canoniek, array $verwacht, array $uitgezonderd): array
    {
        $findings = [];

        foreach ($canoniek as $slug => $project) {
            if (empty($project['server_path'])) {
                continue;
            }

            if (isset($verwacht[$slug]) || $this->uitzonderingsreden($uitgezonderd, $slug) !== null) {
                continue;
            }

            $findings[] = $this->finding(
                'medium',
                $slug,
                "{$slug} draait op {$project['server_path']} maar er staat nergens wát ervan bewaard moet blijven. Zet het in havun-backup.verificatie, of zonder het uit met een reden.",
            );
        }

        return $findings;
    }

    /**
     * Uitzonderingen blijven zichtbaar: een keuze die je terugleest is iets
     * anders dan een regel die ontbreekt. Een uitzondering voor een project dat
     * niet meer draait, is zelf achterhaald.
     *
     * @param  array<string,array<string,mixed>> $canoniek
     * @param  array<string,string>              $uitgezonderd
     * @return array<int,array<string,mixed>>
     */
    private function uitzonderingen(array $canoniek, array $uitgezonderd): array
    {
        $findings = [];

        foreach ($uitgezonderd as $slug => $reden) {
            if ($this->uitzonderingsreden($uitgezonderd, $slug) === null) {
                $findings[] = $this->finding(
                    'medium',
                    $slug,
                    "{$slug} is uitgezonderd van backup zonder reden — een lege reden is geen keuze.",
                );

                continue;
            }

            if (empty($canoniek[$slug]['server_path'])) {
                $findings[] = $this->finding(
                    'informational',
                    $slug,
                    "Backup-uitzondering voor {$slug} is achterhaald: het project draait niet (meer) op de server.",
                );

                continue;
            }

            $findings[] = $this->finding('informational', $slug, "{$slug} wordt bewust niet geback-upt: {$reden}");
        }

        return $findings;
    }

    /**
     * Een dump van iets waar niemand meer om vraagt. Op 01-08-2026 waren dat
     * `infosyst` en `havunclub_production`: elke nacht een lege dump van een
     * database van een app die 18-07 van de server ging.
     *
     * @param  array<string,array<int,string>> $verwacht
     * @param  array<string,array{leeftijd_uren:float,bytes:int}> $gevonden
     * @return array<int,array<string,mixed>>
     */
    private function overtolligeBackups(array $verwacht, array $gevonden): array
    {
        // Een verwachting is een lijst namen óf naam=>ondergrens; beide vormen
        // leveren hier alleen de namen op.
        $alleVerwacht = [];

        foreach ($verwacht as $bestanden) {
            foreach ($bestanden as $sleutel => $waarde) {
                $alleVerwacht[] = is_int($sleutel) ? (string) $waarde : $sleutel;
            }
        }
        $findings = [];

        foreach (array_keys($gevonden) as $bestand) {
            if (in_array($bestand, $alleVerwacht, true) || $this->isMetadata($bestand)) {
                continue;
            }

            $findings[] = $this->finding(
                'medium',
                '_backup',
                "{$bestand} wordt geback-upt maar staat bij geen enkel project — restant van een verwijderde app, of een verwachting die ontbreekt.",
            );
        }

        return $findings;
    }

    private function isMetadata(string $bestand): bool
    {
        return str_ends_with($bestand, '.sha256') || str_ends_with($bestand, '.txt');
    }

    /**
     * @param  array<string,string> $uitgezonderd
     */
    private function uitzonderingsreden(array $uitgezonderd, string $slug): ?string
    {
        $reden = $uitgezonderd[$slug] ?? null;

        if (! is_string($reden) || trim($reden) === '') {
            return null;
        }

        return trim($reden);
    }

    /**
     * @return array<string,mixed>
     */
    private function finding(string $severity, string $slug, string $message): array
    {
        return [
            'severity' => $severity,
            'title' => "Backupdekking: {$slug}",
            'config' => 'havun-backup.php',
            'slug' => $slug,
            'message' => $message,
        ];
    }
}
