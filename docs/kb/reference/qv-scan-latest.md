---
title: qv:scan latest report (auto-generated)
type: reference
scope: alle-projecten
generated_from: qv-scans/2026-05-02/run-160733140-5060.json
generated_at: 2026-05-02T16:06:41+02:00
---

# qv:scan — laatste run (auto-generated)

> Dit bestand wordt overschreven door `php artisan qv:log` na elke scan.
> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md` (handmatig).

**Started:** 2026-05-02T16:06:41+02:00  
**Projects:** havunadmin, herdenkingsportaal, studieplanner, judotoernooi, infosyst, safehavun, havuncore, studieplanner-mobile, havunvet, munus, server-prod  
**Checks:** composer, npm, ssl, observatory, server, forms, ratelimit, secrets, session-cookies, test-erosion, debug-mode

## Totals

| Severity | Count |
|---|---|
| critical | 0 |
| high | 5 |
| medium | 19 |
| low | 0 |
| informational | 0 |
| errors | 0 |

## HIGH / CRITICAL findings

| Project | Check | Severity | Package / Host | Advisory / Title |
|---|---|---|---|---|
| judotoernooi | composer | high | phpoffice/phpspreadsheet | PKSA-gz3f-3cz3-3wsw — PhpSpreadsheet has CPU Denial of Service via Unbounded Row Number in XLSX Row Dimensions |
| judotoernooi | composer | high | phpoffice/phpspreadsheet | PKSA-x13r-n4wc-4gcr — PhpSpreadsheet has CPU Denial of Service via Unbounded Row Index in SpreadsheetML XML Reader |
| judotoernooi | composer | high | phpoffice/phpspreadsheet | PKSA-8cfg-tzhf-fr83 — PhpSpreadsheet has SSRF/RCE in IOFactory::load when $filename is user controlled |
| judotoernooi | forms | high | — | Form validation coverage 53% (112/213 write-routes) |
| havunvet | session-cookies | high | — | 1 session-cookie flag(s) not securely set |

## Next actions

- HIGH/CRITICAL in de tabel hierboven → onderzoek, fix, en documenteer in `security-findings.md`.
- Na een fix: laat deze file automatisch worden overschreven door de volgende `qv:scan` + `qv:log`.
