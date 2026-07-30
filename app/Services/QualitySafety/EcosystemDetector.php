<?php

namespace App\Services\QualitySafety;

/**
 * Stelt vast hoe een project gebouwd is, door naar de manifesten te kijken.
 *
 * **Detectie, geen registratie.** Een `stack`-veld in config/quality-safety.php
 * zou een tweede waarheid worden die uit de pas loopt zodra iemand een
 * package.json toevoegt — dezelfde fout als de hardcoded projectlijst die
 * DocIndexer jarenlang naast havun-projects.php had. Het manifest ís het feit.
 *
 * Waarom de boom in en niet alleen de root: Vusista2 heeft géén Cargo.toml in
 * de root maar vier Cargo.lock-bestanden in submappen. Een detector die alleen
 * de root bekijkt, meldt daar "geen Rust" en dan meet niemand die crates.
 */
class EcosystemDetector
{
    /**
     * Manifest → ecosysteem. Volgorde is niet van belang; een repo mag er
     * meerdere hebben (een Laravel-app met een Vite-frontend heeft er twee).
     *
     * @var array<string,string>
     */
    private const MANIFESTS = [
        'composer.json' => 'php',
        'package.json' => 'js',
        'Cargo.lock' => 'rust',
        'go.mod' => 'go',
        'requirements.txt' => 'python',
        'pyproject.toml' => 'python',
        'pubspec.yaml' => 'dart',
        'build.gradle' => 'java',
        'build.gradle.kts' => 'java',
    ];

    /**
     * Ecosystemen waarvoor de scanner een dependency-audit kan draaien.
     * Alles wat hier NIET in staat maar wel gedetecteerd wordt, levert een
     * "niet gemeten"-bevinding op — dat is het hele punt van deze klasse.
     *
     * @var array<int,string>
     */
    public const AUDITABLE = ['php', 'js', 'rust'];

    /**
     * Mappen die we nooit in duiken. `target/`, `node_modules/` en `vendor/`
     * bevatten de manifesten van *afhankelijkheden*, niet van dit project —
     * meenemen zou duizenden crates als eigen ecosysteem tellen.
     *
     * @var array<int,string>
     */
    private const SKIP_DIRS = [
        'target', 'node_modules', 'vendor', '.git', 'dist', 'build',
        'storage', 'bootstrap', '.next', '.nuxt', 'Pods', '.venv', 'venv',
    ];

    private const MAX_DEPTH = 4;

    /**
     * @return array<string,array<int,string>>  ecosysteem → relatieve paden van de manifesten
     */
    public function detect(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (! is_dir($root)) {
            return [];
        }

        $gevonden = [];
        foreach ($this->walk($root, 0) as $absoluutPad) {
            $bestandsnaam = basename($absoluutPad);
            $ecosysteem = self::MANIFESTS[$bestandsnaam] ?? null;
            if ($ecosysteem === null) {
                continue;
            }

            $relatief = ltrim(substr($absoluutPad, strlen($root)), '/');
            $gevonden[$ecosysteem][] = $relatief;
        }

        ksort($gevonden);

        return $gevonden;
    }

    /**
     * Ecosystemen die we wél zien maar niet kunnen auditen.
     *
     * @param  array<string,array<int,string>>  $gedetecteerd
     * @return array<int,string>
     */
    public function unauditable(array $gedetecteerd): array
    {
        return array_values(array_diff(array_keys($gedetecteerd), self::AUDITABLE));
    }

    /**
     * @return \Generator<int,string>
     */
    private function walk(string $dir, int $diepte): \Generator
    {
        if ($diepte > self::MAX_DEPTH) {
            return;
        }

        $entries = @scandir($dir);
        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $pad = $dir . '/' . $entry;

            if (is_dir($pad)) {
                if (in_array($entry, self::SKIP_DIRS, true)) {
                    continue;
                }
                yield from $this->walk($pad, $diepte + 1);

                continue;
            }

            if (array_key_exists($entry, self::MANIFESTS)) {
                yield $pad;
            }
        }
    }
}
