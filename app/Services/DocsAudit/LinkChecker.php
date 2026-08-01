<?php

namespace App\Services\DocsAudit;

use App\Enums\Severity;

/**
 * Valideert interne markdown-links. Externe http(s)-links worden
 * overgeslagen (niet onze verantwoordelijkheid, zou rate-limit-issues
 * geven). Anchors (#section) worden ook overgeslagen om false-positives
 * bij header-renaming te voorkomen.
 *
 * Broken internal link → Critical: docs zijn misleidend als de lezer
 * op een dode link klikt.
 */
class LinkChecker
{
    /**
     * @return list<array<string,mixed>>
     */
    public function check(string $absolutePath): array
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false) {
            return [];
        }

        $findings = [];
        $baseDir = dirname($absolutePath);

        preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $this->zonderCode($content), $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $label = $m[1];
            $target = trim($m[2]);

            // Skip external links and pure anchors.
            if (preg_match('#^https?://#i', $target) || str_starts_with($target, '#')) {
                continue;
            }

            // Skip mailto/tel/etc.
            if (preg_match('#^[a-z]+:#i', $target)) {
                continue;
            }

            // Strip anchor suffix — we validate only the file part.
            $target = preg_replace('/#.*$/', '', $target);
            if ($target === '' || $target === null) {
                continue;
            }

            $resolved = $this->resolve($baseDir, $target);
            if (! file_exists($resolved)) {
                $findings[] = [
                    'severity' => Severity::Critical->value,
                    'detector' => 'link',
                    'file' => $absolutePath,
                    'detail' => "Broken link: [{$label}]({$target})",
                    'action' => 'Corrigeer of verwijder link',
                ];
            }
        }

        return $findings;
    }

    /**
     * Haalt code-blokken en inline code weg vóór het zoeken naar links.
     *
     * Een link tussen backticks is een **voorbeeld**, geen verwijzing. Zonder
     * deze stap meldde de checker op 01-08-2026 een critical op
     * `doc-intelligence-setup.md` — in de regel die uitlegt hóé de checker
     * false-positives voorkomt, met `[x](./README.md#sectie)` als voorbeeld.
     * De tool vond zijn eigen documentatie.
     *
     * Regels worden vervangen door lege tekst en niet verwijderd, zodat de
     * rest van het document op zijn plek blijft staan.
     */
    private function zonderCode(string $content): string
    {
        $zonderFences = preg_replace('/^```.*?^```/ms', '', $content) ?? $content;

        return preg_replace('/`[^`\n]*`/', '', $zonderFences) ?? $zonderFences;
    }

    private function resolve(string $baseDir, string $target): string
    {
        if (str_starts_with($target, '/')) {
            // Absolute from filesystem root — leave as-is (unusual in KB).
            return $target;
        }

        $joined = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($target, '/\\');

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $joined);
    }
}
