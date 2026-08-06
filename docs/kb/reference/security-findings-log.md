---
title: qv:scan security findings log (auto-generated, append-only)
type: reference
scope: alle-projecten
---

# qv:scan security findings log

> **Auto-generated.** Elke `php artisan qv:log` voegt HIGH/CRITICAL findings
> toe aan dit bestand. Runs zonder HIGH/CRITICAL worden overgeslagen.
>
> Voor **post-mortem, prose en fix-statussen** zie `security-findings.md`
> (handmatig onderhouden — NIET automatisch bijgewerkt).

## 2026-04-22 12:39

- [HIGH] **[high]** herdenkingsportaal/—: 1 test file(s) deleted in last 30 days
- [HIGH] **[high]** judotoernooi/—: Form validation coverage 53% (112/213 write-routes)

## 2026-04-22 16:38

- [HIGH] **[high]** herdenkingsportaal/—: 1 test file(s) deleted in last 30 days
- [HIGH] **[high]** judotoernooi/—: Form validation coverage 53% (112/213 write-routes)

## 2026-05-02 16:06

- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-gz3f-3cz3-3wsw — PhpSpreadsheet has CPU Denial of Service via Unbounded Row Number in XLSX Row Dimensions
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-x13r-n4wc-4gcr — PhpSpreadsheet has CPU Denial of Service via Unbounded Row Index in SpreadsheetML XML Reader
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-8cfg-tzhf-fr83 — PhpSpreadsheet has SSRF/RCE in IOFactory::load when $filename is user controlled
- [HIGH] **[high]** judotoernooi/—: Form validation coverage 53% (112/213 write-routes)
- [HIGH] **[high]** havunvet/—: 1 session-cookie flag(s) not securely set

## 2026-08-04 03:24

- [HIGH] **[high]** havunadmin/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** herdenkingsportaal/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** studieplanner-api/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** studieplanner-api/laravel/framework: PKSA-3r5d-mb8f-1qw9 — Laravel Framework: CRLF injection in default email rule 
- [HIGH] **[high]** studieplanner-api/phpunit/phpunit: PKSA-z3gr-8qht-p93v — Unsafe Deserialization in PHPT Code Coverage Handling
- [HIGH] **[high]** studieplanner-api/symfony/http-kernel: PKSA-dw7n-x7f5-zf63 — CVE-2026-45075: HEAD Request Bypasses methods: ['GET'] Filter in #[IsGranted] / #[IsSignatureValid] / #[IsCsrfTokenValid]
- [HIGH] **[high]** studieplanner-api/symfony/mime: PKSA-2n2k-66v2-bwg3 — CVE-2026-45067: Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\Address
- [HIGH] **[high]** studieplanner-api/web-token/jwt-library: PKSA-237v-kv6c-dpkr — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle
- [HIGH] **[high]** studieplanner-api/web-token/jwt-library: PKSA-66dc-42nb-26yy — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption
- [HIGH] **[high]** judotoernooi/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-r22k-87hv-mfk4 — PHPSpreadsheet: XLS/OLE sector-chain self-loop causes memory exhaustion
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-m9cr-9614-rsf7 — PHPSpreadsheet: Gnumeric reader unbounded gzip expansion causes memory exhaustion
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-dqzt-yst9-1w9y — PHPSpreadsheet: SSRF bypass via HTTP redirect in WEBSERVICE() domain whitelist
- [HIGH] **[high]** safehavun/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** safehavun/laravel/framework: PKSA-3r5d-mb8f-1qw9 — Laravel Framework: CRLF injection in default email rule 
- [HIGH] **[high]** safehavun/web-token/jwt-library: PKSA-237v-kv6c-dpkr — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle
- [HIGH] **[high]** safehavun/web-token/jwt-library: PKSA-66dc-42nb-26yy — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption
- [HIGH] **[high]** judotoernooi/—: Dependencies niet gemeten: python
- [HIGH] **[high]** havun/next: Next.js: Middleware / Proxy bypass in App Router applications using Turbopack and single locale
- [HIGH] **[high]** havun/postcss: PostCSS has XSS via Unescaped </style> in its CSS Stringify Output
- [HIGH] **[high]** havun/sharp: sharp inherited vulnerabilities in libvips: CVE-2026-33327, CVE-2026-33328, CVE-2026-35590, CVE-2026-35591
- [HIGH] **[high]** vpdupdate/xlsx: Prototype Pollution in sheetJS
- [HIGH] **[high]** safehavun/safehavun.havun.nl: Observatory grade C (score 50, minimum B)

## 2026-08-05 03:24

- [HIGH] **[high]** havunadmin/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** herdenkingsportaal/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** studieplanner-api/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** studieplanner-api/laravel/framework: PKSA-3r5d-mb8f-1qw9 — Laravel Framework: CRLF injection in default email rule 
- [HIGH] **[high]** studieplanner-api/phpunit/phpunit: PKSA-z3gr-8qht-p93v — Unsafe Deserialization in PHPT Code Coverage Handling
- [HIGH] **[high]** studieplanner-api/symfony/http-kernel: PKSA-dw7n-x7f5-zf63 — CVE-2026-45075: HEAD Request Bypasses methods: ['GET'] Filter in #[IsGranted] / #[IsSignatureValid] / #[IsCsrfTokenValid]
- [HIGH] **[high]** studieplanner-api/symfony/mime: PKSA-2n2k-66v2-bwg3 — CVE-2026-45067: Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\Address
- [HIGH] **[high]** studieplanner-api/web-token/jwt-library: PKSA-237v-kv6c-dpkr — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle
- [HIGH] **[high]** studieplanner-api/web-token/jwt-library: PKSA-66dc-42nb-26yy — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption
- [HIGH] **[high]** judotoernooi/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-r22k-87hv-mfk4 — PHPSpreadsheet: XLS/OLE sector-chain self-loop causes memory exhaustion
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-m9cr-9614-rsf7 — PHPSpreadsheet: Gnumeric reader unbounded gzip expansion causes memory exhaustion
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-dqzt-yst9-1w9y — PHPSpreadsheet: SSRF bypass via HTTP redirect in WEBSERVICE() domain whitelist
- [HIGH] **[high]** safehavun/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** safehavun/laravel/framework: PKSA-3r5d-mb8f-1qw9 — Laravel Framework: CRLF injection in default email rule 
- [HIGH] **[high]** safehavun/web-token/jwt-library: PKSA-237v-kv6c-dpkr — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle
- [HIGH] **[high]** safehavun/web-token/jwt-library: PKSA-66dc-42nb-26yy — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption
- [HIGH] **[high]** judotoernooi/—: Dependencies niet gemeten: python
- [HIGH] **[high]** havun/next: Next.js: Middleware / Proxy bypass in App Router applications using Turbopack and single locale
- [HIGH] **[high]** havun/postcss: PostCSS has XSS via Unescaped </style> in its CSS Stringify Output
- [HIGH] **[high]** havun/sharp: sharp inherited vulnerabilities in libvips: CVE-2026-33327, CVE-2026-33328, CVE-2026-35590, CVE-2026-35591
- [HIGH] **[high]** vpdupdate/xlsx: Prototype Pollution in sheetJS
- [HIGH] **[high]** safehavun/safehavun.havun.nl: Observatory grade C (score 50, minimum B)

## 2026-08-06 03:24

- [HIGH] **[high]** havunadmin/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** herdenkingsportaal/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** studieplanner-api/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** studieplanner-api/laravel/framework: PKSA-3r5d-mb8f-1qw9 — Laravel Framework: CRLF injection in default email rule 
- [HIGH] **[high]** studieplanner-api/phpunit/phpunit: PKSA-z3gr-8qht-p93v — Unsafe Deserialization in PHPT Code Coverage Handling
- [HIGH] **[high]** studieplanner-api/symfony/http-kernel: PKSA-dw7n-x7f5-zf63 — CVE-2026-45075: HEAD Request Bypasses methods: ['GET'] Filter in #[IsGranted] / #[IsSignatureValid] / #[IsCsrfTokenValid]
- [HIGH] **[high]** studieplanner-api/symfony/mime: PKSA-2n2k-66v2-bwg3 — CVE-2026-45067: Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\Address
- [HIGH] **[high]** studieplanner-api/web-token/jwt-library: PKSA-237v-kv6c-dpkr — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle
- [HIGH] **[high]** studieplanner-api/web-token/jwt-library: PKSA-66dc-42nb-26yy — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption
- [HIGH] **[high]** judotoernooi/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-r22k-87hv-mfk4 — PHPSpreadsheet: XLS/OLE sector-chain self-loop causes memory exhaustion
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-m9cr-9614-rsf7 — PHPSpreadsheet: Gnumeric reader unbounded gzip expansion causes memory exhaustion
- [HIGH] **[high]** judotoernooi/phpoffice/phpspreadsheet: PKSA-dqzt-yst9-1w9y — PHPSpreadsheet: SSRF bypass via HTTP redirect in WEBSERVICE() domain whitelist
- [HIGH] **[high]** safehavun/guzzlehttp/guzzle: PKSA-gcrk-3vtt-1r14 — Guzzle: Noncanonical host can bypass host-based checks
- [HIGH] **[high]** safehavun/laravel/framework: PKSA-3r5d-mb8f-1qw9 — Laravel Framework: CRLF injection in default email rule 
- [HIGH] **[high]** safehavun/web-token/jwt-library: PKSA-237v-kv6c-dpkr — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle
- [HIGH] **[high]** safehavun/web-token/jwt-library: PKSA-66dc-42nb-26yy — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption
- [HIGH] **[high]** judotoernooi/—: Dependencies niet gemeten: python
- [HIGH] **[high]** havun/next: Next.js: Middleware / Proxy bypass in App Router applications using Turbopack and single locale
- [HIGH] **[high]** havun/postcss: PostCSS has XSS via Unescaped </style> in its CSS Stringify Output
- [HIGH] **[high]** havun/sharp: sharp inherited vulnerabilities in libvips: CVE-2026-33327, CVE-2026-33328, CVE-2026-35590, CVE-2026-35591
- [HIGH] **[high]** vpdupdate/xlsx: Prototype Pollution in sheetJS
- [HIGH] **[high]** safehavun/safehavun.havun.nl: Observatory grade C (score 50, minimum B)
