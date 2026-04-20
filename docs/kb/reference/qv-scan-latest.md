---
title: qv:scan latest report (auto-generated)
type: reference
scope: alle-projecten
generated_from: qv-scans/2026-04-20/run-083351666-31544.json
generated_at: 2026-04-20T08:33:21+02:00
---

# qv:scan — laatste run (auto-generated)

> Dit bestand wordt overschreven door `php artisan qv:log` na elke scan.
> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md` (handmatig).

**Started:** 2026-04-20T08:33:21+02:00  
**Projects:** havunadmin, herdenkingsportaal, studieplanner, judotoernooi, infosyst, safehavun, havuncore, server-prod  
**Checks:** composer, npm, ssl, observatory, server, forms, ratelimit, secrets, session-cookies, test-erosion, debug-mode

## Totals

| Severity | Count |
|---|---|
| critical | 0 |
| high | 5 |
| medium | 0 |
| low | 0 |
| informational | 0 |
| errors | 7 |

## HIGH / CRITICAL findings

| Project | Check | Severity | Package / Host | Advisory / Title |
|---|---|---|---|---|
| herdenkingsportaal | test-erosion | high | — | 1 test file(s) deleted in last 30 days |
| herdenkingsportaal | test-erosion | high | — | 18 unconditional markTestSkipped calls (threshold 10) |
| judotoernooi | forms | high | — | Form validation coverage 52% (111/212 write-routes) |
| judotoernooi | session-cookies | high | — | 1 session-cookie flag(s) not securely set |
| judotoernooi | test-erosion | high | — | 16 unconditional markTestSkipped calls (threshold 10) |

## Scanner errors

| Project | Check | Message |
|---|---|---|
| havunadmin | observatory | Observatory returned HTTP 400 for havunadmin.havun.nl |
| herdenkingsportaal | observatory | Observatory returned HTTP 400 for herdenkingsportaal.nl |
| studieplanner | observatory | Observatory returned HTTP 400 for studieplanner-api.havun.nl |
| judotoernooi | observatory | Observatory returned HTTP 400 for judotoernooi.havun.nl |
| infosyst | observatory | Observatory returned HTTP 400 for infosyst.havun.nl |
| safehavun | observatory | Observatory returned HTTP 400 for safehavun.havun.nl |
| havuncore | observatory | Observatory returned HTTP 400 for havuncore.havun.nl |

## Next actions

- HIGH/CRITICAL in de tabel hierboven → onderzoek, fix, en documenteer in `security-findings.md`.
- Na een fix: laat deze file automatisch worden overschreven door de volgende `qv:scan` + `qv:log`.
