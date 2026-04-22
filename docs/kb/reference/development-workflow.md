---
title: Development Workflow — Van Cursor tot Productie
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Development Workflow — Van Cursor tot Productie

> Compleet overzicht van de Havun ontwikkelworkflow.
> Van idee in je hoofd tot draaiende code op de server.

---

## Visueel Schema

```
┌─────────────────────────────────────────────────────────────────┐
│                    ONTWIKKELAAR (Henk)                           │
│                                                                 │
│  Idee / Bug / Feature request                                   │
│         │                                                       │
│         ▼                                                       │
│  ┌─────────────────┐                                            │
│  │  VS Code + Claude│  ← Claude Code Extension (Opus 4.6, 1M)  │
│  │  Code Extension  │                                           │
│  └────────┬────────┘                                            │
│           │                                                     │
│           ▼                                                     │
│  ┌─────────────────┐     ┌──────────────────────┐               │
│  │   /start         │────▶│  CLAUDE.md (60 regels)│              │
│  │   (sessie begin) │     │  context.md (100 r.)  │              │
│  └────────┬────────┘     └──────────────────────┘               │
│           │                                                     │
│           ▼                                                     │
│  ┌─────────────────┐     ┌──────────────────────┐               │
│  │  KB Zoeken       │────▶│  docs:search "term"   │              │
│  │  (on-demand)     │     │                       │              │
│  │                  │     │  Ollama (11434)        │              │
│  │                  │     │  nomic-embed-text      │              │
│  │                  │     │  768-dim vectors       │              │
│  │                  │     │         │              │              │
│  │                  │     │         ▼              │              │
│  │                  │     │  SQLite doc_intelligence│             │
│  │                  │     │  (cosine similarity)   │              │
│  │                  │     │         │              │              │
│  │                  │◀────│  Top 5 resultaten      │              │
│  └────────┬────────┘     └──────────────────────┘               │
└───────────┼─────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    ONTWIKKELFASE                                 │
│                                                                 │
│  ┌─────────────────┐                                            │
│  │  DOCS-FIRST      │                                            │
│  │                  │                                            │
│  │  GROOT?          │                                            │
│  │  ├─ Zoek docs    │                                            │
│  │  ├─ Meld aan user│                                            │
│  │  ├─ Wacht op OK  │                                            │
│  │  ├─ Update docs  │                                            │
│  │  └─ Dan pas code │                                            │
│  │                  │                                            │
│  │  KLEIN?          │                                            │
│  │  ├─ Log smallwork│                                            │
│  │  └─ Fix direct   │                                            │
│  └────────┬────────┘                                            │
│           │                                                     │
│           ▼                                                     │
│  ┌─────────────────┐                                            │
│  │  php artisan test│  ← VOOR wijziging (baseline)              │
│  │  of npm test     │                                            │
│  └────────┬────────┘                                            │
│           │                                                     │
│           ▼                                                     │
│  ┌─────────────────┐     ┌──────────────────────┐               │
│  │  CODE SCHRIJVEN  │     │  5 Beschermingslagen: │              │
│  │                  │     │  1. MD docs            │              │
│  │  • Atomair       │     │  2. DO NOT REMOVE /    │              │
│  │  • Kwaliteit >   │     │     .integrity.json    │              │
│  │    snelheid      │     │  3. Tests + Linter-Gate│              │
│  │  • SaaS-mindset  │     │  4. CLAUDE.md + Recent │              │
│  │                  │     │     Regressions (7d)   │              │
│  │                  │     │  5. Memory (cross-sess)│              │
│  └────────┬────────┘     └──────────────────────┘               │
│           │                                                     │
│           ▼                                                     │
│  ┌─────────────────┐                                            │
│  │  php artisan test│  ← NA wijziging (regressie check)         │
│  │  of npm test     │                                            │
│  └────────┬────────┘                                            │
│           │                                                     │
│       Tests groen?                                              │
│       ├─ NEE → Fix code (niet de test!)                         │
│       └─ JA ↓                                                   │
└───────────┼─────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    /end (SESSIE AFSLUITING)                      │
│                                                                 │
│  ┌─────────────────┐                                            │
│  │  1. Smallwork    │  ← Review kleine fixes                    │
│  │     review       │                                            │
│  └────────┬────────┘                                            │
│           ▼                                                     │
│  ┌─────────────────┐                                            │
│  │  2. MD docs      │  ← Handover, context bijwerken            │
│  │     bijwerken    │                                            │
│  └────────┬────────┘                                            │
│           ▼                                                     │
│  ┌─────────────────┐                                            │
│  │  3. Linter-Gate  │  ← VERPLICHT                              │
│  │                  │                                            │
│  │  • php artisan   │                                            │
│  │    test (junit)  │                                            │
│  │  • integrity     │                                            │
│  │    check         │                                            │
│  │  • regression    │                                            │
│  │    test bij fix? │                                            │
│  └────────┬────────┘                                            │
│           │                                                     │
│       Alles groen?                                              │
│       ├─ NEE → Fix eerst!                                       │
│       └─ JA ↓                                                   │
│           ▼                                                     │
│  ┌─────────────────┐                                            │
│  │  4. Git commit   │  ← Atomair, beschrijvend                  │
│  │     + push       │                                            │
│  └────────┬────────┘                                            │
└───────────┼─────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    GITHUB                                        │
│                                                                 │
│  ┌─────────────────┐                                            │
│  │  GitHub Actions  │  ← Automatisch bij push                   │
│  │  CI Pipeline     │                                            │
│  │                  │                                            │
│  │  Laravel:        │                                            │
│  │  • composer inst.│                                            │
│  │  • SQLite setup  │                                            │
│  │  • php artisan   │                                            │
│  │    test          │                                            │
│  │  • composer audit│                                            │
│  │  • integrity chk │                                            │
│  │                  │                                            │
│  │  Expo:           │                                            │
│  │  • npm ci        │                                            │
│  │  • npm test      │                                            │
│  │  • tsc --noEmit  │                                            │
│  │  • integrity chk │                                            │
│  └────────┬────────┘                                            │
│           │                                                     │
│       CI groen?                                                 │
│       ├─ NEE → GitHub toont ❌, fix nodig                       │
│       └─ JA ↓                                                   │
└───────────┼─────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PRODUCTIE SERVER                              │
│                    (188.245.159.115)                              │
│                                                                 │
│  ┌─────────────────┐                                            │
│  │  git pull        │                                            │
│  │  php artisan     │                                            │
│  │    migrate       │                                            │
│  │  php artisan     │                                            │
│  │    config:clear  │                                            │
│  │  php artisan     │                                            │
│  │    cache:clear   │                                            │
│  └────────┬────────┘                                            │
│           │                                                     │
│           ▼                                                     │
│  ┌─────────────────┐     ┌──────────────────────┐               │
│  │  LIVE!           │     │  AutoFix (bewaking)   │              │
│  │                  │     │                       │              │
│  │  nginx serving   │     │  500 error?           │              │
│  │  Laravel app     │     │  → AI analyse         │              │
│  │                  │     │  → Syntax check       │              │
│  │                  │     │  → Auto-rollback       │              │
│  │                  │     │  → Git commit+push    │              │
│  │                  │     │  → Email notificatie  │              │
│  └─────────────────┘     └──────────────────────┘               │
│                                                                 │
│  ┌─────────────────┐                                            │
│  │  KB Auto-update  │  ← Windows Task Scheduler                 │
│  │  08:03 + 20:07   │     docs:index all --force                │
│  │  (lokaal, Ollama)│     docs:detect                           │
│  └─────────────────┘                                            │
│                                                                 │
│  ┌─────────────────┐                                            │
│  │  Server cron     │  ← Elke 6 uur                             │
│  │  docs:index all  │     TF-IDF (geen Ollama)                  │
│  │  --no-code       │                                            │
│  └─────────────────┘                                            │
└─────────────────────────────────────────────────────────────────┘
```

## Samenvatting Flow

```
Idee → VS Code → /start → KB zoeken → Docs-first → Code
  → Test VOOR → Code schrijven → Test NA → /end
  → Linter-Gate → Integrity check → Git commit+push
  → GitHub Actions CI → Productie deploy → AutoFix bewaking
  → KB auto-update (2x per dag)
```

## Checkpoints (waar kan het stoppen?)

| Checkpoint | Wanneer stopt het? |
|-----------|-------------------|
| Docs-first | Inconsistenties gevonden → meld, wacht op OK |
| Test VOOR | Bestaande tests falen → fix eerst |
| Test NA | Nieuwe code breekt tests → fix code |
| Linter-Gate | Tests/integrity faalt → fix voor commit |
| GitHub Actions | CI faalt → fix en push opnieuw |
| AutoFix | 500 error → auto-fix OF notificatie |

---

*Aangemaakt: 29 maart 2026*
