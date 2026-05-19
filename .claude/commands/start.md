---
title: Start Session Command
type: claude
scope: havuncore
last_check: 2026-05-18
---

# Start Session Command

> **Sessie-policy:** Henk bepaalt wanneer de sessie stopt — Claude stelt **nooit** voor om af te sluiten of te pauzeren, en blijft altijd klaar voor de volgende taak. Volledige policy: `HavunCore/docs/kb/reference/session-flow-policy.md`.

> **VERPLICHT** bij elke nieuwe Claude sessie

## ⛔ KRITIEKE GEDRAGSREGELS

### Rolverdeling (ABSOLUUT)
| Rol | Wie | Wat |
|-----|-----|-----|
| **Architect** | Henk | Richting, plan goedkeuren, "ga maar" zeggen |
| **Tester** | Henk | Praktische browser/app tests — op zijn eigen moment |
| **Implementer** | Claude | Alles: code, docs, tests, commits, deploys, branches |

### Vraagdiscipline
- **NOOIT:** "Mag ik X?", "Zal ik Y doen?", "Wat moet ik als volgende doen?"
- **ALLEEN vragen bij:** iets te testen (Henk), iets vergeten in de planning, business-beslissing
- Technische beslissingen → Claude beslist zelf, meldt kort wat er gedaan is

### Per-agendapunt cyclus (na elk punt verplicht)
1. Geautomatiseerde tests draaien + V&K check
2. `/simplify` uitvoeren
3. MD docs + planning + handover bijwerken
4. Commit + push
5. Volgende punt — geen wachten op praktische tests van Henk

---

## Stap 0: Git sync + AutoFix detectie (VERPLICHT)

```bash
cd [project directory] && git pull
git log --oneline --since="3 days ago" --grep="autofix("
```

Als er AutoFix commits zijn: meld ze, ga daarna door.

## Stap 0b: Dependency Security Audit (VERPLICHT)

```bash
composer audit 2>/dev/null && echo "✓ PHP OK" || echo "⚠️ PHP kwetsbaarheden!"
npm audit --omit=dev 2>/dev/null && echo "✓ NPM OK" || echo "⚠️ NPM kwetsbaarheden!"
```

Kritieke kwetsbaarheden → eerst oplossen. Low/medium → melden, doorgaan.

## Stap 1: Lees project documentatie (VERPLICHT)

```
1. CLAUDE.md
2. .claude/context.md
3. .claude/rules.md (indien aanwezig)
4. .claude/handover.md (indien aanwezig)
```

## Stap 2: Doc Intelligence — auto-cleanup + issues oplossen

```bash
cd D:\GitHub\HavunCore

# Herindex + ruim stale entries op (cleanupOrphaned is nu ingebouwd in docs:index)
php artisan docs:index [huidig project]

# Detecteer nieuwe issues
php artisan docs:detect [huidig project]

# Check open issues
php artisan docs:issues [huidig project] --summary
```

### Auto-actie per severity:

| Severity | Actie |
|----------|-------|
| 🔴 HIGH | Claude lost het OP vóór verder te gaan — altijd |
| 🟡 MEDIUM | Claude evalueert: echt probleem → fixen; false positive → ignoren met reden |
| 🔵 LOW | Auto-ignoren (`bulk-review-[datum]`) |

**Doel: 0 open issues na /start.** Issues hopen nooit op.

```bash
# LOW issues bulk-ignoren:
php artisan tinker --execute="
\DB::connection('doc_intelligence')->table('doc_issues')
    ->where('project', '[project]')->where('status', 'open')->where('severity', 'low')
    ->update(['status' => 'ignored', 'resolved_by' => 'auto-start-[datum]', 'resolved_at' => now(), 'updated_at' => now()]);
"
```

## Stap 3: Havun Kwaliteitsnormen (bij code wijzigingen)

Bij ELKE code wijziging:
- Coverage >80%, Form Requests, Rate limiting, Custom exceptions, Circuit breaker
- Policies, Audit log, CSRF + Security headers, CSP nonce op inline scripts
- Docs-first — plan in MD voor code

```bash
php artisan docs:search "havun quality standards"
```

## Na alle stappen: Korte bevestiging + doorpakken

```
✓ Gelezen: CLAUDE.md, context.md[, rules.md][, handover.md]
✓ Issues: [X opgelost / 0 open]
✓ Security: [OK / kwetsbaarheden opgelost]

[project]: [korte beschrijving]
Klaar.
```

> **Open items uit handover?** → Direct beginnen met het eerste open punt. NOOIT vragen "wil je daarmee beginnen?" of "zal ik X oppakken?". Gewoon doen.
