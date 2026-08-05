---
title: qv:scan latest report (auto-generated)
type: reference
scope: alle-projecten
generated_from: 16 runs uit de laatste 8 dagen (backup-coverage, cargo, composer, debug-mode, deps-coverage, forms, npm, observatory, ratelimit, registries, residu, secrets, server, session-cookies, ssl, test-erosion)
generated_at: 2026-08-05T03:24:03+02:00
---

# qv:scan — laatste run (auto-generated)

> Dit bestand wordt overschreven door `php artisan qv:log` na elke scan.
> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md` (handmatig).

**Started:** 2026-08-05T03:24:03+02:00  
**Projects:** havun, havunadmin, havunclub, havuncore, havuncore-webapp, herdenkingsportaal, infosyst, judotoernooi, safehavun, server-prod, studieplanner, studieplanner-api, studieplanner-mobile, veen-ledenadministratie, vpdupdate, vusista2  
**Checks:** backup-coverage, cargo, composer, debug-mode, deps-coverage, forms, npm, observatory, ratelimit, registries, residu, secrets, server, session-cookies, ssl, test-erosion

## Totals

| Severity | Count |
|---|---|
| critical | 0 |
| high | 23 |
| medium | 57 |
| low | 7 |
| informational | 5 |
| errors | 0 |

## HIGH / CRITICAL findings

| Project | Check | Severity | Package / Host | Advisory / Title |
|---|---|---|---|---|
| havunadmin | composer | high | guzzlehttp/guzzle | PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks |
| herdenkingsportaal | composer | high | guzzlehttp/guzzle | PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks |
| studieplanner-api | composer | high | guzzlehttp/guzzle | PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks |
| studieplanner-api | composer | high | laravel/framework | PKSA-3r5d-mb8f-1qw9 — Laravel Framework: CRLF injection in default email rule  |
| studieplanner-api | composer | high | phpunit/phpunit | PKSA-z3gr-8qht-p93v — Unsafe Deserialization in PHPT Code Coverage Handling |
| studieplanner-api | composer | high | symfony/http-kernel | PKSA-dw7n-x7f5-zf63 — CVE-2026-45075: HEAD Request Bypasses methods: ['GET'] Filter in #[IsGranted] / #[IsSignatureValid] / #[IsCsrfTokenValid] |
| studieplanner-api | composer | high | symfony/mime | PKSA-2n2k-66v2-bwg3 — CVE-2026-45067: Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\Address |
| studieplanner-api | composer | high | web-token/jwt-library | PKSA-237v-kv6c-dpkr — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle |
| studieplanner-api | composer | high | web-token/jwt-library | PKSA-66dc-42nb-26yy — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption |
| judotoernooi | composer | high | guzzlehttp/guzzle | PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks |
| judotoernooi | composer | high | phpoffice/phpspreadsheet | PKSA-r22k-87hv-mfk4 — PHPSpreadsheet: XLS/OLE sector-chain self-loop causes memory exhaustion |
| judotoernooi | composer | high | phpoffice/phpspreadsheet | PKSA-m9cr-9614-rsf7 — PHPSpreadsheet: Gnumeric reader unbounded gzip expansion causes memory exhaustion |
| judotoernooi | composer | high | phpoffice/phpspreadsheet | PKSA-dqzt-yst9-1w9y — PHPSpreadsheet: SSRF bypass via HTTP redirect in WEBSERVICE() domain whitelist |
| safehavun | composer | high | guzzlehttp/guzzle | PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks |
| safehavun | composer | high | laravel/framework | PKSA-3r5d-mb8f-1qw9 — Laravel Framework: CRLF injection in default email rule  |
| safehavun | composer | high | web-token/jwt-library | PKSA-237v-kv6c-dpkr — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle |
| safehavun | composer | high | web-token/jwt-library | PKSA-66dc-42nb-26yy — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption |
| judotoernooi | deps-coverage | high | — | Dependencies niet gemeten: python |
| havun | npm | high | next | Next.js: Middleware / Proxy bypass in App Router applications using Turbopack and single locale |
| havun | npm | high | postcss | PostCSS has XSS via Unescaped </style> in its CSS Stringify Output |
| havun | npm | high | sharp | sharp inherited vulnerabilities in libvips: CVE-2026-33327, CVE-2026-33328, CVE-2026-35590, CVE-2026-35591 |
| vpdupdate | npm | high | xlsx | Prototype Pollution in sheetJS |
| safehavun | observatory | high | safehavun.havun.nl | Observatory grade C (score 50, minimum B) |

## Wanneer elke check draaide

| Check | Laatste run |
|---|---|
| backup-coverage | 2026-08-04T05:30:04+02:00 |
| cargo | 2026-08-05T03:22:02+02:00 |
| composer | 2026-08-05T03:07:03+02:00 |
| debug-mode | 2026-08-04T03:57:03+02:00 |
| deps-coverage | 2026-08-05T03:24:03+02:00 |
| forms | 2026-08-04T04:57:03+02:00 |
| npm | 2026-08-05T03:17:03+02:00 |
| observatory | 2026-08-03T04:37:03+02:00 |
| ratelimit | 2026-07-29T05:07:03+02:00 |
| registries | 2026-08-05T03:02:02+02:00 |
| residu | 2026-08-02T05:47:03+02:00 |
| secrets | 2026-07-30T05:17:03+02:00 |
| server | 2026-08-04T03:47:03+02:00 |
| session-cookies | 2026-07-31T05:27:03+02:00 |
| ssl | 2026-08-03T04:07:03+02:00 |
| test-erosion | 2026-08-01T05:37:02+02:00 |

## Next actions

- HIGH/CRITICAL in de tabel hierboven → onderzoek, fix, en documenteer in `security-findings.md`.
- Na een fix: laat deze file automatisch worden overschreven door de volgende `qv:scan` + `qv:log`.
