---
title: qv:scan latest report (auto-generated)
type: reference
scope: alle-projecten
generated_from: qv-scans/2026-04-19/run-153814576-28536.json
generated_at: 2026-04-19T15:38:14+02:00
---

# qv:scan — laatste run (auto-generated)

> Dit bestand wordt overschreven door `php artisan qv:log` na elke scan.
> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md` (handmatig).

**Started:** 2026-04-19T15:38:14+02:00  
**Projects:** havunadmin, herdenkingsportaal, studieplanner, judotoernooi, infosyst, safehavun, havuncore, server-prod  
**Checks:** forms

## Totals

| Severity | Count |
|---|---|
| critical | 0 |
| high | 1 |
| medium | 0 |
| low | 0 |
| informational | 0 |
| errors | 0 |

## HIGH / CRITICAL findings

| Project | Check | Severity | Package / Host | Advisory / Title |
|---|---|---|---|---|
| judotoernooi | forms | high | — | Form validation coverage 52% (111/212 write-routes) |

## Next actions

- HIGH/CRITICAL in de tabel hierboven → onderzoek, fix, en documenteer in `security-findings.md`.
- Na een fix: laat deze file automatisch worden overschreven door de volgende `qv:scan` + `qv:log`.
