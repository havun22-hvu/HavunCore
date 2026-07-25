---
title: "Plan: /start en /end — de deploy-achterstand moet niet meer te missen zijn"
type: plan
scope: havuncore
status: in uitvoering
date: 2026-07-25
---

# /start en /end verbeteren

**Aanleiding (25-07-2026):** Herdenkingsportaal-productie stond 13 commits achter, met daarin de
fix voor 34 composer-advisories (4 high). Henk merkte het op, niet de sessie.

## Waarom het misging — gemeten, niet vermoed

`/end` §2c heeft al een actieve prod-deploy-check en die **werkt gewoon**: uitgevoerd op 25-07
vond hij 13 achterlopende checkouts.

| Checkout | Achter | Waarvan code |
|---|---|---|
| veenledenadministratie/production | **181** | 238 bestanden |
| havuncore/production | **64** | 26 bestanden |
| vusista/staging | 64 | |
| vpdupdate | 59 | |
| havuncore/webapp · safehavun · studieplanner · havun.nl · judotoernooi (2×) · havunadmin (2×) | 3-8 | |

Het probleem is dus **niet de check maar de opvolging**:

1. **De check zit alleen in `/end`.** `/start` draait altijd (het is de eerste handeling van een
   sessie); `/end` niet — een sessie eindigt vaak gewoon. Daarmee is de enige plek waar de
   achterstand zichtbaar wordt, ook de plek die het vaakst wordt overgeslagen.
2. **Het shell-blok staat gedupliceerd** in `/end` (2b en 2c) en groeit uit elkaar per project.
3. **Alles telt even zwaar.** 181 commits docs leest hetzelfde als 1 commit met een security-fix.
   Zonder dat onderscheid is de melding ruis en wordt hij genegeerd — precies wat er gebeurde.
4. **CI-gezondheid ontbreekt volledig.** Veens CI faalt sinds de workflow bestaat (SQLite in
   `ci.yml` tegen MySQL in `phpunit.xml`); Herdenkingsportaals deploy-workflows sloegen de fix
   stilzwijgend over (`e182df3` heet letterlijk *"repair both deploy workflows before they
   silently skip the fix"*). Beide bleven maanden onopgemerkt.

## Oplossing

**Eén artisan-command als bron van waarheid:** `php artisan havun:deploy-status`

- meet per checkout de achterstand op `origin/<branch>`;
- scheidt **code** van **alleen-docs** — een docs-achterstand is geen deploy-reden;
- licht **security-commits** eruit (`fix(security)`, `security:`, `chore(deps)`) als **alarm**,
  niet als vraag;
- meldt **migraties** in de batch apart (niet terug te draaien met `git revert`);
- toont de **CI-status** van de laatste run per repo.

`/start` en `/end` roepen allebei dat ene commando aan in plaats van hun eigen shell-blok.

## Agendapunten

1. `app/Console/Commands/DeployStatusCommand.php` + test.
2. `/start`: nieuwe verplichte stap na de server-hygiëne — draait het commando, meldt alleen wat
   code of security bevat.
3. `/end`: §2c vervangen door hetzelfde commando; de "actief vragen"-regel blijft.
4. Uitrol van de gewijzigde tekst hoort **niet** in dit plan — `/start` en `/end` zijn per project
   verschillend gegroeid. Wel: de achterhaalde ">80% coverage"-eis in `JudoToernooi/.claude/commands/start.md`
   (regels 183, 205) en `start2.md:79` opruimen, want die spreekt de norm van 24-07 tegen.

## Risico's

| Risico | Aanpak |
|---|---|
| Nóg een melding die genegeerd wordt | Daarom filteren op code + security. Stil bij alleen-docs |
| SSH traag/onbereikbaar bij sessiestart | Time-out van 20s, faalt zacht met een duidelijke melding — `/start` mag er niet op vastlopen |
| `git fetch` op productie | Alleen fetch, nooit merge/checkout. Raakt de werkende kopie niet |
