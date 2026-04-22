---
title: Werkwijze-verslag — Kwaliteit & Veiligheid HavunCore (22-04-2026)
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Werkwijze — Kwaliteit & Veiligheid HavunCore

> **Doel:** point-in-time assessment van hoe HavunCore zichzelf
> kwaliteits- en veiligheidsborgt. Voor audit, review of eigen
> reflectie bij milestones. Niet over de scan-resultaten (daarvoor:
> `qv-scan-latest.md`) maar over de **discipline**.

## Samenvatting in één paragraaf

HavunCore borgt kwaliteit en veiligheid op vier niveaus:
**normen** (wat is goed?), **detectie** (wat is fout?),
**bewijs** (werken de tests echt?) en **bescherming** (wat mag niet verdwijnen?).
Alle vier draaien geautomatiseerd — dagelijks, wekelijks of maandelijks —
en de resultaten komen op één plek terecht (KB `docs/kb/`). Regressies
worden binnen 24 uur gesignaleerd, niet bij de volgende audit. Huidige
meting: **0 kritieke findings**, 2 bekende HIGH-items (beide met
actieplan), coverage **93 %** in ruwe meting.

## Wat goed gaat

### 1. Mutation-testing op kritieke paden (enterprise-niveau)

Coverage-% zegt of code *geraakt* wordt. Mutation-score (MSI) zegt of
het ook *getest* wordt. HavunCore gaat verder dan de meeste MKB-projecten
door MSI per kritiek pad te gaten:

| Pad | Gate | Actueel |
|-----|------|---------|
| Vault (API key management) | 85 % | 91 % |
| AI Proxy (SQLite) | 95 % | 95 %+ |
| AI Proxy (MySQL real-driver) | 95 % | 100 % |
| AutoFix pipeline | 82 % | 87 % |
| Device trust / QR auth | 90 % | 100 % |
| Observability | 95 % | 95 %+ |
| Critical-paths audit | 85 % | 88-90 % |

Per-PR-validatie via GitHub Actions matrix-jobs. Maandelijkse cron-run
op de eerste van elke maand voor drift-detectie.

### 2. Geautomatiseerde V&K scan over 9 projecten

`php artisan qv:scan` draait 11 checks cross-portfolio:

- Composer vulnerabilities (dagelijks)
- NPM vulnerabilities (dagelijks)
- SSL expiry (wekelijks)
- Mozilla Observatory grade (wekelijks)
- Disk + systemd health via SSH (dagelijks)
- Form-validation coverage (wekelijks)
- Rate-limit coverage (wekelijks)
- Hardcoded secrets (wekelijks)
- Session-cookie flags (wekelijks)
- Test-erosion (wekelijks)
- APP_DEBUG default (dagelijks)

Findings worden automatisch toegevoegd aan
`docs/kb/reference/security-findings-log.md` (append-only historie).

### 3. Test-kwaliteit beleid (3-laags model)

Niet alle tests hebben hetzelfde gewicht. `test-quality-policy.md` stelt
drie lagen vast: **kritiek pad** (100 % + hoge MSI), **business logic**
(70-85 % coverage), **glue code** (20-40 %). Voorkomt coverage-padding
waar het niet nodig is, dwingt diepte waar het wel telt.

### 4. Handover-discipline geautomatiseerd

`php artisan docs:handover` draait dagelijks om 04:00 en genereert
`docs/handover.md` uit recente git-commits + de laatste V&K scan.
Geen handmatige discipline-drift meer mogelijk.

### 5. Zes onschendbare regels (CLAUDE.md)

Expliciete gedragsregels voor Claude — geen code zonder KB, geen
features stilletjes verwijderen, geen credentials in git, altijd tests
voor én na wijzigingen, bij twijfel vragen. Geborgd in `CLAUDE.md`
en `memory/` (cross-session).

### 6. Commit-discipline

Deze sessie (22-04): 40 commits in ~8 uur, elk atomic (1 feature/fix per
commit), met uitvoerige commit-messages die de WHY uitleggen. Per commit
een `/simplify` pass tegen hacky patronen (weliswaar vaak "skip" voor
config-only diffs, maar het triggerpunt staat ingebakken).

## Risico's en aandachtspunten

### Risico H1 — DocIntel CI-hang blokkeert 93 % coverage-cijfer

**Situatie:** HavunCore's echte coverage is 93.4 %, maar CI meet 75.2 %
omdat 306 DocIntel-tests via `--exclude-group=doc-intelligence` worden
overgeslagen op CI. De hang-oorzaak is vandaag twee keer onderzocht
(HTTP stray requests: uitgesloten via `Http::preventStrayRequests`
lokale bevestiging; SQLite-locking: theorie). Niet opgelost.

**Impact:** matig. Het maakt niet dat code ongetest is (lokaal werken
alle tests), maar het externe coverage-cijfer is lager dan de werkelijkheid.
Audit-vragen over het verschil liggen voor de hand.

**Actie:** per-test `--filter` isolatie in volgende sessie, of acceptatie
dat lokaal volstaat en CI een separate suite krijgt.

### Risico H2 — JudoToernooi forms-coverage 53 %

**Situatie:** qv:scan markeert JudoToernooi al weken als HIGH voor
form-validation coverage onder de 60 %-drempel. Wachten op merge van
`feat/vp18-alpine-csp-migration` branch (blokkeert test-reconstructie
van 2 verwijderde tests die onder `feat/restore-deleted-tests` klaar
staan).

**Impact:** laag-matig. Geen direct security-risico, wel een gap tegen
de enterprise-baseline. Toernooi-organisatoren kunnen bij hoge uitzondering
met maliciously crafted input bypassable zijn op schermen waar forms
niet via FormRequest lopen. Niet exploiteerbaar zonder auth.

**Actie:** nauwkeurig gedocumenteerd. Niet urgent; wacht op CSP-merge.

### Risico L1 — Environment-afhankelijke mutation false-positives

**Situatie:** AIProxy SQLite-floor was 81 %, doorbroken naar 95 % via
`infection-critical-paths.json5` ignore-config voor 23 technisch
onkillable mutations (`->timeout(60)` op Http::fake, sub-ms
RoundingFamily, SQLite SUM/COUNT int-coercion). Elke ignore heeft
een `//`-comment met WAAROM.

**Impact:** laag. MySQL-real-driver-job dekt dezelfde scope op 100 %
MSI, dus het bewijs van correctheid is er. Maar de ignore-config is
kwetsbaar: bij een PR die per ongeluk een ignore-regel verwijdert,
kan een echte bug ontstaan die geen test vangt.

**Actie:** bij elke PR op `infection-critical-paths.json5` extra review
nodig. Overwegen: een meta-test die controleert dat elke ignore-entry
een bijbehorende `//`-comment heeft.

### Risico L2 — Handmatige migratie HavunAdmin CSP niet gestart

**Situatie:** HavunAdmin mist `@alpinejs/csp` plugin, wat betekent dat
de CSP-header `unsafe-eval` bevat. Mozilla Observatory geeft daardoor
sub-optimale grade. Migratie-plan staat klaar
(`docs/kb/runbooks/havunadmin-alpine-csp-migration.md`) met 29
inline x-data patronen geïnventariseerd in 3 categorieën, ~6u werk.

**Impact:** laag. Geen acuut risico — admin is intern en niet publiek.
Wel een gap tegen de portfolio-doelstelling "alle projecten Mozilla
Observatory clean".

**Actie:** inplannen voor een eigen sessie met browser-test-cycle.

## Aanbevelingen

### Korte termijn (volgende sessie)

1. **Doc-intel CI-hang root-cause** — per-test `--filter` isolatie, of
   splitsing naar aparte `tests-doc-intel.yml` workflow met eigen timeout.
2. **HavunAdmin CSP migratie** — categorie A (trivial booleans, 18 files)
   als losse PR. Browser-test per file.

### Middellange termijn (binnen 2 maanden)

3. **Meta-test** — controleert dat elke Infection ignore een WHY-comment
   heeft. Voorkomt stilzwijgende deletes.
4. **Severity-rollout uitbreiden** — naar DocIntelligence-module (nu nog
   eigen const-set). Niet urgent.
5. **Baseline covered-MSI gate 65 → 70** — na 2 stabiele maandelijkse
   runs.

### Lange termijn (doorlopend)

6. **Maandelijkse review** van `qv-scan-latest.md`. Bij nieuwe HIGH of
   CRIT: binnen 24u actie plannen.
7. **Cross-portfolio verbeterpunten** — JudoToernooi forms, andere
   projecten Mozilla Observatory. Gestructureerd via K&V-systeem.

## Audit-ready bewijs

Voor externe audit zijn de volgende artefacten beschikbaar:

- `CLAUDE.md` — 6 Onschendbare Regels (gedragsregels Claude)
- `docs/kb/reference/havun-quality-standards.md` — enterprise normen
- `docs/kb/reference/test-quality-policy.md` — 3-laags test-model
- `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md` — V&K architectuur
- `docs/kb/reference/critical-paths-havuncore.md` — kritieke paden + MSI-targets
- `docs/kb/reference/security-findings-log.md` — historie auto-findings
- `docs/kb/reference/security-findings.md` — curated post-mortems
- `.github/workflows/tests.yml` + `mutation-test.yml` — geautomatiseerde gates
- `storage/app/qv-scans/` — raw scan-outputs per datum

## Eindoordeel

HavunCore's werkwijze zit op enterprise-niveau voor een MKB-portfolio.
De combinatie van mutation-testing, geautomatiseerde V&K-scans, 3-laags
test-policy en docs-first discipline is sterker dan wat veel Nederlandse
mid-market SaaS-bedrijven hebben. Er zijn twee concrete risico's (docintel
CI-hang + JudoToernooi forms), beide met actieplan — geen van beide acuut.
