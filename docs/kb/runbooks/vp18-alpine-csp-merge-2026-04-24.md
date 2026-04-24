---
title: VP-18 Alpine CSP merge + unsafe-eval elimination (24-04-2026)
type: runbook
scope: judotoernooi
last_check: 2026-04-24
status: RESOLVED
---

# VP-18 Alpine CSP migration — merged + live

## Wat er is gebeurd

Op 24-04 is `feat/vp18-alpine-csp-migration` (41 commits, 31 batches, 2666/1867 regels diff) gemerged in `main`. Deze branch bevatte een volledige Alpine CSP-migratie die staging al draaide sinds ~17 april, maar was nog niet gemerged.

Parallel werk was die nacht op main begonnen (5 batches, 30 files) zonder te weten dat vp18 al bestond. Lessen: zie `memory/feedback_check_git_branches.md`.

## Merge-strategie

- **Accept theirs (vp18)** voor alle Alpine-gerelateerde bestanden (completer, met `@alpinejs/csp` build)
- **Manual merge** op `SecurityHeaders.php`: vp18's unsafe-eval verwijdering BEHOUDEN + mijn Tailwind/gstatic cleanup BEHOUDEN
- **Manual merge** op `SecurityHeadersTest.php`: beide test-sets behouden (HSTS preload + unsafe-eval regression)
- **Re-apply Chromecast removal** op vp18's device-toegangen (vp18 was ouder dan mijn Chromecast commit)

## Resultaat live

| Check | Pre-merge main | Na merge live |
|---|---|---|
| `unsafe-eval` in CSP | ✅ aanwezig (Alpine vanilla) | ❌ **weg** (Alpine CSP build) |
| `cdn.tailwindcss.com` in CSP | ❌ weg (mijn commit) | ❌ **weg** (behouden) |
| `gstatic.com` in CSP | ❌ weg (mijn commit) | ❌ **weg** (behouden) |
| HSTS `preload` | ✅ aanwezig (mijn commit) | ✅ **aanwezig** (behouden) |
| Pusher SRI | ✅ 9 plekken (mijn commit) | ✅ **9 plekken** (behouden) |
| Chromecast feature | ❌ weg (mijn commit) | ❌ **weg** (re-apply) |
| Alpine CSP build (`@alpinejs/csp`) | ❌ vanilla | ✅ **@alpinejs/csp** |

## Verwachte Mozilla Observatory impact

judotournament.org van **A (90/100)** naar **A+ (100/100)** — `unsafe-eval -10 penalty` is weg.

## Verificatie

```bash
curl -skI https://judotournament.org/ | grep -i 'content-security-policy'
```
→ `script-src 'self' 'nonce-...' https://cdn.jsdelivr.net ...` (GEEN `'unsafe-eval'`).

## Tests

- SecurityHeadersTest: 17/17 passed (incl nieuwe `csp_does_not_contain_unsafe_eval_in_non_local_env`, HSTS preload, unsafe-inline regression)
- Full suite: 2883/2884 passed (1 pre-existing regex-failure in `ClubControllerCoverageTest`, niet gerelateerd aan merge)

## Cleanup

- Backup tag `backup/pre-vp18-merge` gepusht voor rollback safety
- Lokale branch `feat/vp18-alpine-csp-migration` verwijderd na merge
- Remote branch kan verwijderd als gewenst (`git push origin --delete feat/vp18-alpine-csp-migration`) — voor nu behouden als referentie

## Open follow-ups

### ✅ Afgerond — ClubControllerTest regex-faal

`clubs/index.blade.php` gebruikte `@json()` directive met multi-line
closure die een array returned. Blade's haakjes-teller in de `@json()`
macro kan nested `[` + `fn($c) => [...]` niet correct parsen; gecompileerde
PHP ontbrak de sluitende `]` waardoor elke route die de view rendert een
`ParseError: Unclosed '[' on line 220 does not match ')'` gaf. 4
ClubController tests faalden op main vóór de fix (pre-existing — niet
veroorzaakt door VP-18).

**Fix** (commit `8dc7a617`): bouw JSON eerst in `@php` block, emit met
`{!! !!}`:
```blade
@php $clubsLookupJson = $clubs->keyBy('id')->map(fn($c) => [...])->toJson(); @endphp
const clubsLookup = {!! $clubsLookupJson !!};
```

Resultaat: alle 25 ClubController tests groen.

### ✅ Afgerond — staging mist HSTS (by design)

Middleware check is `app()->environment('production') && $request->secure()`
— staging (APP_ENV=staging) krijgt bewust geen HSTS. Dit voorkomt
permanent-lockout als staging-SSL-cert verloopt of HTTPS breekt tijdens
test-iteraties. **Niet fixen** — ontworpen security-trade-off.

### ⏳ Open

- HavunAdmin Alpine CSP migratie — nog aparte MPC-sessie nodig voor 2
  grote in-view functions (invoiceProcessor + reconciliation)
