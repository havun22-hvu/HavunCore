<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Toon per server-checkout hoeveel commits er klaarstaan, en wát erin zit.
 *
 * Bestond eerder als los shell-blok in /end §2c. Dat blok wérkte — op 25-07-2026
 * vond het 13 achterlopende checkouts — maar stond alleen in /end (dat lang niet
 * elke sessie draait) en telde alles even zwaar. Daardoor las 181 commits docs
 * hetzelfde als één security-fix, en bleef de fix voor 34 composer-advisories
 * 13 commits lang op Herdenkingsportaal-productie liggen.
 *
 * Daarom scheidt dit commando code van docs en licht het security-commits eruit:
 * een melding die altijd afgaat, wordt genegeerd.
 */
class DeployStatusCommand extends Command
{
    protected $signature = 'havun:deploy-status
        {--host=188.245.159.115 : Server om te bevragen}
        {--user=root : SSH-gebruiker}
        {--timeout=25 : SSH connect-timeout in seconden}
        {--all : Toon ook checkouts die alleen docs achterlopen}';

    protected $description = 'Wat staat er klaar om te deployen, en is er iets bij dat niet kan wachten';

    /** Commit-prefixen die geen deploy rechtvaardigen als er verder niets is. */
    private const DOCS_ONLY_PADEN = ['docs/', '.claude/', 'README', 'CLAUDE.md'];

    public function handle(): int
    {
        $script = $this->buildRemoteScript();

        $result = Process::timeout((int) $this->option('timeout') + 40)->run([
            'ssh',
            '-o', 'ConnectTimeout=' . (int) $this->option('timeout'),
            '-o', 'BatchMode=yes',
            $this->option('user') . '@' . $this->option('host'),
            $script,
        ]);

        if (! $result->successful()) {
            // Zacht falen: een sessie mag hier niet op vastlopen.
            $this->warn('Deploy-status niet op te halen (server onbereikbaar of SSH geweigerd).');
            $this->line(trim($result->errorOutput()) ?: 'geen foutmelding');

            return self::SUCCESS;
        }

        return $this->render(trim($result->output()));
    }

    /**
     * Eén remote script: per checkout de achterstand, de gewijzigde bestanden en
     * de onderwerpsregels. Alleen `fetch` — nooit merge of checkout, zodat de
     * draaiende kopie onaangeroerd blijft.
     */
    private function buildRemoteScript(): string
    {
        return <<<'BASH'
        for d in $(find /var/www -maxdepth 3 -name .git -type d 2>/dev/null | sed "s|/.git||"); do
          br=$(git -C "$d" rev-parse --abbrev-ref HEAD 2>/dev/null)
          [ -z "$br" ] || [ "$br" = "HEAD" ] && continue
          git -C "$d" fetch -q origin 2>/dev/null
          n=$(git -C "$d" rev-list --count "HEAD..origin/$br" 2>/dev/null)
          [ -z "$n" ] || [ "$n" -eq 0 ] && continue
          echo "###CHECKOUT|$d|$br|$n"
          git -C "$d" diff --name-only "HEAD..origin/$br" 2>/dev/null | sed "s|^|F|"
          git -C "$d" log --format="S%s" "HEAD..origin/$br" 2>/dev/null
        done
        BASH;
    }

    private function render(string $output): int
    {
        if ($output === '') {
            $this->info('Alle checkouts zijn bij.');

            return self::SUCCESS;
        }

        $checkouts = $this->parse($output);
        $alarm = [];
        $code = [];
        $docs = [];

        foreach ($checkouts as $c) {
            if ($c['security'] !== []) {
                $alarm[] = $c;
            } elseif ($c['codeBestanden'] > 0) {
                $code[] = $c;
            } else {
                $docs[] = $c;
            }
        }

        foreach ($alarm as $c) {
            $this->newLine();
            $this->error(sprintf('  SECURITY WACHT OP DEPLOY — %s [%s], %d commits achter', $c['pad'], $c['branch'], $c['aantal']));
            foreach ($c['security'] as $s) {
                $this->line('    · ' . $s);
            }
            $this->toonMigraties($c);
        }

        foreach ($code as $c) {
            $this->newLine();
            $this->warn(sprintf('  Code klaar — %s [%s], %d commits (%d codebestanden)', $c['pad'], $c['branch'], $c['aantal'], $c['codeBestanden']));
            foreach (array_slice($this->interessanteOnderwerpen($c['onderwerpen']), 0, 3) as $s) {
                $this->line('    · ' . $s);
            }
            $this->toonMigraties($c);
        }

        if ($docs !== []) {
            $this->newLine();
            if ($this->option('all')) {
                foreach ($docs as $c) {
                    $this->line(sprintf('  Alleen docs — %s [%s], %d commits', $c['pad'], $c['branch'], $c['aantal']));
                }
            } else {
                $this->line(sprintf('  %d checkout(s) lopen alleen op docs achter — die liften mee. (--all om te tonen)', count($docs)));
            }
        }

        $this->newLine();
        if ($alarm !== []) {
            $this->error(sprintf('  %d checkout(s) met een security-fix die nog niet live is. Dit is geen vraag maar een alarm.', count($alarm)));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function toonMigraties(array $c): void
    {
        if ($c['migraties'] > 0) {
            $this->line(sprintf('    LET OP: %d migratie(s) — niet terug te draaien met git revert, DB-backup vooraf', $c['migraties']));
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function parse(string $output): array
    {
        $checkouts = [];
        $huidig = null;

        foreach (explode("\n", $output) as $regel) {
            $regel = rtrim($regel, "\r");

            if (str_starts_with($regel, '###CHECKOUT|')) {
                if ($huidig !== null) {
                    $checkouts[] = $huidig;
                }
                [, $pad, $branch, $aantal] = explode('|', $regel, 4);
                $huidig = [
                    'pad' => $pad,
                    'branch' => $branch,
                    'aantal' => (int) $aantal,
                    'codeBestanden' => 0,
                    'migraties' => 0,
                    'onderwerpen' => [],
                    'security' => [],
                ];
                continue;
            }

            if ($huidig === null) {
                continue;
            }

            if (str_starts_with($regel, 'F')) {
                $bestand = substr($regel, 1);
                if ($bestand === '') {
                    continue;
                }
                if (! $this->isDocs($bestand)) {
                    $huidig['codeBestanden']++;
                }
                if (str_contains($bestand, 'database/migrations/')) {
                    $huidig['migraties']++;
                }
                continue;
            }

            if (str_starts_with($regel, 'S')) {
                $onderwerp = substr($regel, 1);
                $huidig['onderwerpen'][] = $onderwerp;
                if ($this->isSecurity($onderwerp)) {
                    $huidig['security'][] = $onderwerp;
                }
            }
        }

        if ($huidig !== null) {
            $checkouts[] = $huidig;
        }

        return $checkouts;
    }

    /**
     * Toon liever `feat`/`fix` dan `docs` — anders zie je bij een batch van 181
     * commits drie handover-regels terwijl er 238 codebestanden onder liggen.
     *
     * @param  array<int, string>  $onderwerpen
     * @return array<int, string>
     */
    private function interessanteOnderwerpen(array $onderwerpen): array
    {
        $code = array_values(array_filter(
            $onderwerpen,
            static fn (string $s): bool => ! str_starts_with(strtolower($s), 'docs')
                && ! str_starts_with(strtolower($s), 'chore(docs)')
        ));

        return $code !== [] ? $code : $onderwerpen;
    }

    private function isDocs(string $bestand): bool
    {
        foreach (self::DOCS_ONLY_PADEN as $pad) {
            if (str_starts_with($bestand, $pad)) {
                return true;
            }
        }

        return str_ends_with($bestand, '.md');
    }

    private function isSecurity(string $onderwerp): bool
    {
        $l = strtolower($onderwerp);

        return str_contains($l, 'security')
            || str_contains($l, 'cve-')
            || str_contains($l, 'advisor')
            || str_contains($l, 'vulnerab')
            || str_starts_with($l, 'chore(deps)');
    }
}
