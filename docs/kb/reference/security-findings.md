---
title: Security findings log (alle projecten)
type: reference
scope: alle-projecten
last_check: 2026-04-18
---

# Security findings log

> **Chronologisch** log van elke externe security-scan hit + fix.
> **Bron voor entries:** `composer audit`, `npm audit`, Mozilla Observatory, SSL Labs, OWASP ZAP, pentest, GHSA notifications.
> **Werkwijze:** zie `runbooks/security-findings-logging.md`.

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
