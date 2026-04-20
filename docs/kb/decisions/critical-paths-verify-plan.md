---
title: `critical-paths:verify` — implementatieplan (Fase 2 /mpc)
type: decision
scope: havuncore
status: awaiting-approval
date: 2026-04-20
follows: "critical-paths-verify-command.md"
---

# Implementatieplan — `critical-paths:verify`

> Fase 2 van /mpc. Beschrijft EXACT welke bestanden worden gewijzigd/
> aangemaakt, in welke volgorde, met welke tests. **Geen code** tot
> dit plan goedgekeurd is.

## Bestandenoverzicht

### Nieuw

| Bestand | Verantwoordelijkheid | Ca. regels |
|---------|----------------------|------------|
| `app/Services/CriticalPaths/DocParser.php` | Leest `critical-paths-{project}.md`, levert `Path[]` objecten met `name`, `references[]` (paden als string). | ~60 |
| `app/Services/CriticalPaths/ReferenceChecker.php` | Neemt `references[]`, controleert `file_exists` + glob-expansie, levert `CheckResult[]` met `exists/matches/error`. | ~50 |
| `app/Services/CriticalPaths/TestRunner.php` | Wrapper rond `Artisan::call('test', ['--filter'=>...])`, returnt `RunResult` (passed/failed counts, duration). | ~40 |
| `app/Console/Commands/CriticalPathsVerifyCommand.php` | Glue: leest doc-paden, combineert parser+checker(+runner), print summary of JSON. | ~110 |
| `tests/Unit/CriticalPaths/DocParserTest.php` | Happy path, missing sectie, glob-referentie, inline commentaar. | ~90 |
| `tests/Unit/CriticalPaths/ReferenceCheckerTest.php` | Bestaand bestand, ontbrekend bestand, glob zonder match, glob met 3 matches. | ~70 |
| `tests/Feature/Commands/CriticalPathsVerifyCommandTest.php` | End-to-end: temp doc + temp tests/ dir, exit-codes, JSON-shape, `--run` flag. | ~140 |

### Gewijzigd

| Bestand | Wijziging |
|---------|-----------|
| `routes/console.php` | Één `Schedule::command('critical-paths:verify --json')->dailyAt('03:52')` regel. |
| `docs/kb/reference/critical-paths-havuncore.md` | Nieuwe kop "Pad 7 — Audit infrastructure (`critical-paths:verify`)" met zelf-verwijzing naar bovenstaande tests. |

**Niets anders wordt aangeraakt.** Geen bestaande services, controllers, middleware.

## Volgorde (één-richting, geen cirkels)

1. **DocParser + test** — pure functie, geen DB, geen container-binding. Start hier.
2. **ReferenceChecker + test** — leest filesystem via `Storage::disk('local')` of `File::` facade. Onafhankelijk van parser.
3. **TestRunner + test** — wrapper; testen via `Artisan::mock()` / `Bus::fake()`-stijl.
4. **Command + test** — glue. Feature-test gebruikt een temp `tests/fixtures/critical-paths/` dir met dummy doc + test-files, zodat we geen echte tests triggeren tijdens het testen van de command.
5. **routes/console.php scheduler-regel** — triviaal.
6. **Doc-update** (Pad 7 toevoegen).

Parallel kan: stap 1, 2, 3 zijn onafhankelijk. In praktijk schrijf ik ze sequentieel want de command (stap 4) moet ze alle 3 binden.

## Afhankelijkheden

- **Binnen HavunCore**: `Storage`, `File`, `Artisan` facades, `Illuminate\Console\Command`, `Symfony\Component\Console\Command\Command` voor exit-codes.
- **Buiten HavunCore**: geen — geen composer-packages nodig.
- **Tests**: bestaande `Tests\TestCase`, `Storage::fake()`, `Artisan::call()`.

## Risico's + mitigaties

| Risico | Kans | Impact | Mitigatie |
|--------|------|--------|-----------|
| Regex-parsing van MD is broos (users schrijven docs in lichtelijk afwijkend formaat) | medium | medium | Parser documenteert de verwachte syntax in PHPDoc; fallback: als een pad 0 referenties produceert, WARNING (niet ERROR). Test-fixtures coveren 3 formats. |
| `--run` triggert echte test-suite → langzaam in CI | medium | low | Niet standaard aan; alleen bij release-check. Per-PR run alleen link-check. |
| Command wordt zelf niet getest → audit-infra die roest | medium | hoog | Pad 7 toegevoegd aan critical-paths-havuncore.md, zodat deze command zichzelf afdwingt om groen te blijven. |
| False positives (test hernoemd, doc nog niet) blokkeren merge | laag | laag | Acceptabel — dat is het hele punt. De PR-auteur moet dan ook de doc updaten. |
| Glob met `*` matcht testen buiten scope (bv. `tests/Unit/Vault/*` matcht ook `tests/Unit/Vault/FixtureTest.php`) | laag | laag | Glob-matches worden opgelijst met aantal; reviewer ziet of er iets geks tussen zit. |

## Tests die verplicht zijn (§6 test-quality-policy)

**Kritieke paden verification command is zelf kritieke infra.** Daarom
worden de volgende tests verplicht (geen padding, alle assertief):

1. `DocParserTest::test_parses_single_path_with_test_references`
2. `DocParserTest::test_ignores_parenthetical_commentary_in_references`
3. `DocParserTest::test_handles_glob_reference_unchanged_in_parsed_output`
4. `DocParserTest::test_missing_references_section_yields_empty_list`
5. `DocParserTest::test_multiple_paths_parsed_with_correct_names`
6. `ReferenceCheckerTest::test_existing_file_reports_exists_true`
7. `ReferenceCheckerTest::test_missing_file_reports_exists_false_with_error`
8. `ReferenceCheckerTest::test_glob_expansion_returns_actual_matches`
9. `ReferenceCheckerTest::test_glob_zero_matches_reports_error`
10. `TestRunnerTest::test_passing_test_returns_success_result` (Artisan mocked)
11. `TestRunnerTest::test_failing_test_returns_failure_result`
12. `CriticalPathsVerifyCommandTest::test_exit_0_when_all_references_exist` (geen `--run`)
13. `CriticalPathsVerifyCommandTest::test_exit_1_when_reference_broken`
14. `CriticalPathsVerifyCommandTest::test_json_output_schema`
15. `CriticalPathsVerifyCommandTest::test_run_flag_triggers_test_execution`
16. `CriticalPathsVerifyCommandTest::test_all_flag_scans_all_critical_paths_docs`
17. `CriticalPathsVerifyCommandTest::test_project_and_all_are_mutually_exclusive`
18. `CriticalPathsVerifyCommandTest::test_missing_doc_yields_exit_2`

Geen `FinalBoost*` of `Coverage2` tests — elke test dekt één scenario
expliciet. Assertion-density ≥ 2 per test.

## Volg-op-werk (niet in deze PR)

- Mutation testing runner als aparte command (`qv:scan --only=mutation`).
- Critical-paths-*.md opstellen voor HavunAdmin / JT / HP zodra zij de
  verify-command willen gebruiken.
- Extensie voor frontend-projecten (Jest/Vitest) indien ooit gewenst.

## Schatting

Werk: ~4-6 uur geconcentreerd. ~550 regels productie + ~300 regels tests.
Eén commit per stap, een merge-commit aan het einde.

## Akkoord-vereisten

- [ ] Gebruiker akkoord met bestand-layout (`app/Services/CriticalPaths/*`).
- [ ] Gebruiker akkoord met scheduler-regel (03:52 dagelijks).
- [ ] Gebruiker akkoord met CI-gate per PR (link-check blokkeert merge).
- [ ] Gebruiker akkoord met het toevoegen aan kritieke paden (Pad 7).

Pas na akkoord begint Fase 3. Bij afwijking van plan tijdens Fase 3:
plan **eerst** updaten, dan code.
