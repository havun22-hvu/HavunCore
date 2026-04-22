<?php

namespace Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

/**
 * Meta-test: elke `ignore`-regel in `infection-critical-paths.json5`
 * moet een `//`-comment hebben als toelichting waarom de mutation
 * niet killable is. Voorkomt stilzwijgende deletes van ignore-entries
 * tijdens een refactor (risico L1 uit werkwijze-verslag 22-04).
 */
class InfectionIgnoresHaveCommentsTest extends TestCase
{
    public function test_each_ignore_method_path_has_an_explaining_comment(): void
    {
        $raw = file_get_contents(__DIR__ . '/../../../infection-critical-paths.json5');
        $this->assertNotFalse($raw, 'infection-critical-paths.json5 must exist');

        $lines = explode("\n", $raw);
        $violations = [];

        foreach ($lines as $idx => $line) {
            if (! preg_match('#^\s*"App\\\\\\\\#', $line)) {
                continue;
            }

            $foundComment = false;
            for ($back = 1; $back <= 10; $back++) {
                $prev = $lines[$idx - $back] ?? '';

                if (preg_match('#^\s*//#', $prev)) {
                    $foundComment = true;
                    break;
                }

                if (trim($prev) === '' || preg_match('#"ignore":\s*\[#', $prev) || preg_match('#^\s*"[A-Z][A-Za-z]+":\s*\{#', $prev)) {
                    continue;
                }

                if (preg_match('#^\s*"App\\\\\\\\#', $prev)) {
                    continue;
                }

                break;
            }

            if (! $foundComment) {
                $violations[] = sprintf('line %d: %s', $idx + 1, trim($line));
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Elke ignore-entry moet binnen 10 regels terug een //-comment hebben met de WHY.\n"
                . "Doel: voorkomt dat iemand stilzwijgend een ignore toevoegt/verwijdert.\n"
                . "Gevonden zonder comment:\n" . implode("\n", $violations)
        );
    }
}
