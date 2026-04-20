---
title: `critical-paths:verify` command — spec
type: decision
scope: havuncore
status: draft-ready-for-plan
date: 2026-04-20
follows: ["test-quality-policy.md", "critical-paths-havuncore.md"]
---

# `php artisan critical-paths:verify` — specificatie

> Eén-regel: parseert `docs/kb/reference/critical-paths-{project}.md`,
> controleert dat elk genoemd testbestand/method bestaat en groen draait,
> en rapporteert onomwonden waar de audit-bewijzen rotten.

## Waarom deze command

De binding standaard (`test-quality-policy.md`) zegt: audit-bewijs = de
kritieke-paden-lijst. Maar zonder machine-verificatie kan die lijst
stilletjes verouderd raken:

- Een test wordt hernoemd → doc noemt oude naam.
- Een test wordt verwijderd → doc blijft verwijzen.
- Een nieuwe branch aan het pad toegevoegd → geen test toegevoegd aan
  lijst.
- Een genoemde test is skipped/incomplete → doc claimt dekking die er
  niet is.

Deze command maakt de doc **uitvoerbaar** — als de lijst niet klopt,
faalt de verificatie. Dat dwingt de doc-onderhoud af.

## Scope (wat deze command WEL en NIET doet)

**WEL:**

- Lezen van de `critical-paths-{project}.md` bestanden voor één of meer
  projecten.
- Parsen van test-referenties: per pad de opgesomde test-bestanden +
  test-method-namen.
- Controleren dat de bestanden bestaan (relative to project root).
- Controleren dat de methoden bestaan in die bestanden (grep op
  `function <name>`).
- Optionele modus `--run`: draai de genoemde tests (filter op bestand +
  method), rapporteer pass/fail/skip.
- Non-zero exit-code bij broken verwijzing of failing test.
- JSON-output voor CI-integratie (`--json`).

**NIET:**

- Mutation testing (dat is een separate, zwaardere check — toekomst).
- Branch-compleetheid beoordelen (of alle branches in §"branches / edge-
  cases" écht zijn gedekt — vereist mutation of coverage-mapping, blijft
  menselijke review).
- Auto-generatie van tests uit de doc.
- Auto-update van de doc (alleen lezen, nooit schrijven).

## Input — doc-format dat we parseren

`critical-paths-havuncore.md` (§per pad) heeft secties als:

```md
**Tests die dit afdekken:**

- `tests/Feature/VaultControllerTest.php` (happy path + auth)
- `tests/Feature/Middleware/EnsureAdminTokenTest.php`
- `tests/Unit/Vault/*` (service/encryptie)

**Mutation-score target:** 90 %.
```

**Parse-regels:**

1. **Per H2-kop `## Pad N — ...`** is een "kritiek pad".
2. Binnen het pad zoeken naar de kop `**Tests die dit afdekken:**`
   (of varianten: `**Tests:**`, `Tests die dit dekken:`).
3. Bulletpoints (`- `) met een backtick-path die eindigt op `.php` =
   test-bestandsreferentie. Optioneel parenthetisch commentaar erachter
   mag worden genegeerd.
4. **Wildcards (`*`)** zijn toegestaan (bv. `tests/Unit/Vault/*`) — die
   worden expanded via `glob()`.
5. **Method-level referenties** (toekomst) via inline `::method` syntax
   zoals `tests/Feature/VaultControllerTest.php::test_non_admin_token_denied`.
   Deze command herkent de syntax; voor nu is bestand-niveau genoeg.

**Mutation-score target** wordt **genegeerd** door deze command (hoort
bij de toekomstige mutation-check).

## Output — wat de gebruiker ziet

### Standaardmodus (`critical-paths:verify`)

```
[havuncore] Verifying 6 critical paths from docs/kb/reference/critical-paths-havuncore.md

Pad 1: Vault
  ✓ tests/Feature/VaultControllerTest.php
  ✓ tests/Feature/Middleware/EnsureAdminTokenTest.php
  ✗ tests/Unit/Vault/*  (glob matched 0 files)

Pad 2: AI Proxy
  ✓ tests/Feature/AiChatApiTest.php
  ✗ tests/Unit/Services/AIProxyServiceTest.php (file missing)
  ✓ tests/Unit/Services/CircuitBreakerTest.php

... (andere paden)

Summary: 6 paths / 18 references / 15 OK / 3 missing
Exit: 1 (some references are broken)
```

### `--run` mode

Voor elk bestaand referentie-bestand: voer de tests uit (`php artisan
test --filter=<ClassName>`), toon resultaat. Exit 1 bij failures.

### `--json` mode

Machine-leesbaar. Schema:

```json
{
  "project": "havuncore",
  "doc": "docs/kb/reference/critical-paths-havuncore.md",
  "paths": [
    {
      "name": "Vault",
      "references": [
        {"path": "tests/Feature/VaultControllerTest.php", "exists": true, "tests_run": null, "tests_failed": null},
        {"path": "tests/Unit/Vault/*", "exists": false, "glob_matched": 0, "error": "no files match"}
      ]
    }
  ],
  "totals": {"paths": 6, "references": 18, "ok": 15, "missing": 3, "failed": 0}
}
```

## Argumenten & opties

```bash
# Default: alleen HavunCore, read-only check
php artisan critical-paths:verify

# Specifiek project
php artisan critical-paths:verify --project=judotoernooi

# Alle projecten die een critical-paths-*.md hebben
php artisan critical-paths:verify --all

# Ook echt draaien
php artisan critical-paths:verify --run

# JSON
php artisan critical-paths:verify --json
```

**Conflict-regels:**

- `--project=X` en `--all` zijn mutueel exclusief.
- Geen argument = `--project=havuncore` (waar deze command bestaat).

## Exit-codes

| Code | Betekenis |
|------|-----------|
| 0 | Alle referenties kloppen (+ alle tests groen indien `--run`). |
| 1 | Minstens één broken reference of failing test. |
| 2 | Command kon niet draaien (doc ontbreekt, project bestaat niet, JSON schema corrupt). |

## CI-integratie

Drie invocaties in scheduler + CI:

- **Dagelijks** (`routes/console.php`): `Schedule::command('critical-paths:verify --json')->dailyAt('03:52')`.
  Koppelt aan `qv:scan` workflow (findings naar KB).
- **Per PR** (GitHub Actions, HavunCore-repo): run zonder `--run` — snelle
  link-check. Non-zero blokkeert merge als de PR de doc raakt.
- **Per release** (geen CI-invocatie, handmatig voor de release-PR):
  met `--run`, om te bevestigen dat alle kritieke paden-tests groen
  staan op de release-commit.

## Falen = wat de auteur dan moet doen

Broken reference:

- **Bestand/methode bestaat niet meer** → doc aanpassen (test-verwijzing
  updaten of hele regel weghalen + vervanging noemen).
- **Glob matched 0** → nieuwe tests schrijven of glob specifieker maken.
- **Doc zelf ontbreekt** → project heeft geen critical-paths-lijst
  terwijl het wel kritieke componenten heeft → nieuwe doc opstellen
  (dat is dan een aparte PR).

Failing test in `--run` mode:

- **VP-17 regel leidt** — eerst oorzakenonderzoek, niet simpelweg de
  test aanpassen. Test-quality-policy.md §4 + test-repair-anti-pattern.md.

## Tests voor deze command

Zie de implementatie-plan (Fase 2). Minstens:

1. Happy path: doc met 1 pad, 1 bestaande test-file → exit 0.
2. Missing file → exit 1, correct gerapporteerd in summary + JSON.
3. Glob zonder match → exit 1, correct gerapporteerd.
4. `--all` iterates over alle `critical-paths-*.md` files.
5. `--json` produceert valide JSON met vast schema.
6. `--run` triggert `php artisan test` met juiste filter.

Deze command komt ook **op de kritieke-paden-lijst te staan** (zelfde
doc) — want hij is zelf ook audit-infrastructuur die niet mag rotten.

## Waar komt de code

- `app/Console/Commands/CriticalPathsVerifyCommand.php` (hoofdcommando)
- `app/Services/CriticalPaths/DocParser.php` (parseer de MD)
- `app/Services/CriticalPaths/ReferenceChecker.php` (glob + exists)
- `app/Services/CriticalPaths/TestRunner.php` (wrapper rond `artisan test --filter`)
- `tests/Feature/Commands/CriticalPathsVerifyCommandTest.php`
- `tests/Unit/CriticalPaths/DocParserTest.php`
- `tests/Unit/CriticalPaths/ReferenceCheckerTest.php`

## Open vragen (voor Fase 2, niet nu beslissen)

- Moet deze command ook lopen op HavunAdmin/JT/HP wanneer zij
  critical-paths-*.md krijgen? **Ja**, vanaf het moment dat die docs
  bestaan. Geen code-duplicatie nodig: command leest de file-naam
  dynamisch uit `docs/kb/reference/critical-paths-*.md`.
- Per-project override voor test-commando (phpunit vs pest vs npm)?
  Voor HavunCore (Laravel/PHPUnit) is het eenvoudig. Frontend/Expo
  projecten hebben andere runners — voor nu alleen Laravel-projecten
  ondersteunen, open extensie-punt later.

## Status

Dit doc = Fase 1 van /mpc. **100 % compleet voor HavunCore/PHPUnit
scope.** Fase 2 (plan) volgt in aparte MD. Geen code tot Fase 2 door
gebruiker is goedgekeurd.
