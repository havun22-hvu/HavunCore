# Plan: JudoToernooi Coverage Push (zinvolle tests only)

> **Status:** Fase 2 — voor akkoord
> **Datum:** 2026-04-20
> **Doel:** JT Lines coverage 37,6% → >80% door ALLEEN zinvolle tests
> **Leidraad:** `docs/kb/patterns/zinvolle-tests.md`
> **Branch:** `feat/restore-deleted-tests` (bestaand, 17 test-bestanden reeds toegevoegd vannacht)

## Uitgangspunt

- JT = 18.344 regels source. Baseline: 6.898 covered (37,6% Lines).
- Voor 80% Lines nodig: 14.675 covered = **~7.800 regels extra te dekken**.
- Henk (20-04-2026): "alleen zinvolle tests, Claude werkt 10-30x sneller, padding hoeft niet."
- Output: branch klaar voor merge, coverage.txt met **>80% Lines**, tests die écht bugs vangen.

## Plan in 3 sub-fases

### Sub-fase A: Prune bestaande padding

Review van de 17 test-bestanden die vannacht zijn toegevoegd (119 tests). Classificatie volgens `zinvolle-tests.md`:

| Bestand | Blijven | Schrappen | Reden schrapping |
|---------|--------:|----------:|-----------------|
| `Unit/Models/MagicLinkTokenTest.php` | 9 | **3** | `stores_metadata_array` (framework cast), `mark_used_sets_timestamp` (triviale update), `is_expired_reflects_expires_at` (getter) |
| `Unit/Requests/ToernooiRequestTest.php` | 9 | **1** | `authorize_returns_true` (1-regel method) |
| `Unit/Middleware/SecurityHeadersTest.php` | 8 | 0 | allemaal security-invariants |
| `Unit/Middleware/ObservabilityMiddlewareTest.php` | 5 | 0 | allemaal domein+invariant |
| `Unit/Mail/MagicLinkMailTest.php` | 7 | **1** | `uses_magic_link_markdown_template` (implementation detail) |
| `Unit/Models/SyncConflictTest.php` | 3 | **2** | `unresolved_scope` (triviaal where), `array_casts_round_trip` (framework cast) |
| `Unit/Middleware/TrackResponseTimeTest.php` | 2 | **1** | `response_is_returned_unchanged` (triviaal) |
| `Unit/Middleware/CheckFreemiumPrintTest.php` | 3 | 0 | allemaal domein |
| `Unit/Events/BroadcastEventsTest.php` | 10 | 0 | allemaal frontend-contracts |
| `Unit/Requests/ClubRequestTest.php` | 6 | **1** | `authorize_returns_true` |
| `Unit/Middleware/CheckRolSessieTest.php` | 4 | 0 | allemaal security-branch |
| `Unit/Concerns/HandlesWedstrijdConflictTest.php` | 5 | 0 | allemaal invariants (incl. 1s drift) |
| `Unit/Models/ClubUitnodigingTest.php` | 4 | **3** | `explicit_token_is_preserved`, `update_laatst_ingelogd`, `relationships_load` |
| `Unit/Models/CoachCheckinTest.php` | **1** | **6** | alleen `is_uit_geforceerd_counts_as_uit` (domein-regel) blijft; rest is getter/scope padding |
| `Unit/Models/TvKoppelingTest.php` | 3 | **3** | `is_expired`, `is_linked`, `relationship` (allemaal getters) |
| `Unit/Models/VrijwilligerTest.php` | 4 | **3** | `functies_constant`, `get_functie_label_capitalises`, `relationship` |
| `Feature/AccountControllerTest.php` | 12 | 0 | allemaal auth/contract/invariant |
| **Totaal** | **95** | **24** | |

**Effect:** van 119 → 95 tests. Coverage-impact: minimaal (padding-tests raken typisch 1-3 regels elk → ~30-60 regels verlies, verwaarloosbaar t.o.v. 7.800 gap).

**Extra actie:** `CoachCheckinTest.php` wordt bijna leeg — consolideren met bestaand test-bestand voor CoachCheckin/CoachKaart (of 1-test-bestand acceptabel als domein-waarschuwing voor refactorers).

### Sub-fase B: Zinvolle nieuwe tests — top-impact domains

Richt op JT's **kernbusiness-algoritmes** waar 1 zinvolle test ~50-200 regels dekt én echte regressie-risico's vangt.

#### Prio 1 — Bracket/Poule/Wedstrijd-algoritmes (~2000-3000 regels coverage)

| Doel-bestand | Regels | Huidige cov | Invarianten die we willen borgen |
|-------------|-------:|------------:|----------------------------------|
| `Services/VariabeleBlokVerdelingService.php` | 815 | 38% | • Totale judoka-count blijft = som blok-counts (conservatie)<br>• Max afwijking % wordt gerespecteerd<br>• Aansluiting-penalty reduceert tussenblok-sprongen<br>• Timeout respect (`MAX_TIJD_SECONDEN`) |
| `Http/Controllers/RoleToegang.php` | 442 | 3% | • Admin-rol toegang tot admin-endpoints (403 voor non-admin)<br>• Weging-rol alleen weging-endpoints<br>• Jury-rol alleen jury-endpoints<br>• PIN-code login valideert, rate-limit werkt |
| `Services/BlokVerdeling/BlokPlaatsingsHelper.php` | ~200 | onbekend (zie `BlokVerdelingHelpers`) | • Categorie past in `max_leeftijd` grens (hard)<br>• Gewichtsklasse-tolerantie wordt gerespecteerd |
| `Services/BracketCalculator.php` | ? | 0% | • Aantal rondes klopt voor 2/4/8/16 judokas<br>• Seeding-regels (1 en 2 ontmoeten in finale pas)<br>• Bye-plaatsing klopt bij niet-macht-van-2 aantal |

#### Prio 2 — Payment/Webhook flows (~500-1000 regels coverage)

| Doel-bestand | Regels | Huidige cov | Invarianten |
|-------------|-------:|------------:|-------------|
| `Services/Payments/StripePaymentProvider.php` | 298 | 22% | • Webhook signature-verificatie faalt → 400<br>• Dubbele webhook met zelfde id is idempotent<br>• `paid` status leidt tot Betaling persistentie |
| `Services/OfflineExportService.php` | 212 | 14% | • Gegenereerd zip bevat alle verwachte bestanden<br>• Toernooi zonder judokas → lege export, geen crash |
| `Http/Controllers/MollieController.php` | ? | ? | • Webhook retry is idempotent<br>• Onbekende status wordt gelogd, niet doorgelaten |

#### Prio 3 — Grote untested controllers (~2500-3500 regels coverage)

**Criterium:** alleen routes mét duidelijke branches testen (auth / happy / 404 / 409 / validation-fail). Geen view-rendering asserties.

| Doel-bestand | Regels | Focus |
|-------------|-------:|-------|
| `Http/Controllers/WedstrijddagController.php` | 35KB ≈ 800 | Dag-starten, dag-sluiten, mat-toewijzing, judoka-verplaatsen |
| `Http/Controllers/CoachPortalController.php` | 35KB ≈ 800 | Coach-auth (PIN + device-binding), kaart-in/uit, aanwezigheid |
| `Http/Controllers/PubliekController.php` | 29KB ≈ 700 | Publieke routes: toernooi-overzicht, mat-live, uitslagen read-only |
| `Http/Controllers/JudokaController.php` | 28KB ≈ 650 | CRUD judoka, csv-import, bulk verplaats |
| `Http/Controllers/ClubController.php` | 27KB ≈ 600 | Club CRUD, uitnodiging-flow, coach-beheer |

**Niet:** `NoodplanController` (freemium-gated, complex view-rendering) en `LocalSyncController` (sync is al apart gedekt via LocalSyncService tests) — deze pas als Prio 3 onvoldoende blijkt.

#### Prio 4 — Verbetering bestaande partials (~500-1000 regels)

Files die al >0% zitten maar nog branches missen:
- `App\Services\AutoFixService` (52/457 = 11%) — focus: rate-limit, delivery-mode switch, dry-run vs branch-pr paths (kritieke infra)
- `App\Services\AutoFix\GitOperations` (3/111 = 3%) — focus: branch-create, commit-message-build, push-dry-run
- `App\Services\LocalSyncService` (38/131 = 29%) — focus: conflict-resolution paths (local-authority), queue-persistence

### Sub-fase C: Meting + iteratie

1. Na sub-fase A: coverage-run **A**: baseline na pruning (verwacht ~37% — pruning raakt weinig)
2. Na Prio 1 tests: coverage-run **B** (verwacht ~50-55%)
3. Na Prio 2 tests: coverage-run **C** (verwacht ~58-62%)
4. Na Prio 3 tests: coverage-run **D** (verwacht ~72-78%)
5. Na Prio 4 tests: coverage-run **E** (doel ≥80%)
6. Als E <80%: analyseer resterende gaps en voeg targeted tests toe (niet breed, alleen voor specifieke gaps)

**Meting-methode:** `--coverage-clover` (niet `--coverage-text` — dat hing vannacht 20+ min). Parse clover.xml voor % getallen. Tijd per run: schatting 6-10 min.

## Bestandsvolgorde & afhankelijkheden

```
1. Sub-fase A (pruning)
   ├── per file: identificeer tests uit de tabel hierboven
   ├── per file: één commit, message "refactor(tests): prune padding in X"
   └── NA: coverage-run A
2. Sub-fase B Prio 1 (algoritmes)
   ├── VariabeleBlokVerdelingService (grootste winst)
   ├── RoleToegang (auth-breed)
   ├── BracketCalculator
   └── NA: coverage-run B
3. Sub-fase B Prio 2 (payments)
   └── StripePaymentProvider, MollieController, OfflineExportService
4. Sub-fase B Prio 3 (controllers, parallelliseerbaar per controller)
   └── per controller één testbestand, focus op branches niet views
5. Sub-fase B Prio 4 (partials)
6. Sub-fase C meet-iteratie tot ≥80%
```

## Risico's

| Risico | Kans | Impact | Mitigatie |
|-------|------|-------|-----------|
| BracketCalculator/BlokVerdeling-algoritmes hebben complexe setups (Toernooi + Organisator + N judokas + categorieën) → tests zijn langzaam | Hoog | Medium | Gebruik bulk-insert helpers (analoog HavunCore), beperk setup tot minimum per test-geval |
| Domein-regels niet gedocumenteerd → tests pinnen bugs ipv correct gedrag | Medium | Hoog | Voor elke nieuwe test: lees de KB + bestaande tests + vraag Henk bij onduidelijkheid vóór assertion |
| SQLite vs MySQL CHECK-constraint verschillen (historisch JT-issue) | Medium | Medium | Vóór elke Model-test: check migratie op CHECK/enum, gebruik waarden uit migratie niet verzonnen strings |
| `--coverage-clover` hangt net als `--coverage-text` | Medium | Hoog | Fallback: run per testsuite apart (`--testsuite=Unit` dan `--testsuite=Feature`), of run in parts (`--group=...`) |
| Padding-prune breekt afhankelijke tests door gewijzigde setup-helpers | Laag | Laag | Na elke prune: alleen dat bestand runnen. Geen helpers verwijderen die nog gebruikt worden. |
| Coverage-doel niet gehaald in 1 sessie (gewoon te veel regels) | Medium | Medium | Sub-fase B Prio 1+2+3 moet realistisch 70%+ geven; Prio 4 is buffer. Als <80%: commit stand, updat handover, Henk beslist volgende stap |

## Klaar-criteria

- [ ] Sub-fase A: 24 padding-tests verwijderd, 95 blijven, alle green
- [ ] Sub-fase B: zinvolle tests toegevoegd tot meetbare Lines ≥80%
- [ ] Geen brittle tests (exacte payload-waarden, implementation-detail asserties)
- [ ] Coverage-run E succesvol uitgevoerd en cijfers in commit-message/handover
- [ ] Handover bijgewerkt met eindstand + eventuele resterende gaps

## Vragen aan gebruiker voor akkoord

1. **Padding-classificatie OK?** Check tabel sub-fase A — wil je tests die ik als "padding" markeer toch behouden? (bv. `is_expired_reflects_expires_at` — 1-regel getter)
2. **Prio-volgorde OK?** Eerst algoritmes → payments → controllers → partials? Of liever payments/webhooks eerst vanwege risico?
3. **Coverage-doel rigide?** Als 80% niet haalbaar zonder padding, stoppen we op hoogste waarde met alleen zinvolle tests (bv. 72%) — gaan we voor "best met zinvolle tests" of harde 80% (eventueel met Feature-integratie-tests die veel regels dekken per test)?
4. **Tijd boxen?** Geen deadline? Of doe ik de prune vanavond, rest morgenochtend?

Na akkoord: start ik Fase 3 (Implementatie) volgens dit plan. Bij afwijking update ik eerst het plan, dan pas code.
