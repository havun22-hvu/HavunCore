---
title: Security findings loggen in KB
type: runbook
scope: alle-projecten
last_check: 2026-04-18
---

# Security findings & bug-fixes loggen in de KB

> **Kernregel:** elke online security-scan die iets vindt, én elke code-fout die pas in tests/productie blijkt, levert een KB-entry op — zodat toekomstige sessies niet dezelfde ontdekking opnieuw hoeven te doen.

## Waarom

- Claude heeft geen geheugen tussen sessies behalve wat in KB + memory staat.
- Een CVE-fix of regressie-oplossing die niet vastgelegd is → volgende sessie doet 't opnieuw.
- Pattern-herkenning ontstaat alleen als findings geordend worden.

## Wanneer loggen

| Trigger | Loggen? | Waar |
|---------|---------|------|
| `composer audit` / `npm audit` → CVE gevonden | ✅ | `docs/kb/reference/security-findings.md` |
| Mozilla Observatory score verandert | ✅ | `project_mozilla_observatory_status.md` (memory) |
| SSL Labs / Qualys bevinding | ✅ | `docs/kb/reference/security-findings.md` |
| OWASP ZAP / Burp scan hit | ✅ | `docs/kb/reference/security-findings.md` |
| Pentest-bevinding | ✅ | `docs/kb/reference/pentest-log.md` |
| Code-regressie (test rood geworden door dep-bump, framework-upgrade, etc.) | ✅ | `docs/kb/patterns/[onderwerp].md` |
| Productie-incident (5xx spike, user report) | ✅ | `docs/kb/reference/incidents.md` |
| Browser quirk (zoals `w-[X%]` Tailwind arbitrary) | ✅ | `docs/kb/patterns/frontend-gotchas.md` |
| Verlopen token / auth-fout / config drift | ✅ | betreffend runbook |
| Alleen `composer outdated` zonder CVE | ❌ | overslaan (ruis) |

## Structuur van een finding-entry

```markdown
### [DATUM] [project] [korte titel]

**Bron:** composer audit / Mozilla Observatory / OWASP ZAP / productie-log / ...
**Severity:** HIGH / MEDIUM / LOW / informational
**CVE/Advisory:** GHSA-xxxx-xxxx-xxxx (indien van toepassing)

**Probleem (1-2 regels):**
Wat er mis was, in niet-technische taal als het kan.

**Oorzaak (als bekend):**
Waarom dit kon gebeuren — root cause, niet symptoom.

**Fix:**
Concrete wijziging — commit-hash, package-versie, config-regel.

**Lessen / patroon:**
Wat volgende sessie hier aan kan hebben. Is dit een herhalend patroon?
Staat er nu een regressietest? Hoort dit in CI-check?

**Validatie:**
Hoe is bevestigd dat de fix werkt — test groen, scan schoon, prod-metric normaal.
```

## Concreet voorbeeld uit deze sessie (2026-04-18)

### Dependency-bumps HavunAdmin + Herdenkingsportaal

**Bron:** `composer audit` bij `/start` hook.
**Severity:** HIGH (phpseclib, phpunit, phpseclib wrapper) + MEDIUM (3×).

**Lessen:**
- **Vendor `.git` dirs blokkeren `composer update`** → 144 packages in HavunAdmin hadden ooit `prefer-source` installs gehad. Fix: `rm -rf vendor && composer install --prefer-dist`. Zelfde-issue in HP (154 dirs). Voeg dit toe aan `troubleshoot.md`.
- **Transitive CVE kan door minor bump van direct dep worden opgelost.** HP `firebase/php-jwt <7.0.0` leek onoplosbaar (via `laravel/socialite ^6.4`), maar `socialite` 5.23 → 5.26 verruimde de jwt-constraint naar `^6|^7`, waardoor jwt meteen naar 7.0.5 ging. **Principe:** probeer altijd `composer update --with-dependencies` vóór een advisory als "onoplosbaar" te bestempelen.
- **Major-bumps (phpunit 11→12, tinker 2→3, predis 2→3) overslaan** in patch-sessie. Vereisen feature-branch + test-suite aanpassingen.

**Patroon voor frontend: Tailwind arbitrary values werken niet runtime**
Zie `frontend-gotchas.md` (wordt bij deze sessie aangemaakt).

## Locaties — single source of truth

| Bestand | Doel |
|---------|------|
| `docs/kb/reference/security-findings.md` | Chronologisch log van alle externe scan hits + fixes |
| `docs/kb/reference/incidents.md` | Productie-incidenten (downtime, 5xx, data-issue) |
| `docs/kb/patterns/frontend-gotchas.md` | Herhalende frontend-val (Tailwind JIT, Alpine CSP, etc.) |
| `docs/kb/patterns/backend-gotchas.md` | Herhalende backend-val (N+1, serialization, lock-contention) |
| memory: `project_mozilla_observatory_status.md` | Observatory-score per project, historie |

## Werkwijze voor Claude

**Vóór** een fix/bump → zoek of dit patroon al gelogd is:
```bash
cd D:/GitHub/HavunCore && php artisan docs:search "trefwoord"
```

**Ná** een succesvolle fix → update of creëer entry:
1. Bron (scan-tool, advisory-URL)
2. Severity + CVE-id
3. 1-regel probleem + 1-regel oorzaak + 1-regel fix
4. 1 regel "les voor volgende keer"
5. Validatie-bewijs (tests groen, scan schoon)

**Niet loggen** als:
- Het een pure styling-tweak is (geen regressie-risico)
- Het een one-time config-keuze is die nergens anders relevant is
- Er al een runbook/pattern exact dit beschrijft (dan alleen timestamp updaten)

## Anti-pattern

❌ **"Dit lossen we later wel op"** zonder KB-entry — volgende sessie weet van niks.
❌ **Findings in commit-message** zonder KB-entry — git log is niet doorzoekbaar zoals KB.
❌ **Per-project security-doc** zonder centrale index — dan moet Claude 9 bestanden lezen.
❌ **Alleen SUCCESSEN loggen** — juist mislukte pogingen + waarom, behoeden volgende poging.
