---
title: qv:scan latest report (auto-generated)
type: reference
scope: alle-projecten
generated_from: qv-scans/2026-04-22/run-124042131-7744.json
generated_at: 2026-04-22T12:39:51+02:00
---

# qv:scan — laatste run (auto-generated)

> Dit bestand wordt overschreven door `php artisan qv:log` na elke scan.
> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md` (handmatig).

**Started:** 2026-04-22T12:39:51+02:00  
**Projects:** havunadmin, herdenkingsportaal, studieplanner, judotoernooi, infosyst, safehavun, havuncore, studieplanner-mobile, server-prod  
**Checks:** composer, npm, ssl, observatory, server, forms, ratelimit, secrets, session-cookies, test-erosion, debug-mode

## Totals

| Severity | Count |
|---|---|
| critical | 0 |
| high | 2 |
| medium | 0 |
| low | 0 |
| informational | 0 |
| errors | 0 |

## HIGH / CRITICAL findings

| Project | Check | Severity | Package / Host | Advisory / Title |
|---|---|---|---|---|
| herdenkingsportaal | test-erosion | high | — | 1 test file(s) deleted in last 30 days |
| judotoernooi | forms | high | — | Form validation coverage 53% (112/213 write-routes) |

## Next actions

- HIGH/CRITICAL in de tabel hierboven → onderzoek, fix, en documenteer in `security-findings.md`.
- Na een fix: laat deze file automatisch worden overschreven door de volgende `qv:scan` + `qv:log`.
