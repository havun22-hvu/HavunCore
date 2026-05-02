---
title: Security findings log (alle projecten)
type: reference
scope: alle-projecten
last_check: 2026-05-02
---

# Security findings log

> **Chronologisch** log van elke externe security-scan hit + fix.
> **Bron voor entries:** `composer audit`, `npm audit`, Mozilla Observatory, SSL Labs, OWASP ZAP, pentest, GHSA notifications, ad-hoc server-sweeps.
> **Werkwijze:** zie `runbooks/security-findings-logging.md`.

## 2026-05-02 — Server-hardening sweep (Hetzner prod 188.245.159.115)

**Bron**: ad-hoc audit n.a.v. user-vraag "is de site goed beveiligd?".
**Scope**: OS-laag + Laravel app-config op productie-server. App-laag (CSP,
CSRF, XSS, etc.) was al groen — focus deze sweep op infra die door eerdere
audit (1-nov-2025) niet was gedekt.

### Bevindingen

| # | Severity | Bevinding | Project / scope |
|---|----------|-----------|-----------------|
| 1 | 🔴 HIGH | `APP_DEBUG=true` op productie | Herdenkingsportaal |
| 2 | 🟠 MEDIUM | `SESSION_LIFETIME=43200` (30 dagen) — eis is ≤ 120 | Herdenkingsportaal |
| 3 | 🟠 MEDIUM | `SESSION_DRIVER=file` — eis is `database` op productie | Herdenkingsportaal |
| 4 | 🟠 MEDIUM | `.env` permissions `0664` (world-readable) | Herdenkingsportaal |
| 5 | 🔴 HIGH | UFW firewall `inactive` op hele server | alle projecten |
| 6 | 🔴 HIGH | fail2ban niet geïnstalleerd; `auth.log` toont actieve brute-force op SSH | alle projecten |
| 7 | 🔴 HIGH | SSH `PasswordAuthentication` aan; brute-force pogingen succesvol-mogelijk | alle projecten |

### Wat goed was (geen actie)

- ✅ HTTPS-headers correct (HSTS, CSP nonce, Permissions-Policy, COOP/CORP)
- ✅ Cookies met `__Host-` en `__Secure-` prefixes, secure+httponly+samesite=lax
- ✅ MySQL + Redis bind 127.0.0.1 (niet extern bereikbaar)
- ✅ SSL cert geldig tot 22-jul-2026, ECDSA, auto-renew werkt
- ✅ PHP 8.2.29 (laatste patch)
- ✅ Composer + npm audits clean

### Doc-updates (2026-05-02)

- `productie-deploy-eisen.md` — sectie 8 "Server-hardening (OS + app-config)" toegevoegd met 7 sub-eisen + verifieer-commands
- `poort-register.md` — sectie "Externe bereikbaarheid (UFW policy)" toegevoegd
- `security.md` — server-hardening status tabel toegevoegd, Hetzner prod = ❌
- `server.md` — ports-sectie verwijderd (dubbel met poort-register)
- `Herdenkingsportaal/docs/3-TECHNICAL/SECURITY-AUDIT-2025-11-01.md` — verwijderd (obsolete "100/100" claim)

### Plan / status fixes

Plan-doc: `Herdenkingsportaal/docs/3-TECHNICAL/SERVER-HARDENING-PLAN-2026-05-02.md`
(volgorde, dependencies, rollback, owner=Henk).

| # | Fix | Status |
|---|-----|--------|
| 1 | APP_DEBUG=false op HP-prod + cache:clear | ✅ 2026-05-02 23:03 UTC |
| 2 | SESSION_LIFETIME=120 + DRIVER=database op HP-prod | ✅ 2026-05-02 23:03 UTC (sessions-tabel bestond al, geen migrate nodig) |
| 3 | chmod 640 .env op HP-prod (eigenaar root:www-data) | ✅ 2026-05-02 23:03 UTC |
| 4 | UFW activeren met whitelist 22/80/443/22000 | ✅ 2026-05-02 11:15 UTC — actief, externe poorten 8080/8081/3001-3004 nu geblokkeerd |
| 5 | fail2ban installeren + sshd jail | ✅ 2026-05-02 23:15 UTC — 8 IPs direct gebanned uit auth.log historie |
| 6 | SSH PasswordAuthentication=no (na pubkey-test) | ✅ 2026-05-02 11:20 UTC — pubkey enforced, password-login geweigerd, sshd reload (niet restart) |
| 7 | Re-audit + status updaten in `security.md` | ✅ 2026-05-02 11:20 UTC — alle 7 checks groen, `security.md` bijgewerkt |

**Verificatie Fase 1+2** (na uitvoering):
- DB row-counts vóór = na: `users=5, memorials=8, invoices=13, payments=17` (data-safety bevestigd)
- `curl -sI https://herdenkingsportaal.nl/i-do-not-exist` → HTTP 404 zonder Whoops-stack-trace in body (APP_DEBUG=false werkt)
- `ls -la .env` → `-rw-r----- 1 root www-data` (640 perms gerespecteerd, php-fpm leest nog via groep)
- HTTPS 200 OK na chmod (php-fpm leest .env via group)

**Bonus-bevindingen tijdens P3 (.env snapshots)** — opgelost 2026-05-02 11:30 UTC:
- `havunclub/production/.env`: 755 → ✅ 640 (root:www-data)
- `havunclub/staging/.env`: 755 → ✅ 640 (root:www-data)
- `herdenkingsportaal/staging/.env`: 755 → ✅ 640 (root:www-data)
- `safehavun/production/.env`: 755 → ✅ 640 (root:www-data)
- Alle 4 sites na fix HTTP 200/302 (framework draait, php-fpm leest via group)

## 2026-04-18 — Composer audit sweep

### HavunAdmin — 8 advisories → 1 resterend

**Bron:** `composer audit` tijdens `/start` hook (18-04-2026).

| Package | Severity | CVE / Advisory | Fix | Status |
|---------|----------|----------------|-----|--------|
| phpseclib/phpseclib | HIGH | GHSA (<=3.0.49) | 3.0.47 → 3.0.51 | ✅ |
| phpseclib/phpseclib | LOW | (<3.0.51) | 3.0.47 → 3.0.51 | ✅ |
| league/commonmark | MEDIUM (2×) | (<=2.8.1) | 2.7.1 → 2.8.2 | ✅ |
| symfony/process | MEDIUM | CVE-2026-24739 (Windows escape) | 7.3.4 → 7.4.8 | ✅ |
| psy/psysh | MEDIUM | CVE-2026-25129 (CWD auto-load) | 0.12.14 → 0.12.22 | ✅ |
| phpunit/phpunit | HIGH | CVE-2026-24765 (PHPT deserialization) | 11.5.44 → 11.5.55 | ✅ |
| phpunit/phpunit | HIGH | GHSA-qrr6-mg7r-m243 (INI newline injection) | Vereist 12.5.22+ — blocked door `^11.5.3` constraint | ⏳ open |

**Commit-hash:** `86277d5` (chore: security patches for vendor dependencies).
**Validatie:** PHPUnit suite 3191 passed / 7 skipped / 0 failed.

**Lessen:**
- Vendor-dir had 144 `.git` subdirs (oude `prefer-source` install) → `composer update` faalde met "Source directory has uncommitted changes". **Fix:** `rm -rf vendor && composer install --prefer-dist`.
- PHPUnit major bump (11 → 12) is breaking, aparte feature-branch klus.

### Herdenkingsportaal — 1 advisory → 0

**Bron:** `composer audit` tijdens `/start` hook (18-04-2026).

| Package | Severity | CVE / Advisory | Fix | Status |
|---------|----------|----------------|-----|--------|
| firebase/php-jwt | LOW | CVE-2025-45769 (weak encryption, <7.0.0) | 6.11.1 → **7.0.5** via socialite bump 5.23 → 5.26 | ✅ |

**Lessen:**
- **Transitive CVE kan opgelost worden door minor-bump van direct dependency.** Socialite's nieuwe release verruimde `firebase/php-jwt` constraint van `^6.4` naar `^6|^7`, waardoor de bump vanzelf doorkwam.
- **Regel:** altijd `composer update --with-dependencies` proberen vóór advisory als "onoplosbaar" af te schrijven.

### HP — PHP memory_limit te laag voor volledige test-suite (runtime vs. static-time)

**Bron:** eigen test-run 2026-04-18, na de composer update.
**Severity:** informational (geen security-bug — dev-ergonomie).

**Symptoom:** `Allowed memory size of 536870912 bytes exhausted (tried to allocate 20480 bytes)` na ~5563 tests groen. PHPUnit-proces hing, tests stopten halverwege.

**Oorzaak-categorie:** #5b runtime-vs-static-time. `memory_limit` is een statische cap; test-suite-grootte is runtime. Geen defensie → crash.

**Fix:** `php -d memory_limit=2G artisan test` (ipv standaard `php artisan test`).

**Lessen:**
- Test-suites groeien onopgemerkt mee per feature. Voeg memory-limit override toe aan standaard dev-commando's.
- Overwegen: `composer.json` `scripts.test` met `-d memory_limit=2G` ingebakken zodat elke dev/CI dezelfde limiet gebruikt.
- CI (GitHub Actions) heeft meestal genoeg RAM maar zet ook `-d memory_limit=2G` expliciet voor reproduceerbaarheid.
- Toegevoegd aan `patterns/runtime-vs-static-assumptions.md` sectie 5b.

### HP — historische secrets in git history (scan 2026-04-18/19)

**Bron:** ggshield full repo-scan.
**Severity:** LAAG (repo private); zou HIGH worden bij public-going.

**Wat staat in git history (niet in HEAD):**
| File | Commit | Secrets | Detectors |
|------|--------|---------|-----------|
| `.env.dev` | f66360ff | 1 | (ignored in GitGuardian) |
| `.env.prod` | f66360ff + 5445bf8d | 2 (elk) | (ignored) |
| `.env.staging` | f66360ff | 3 | (ignored) |
| `docs/5-CREDENTIALS/CREDENTIALS.md` | 4790e63a (deleted: df99f01) | 4 | (ignored) |
| `resources/views/auth/register.blade.php` | 9cdfd59c | 4 | (ignored) |

**Huidige staat:** ✅
- `.env*` en `CREDENTIALS.md` in `.gitignore`
- Geen van deze files nog in HEAD
- GitGuardian dashboard heeft alle hits gemarkeerd als "ignored"

**Waarom niet meteen opgelost:**
- `git filter-repo`/BFG-rewrite is destructief (breakt alle checkouts + forks)
- Geen onmiddellijk lek: repo is private
- Secrets zijn waarschijnlijk al geroteerd sinds leaks

**Wanneer wél opruimen:**
- Vóórdat de repo public wordt gemaakt
- Bij verdenking of extern bericht van misbruik
- Bij ownership-transfer

**Runbook voor opruiming (als het moment komt):**
1. Roteer ALLE secrets genoemd in deze entries (zelfs als we denken dat ze al veranderd zijn)
2. `git filter-repo --path .env.dev --path .env.prod --path .env.staging --path docs/5-CREDENTIALS/CREDENTIALS.md --invert-paths`
3. Force-push + coördineer met alle clones (lokale worktrees opnieuw klonen)
4. GitHub Support vragen om caches te vervangen
5. Update deze entry: status → CLEANED

**Scope:** Alleen HP in deze vorm aangetroffen. Andere projecten: volledige git-history scan was clean (zie parent entry).

---

## Template voor nieuwe entries

```markdown
## YYYY-MM-DD — [korte titel]

### [Projectnaam] — [X advisories → Y resterend]

**Bron:** [scan-tool + context]

| Package | Severity | CVE / Advisory | Fix | Status |
|---------|----------|----------------|-----|--------|
| ... | ... | ... | ... | ✅ / ⏳ / ❌ |

**Commit-hash:** `xxxxxxx`
**Validatie:** [tests / scan / metric]

**Lessen:**
- ...
```
