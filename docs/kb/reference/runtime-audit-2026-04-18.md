---
title: Runtime vs. static-time audit — baseline 2026-04-18
type: reference
scope: alle-projecten
last_check: 2026-04-18
---

# Audit: runtime vs. static-time anti-patterns — baseline per project

> **Bron:** `patterns/runtime-vs-static-assumptions.md` — 6 fout-categorieën.
> **Scan-datum:** 18-04-2026.
> **Doel:** nulmeting per project, zodat volgende sessies per categorie per project
> kunnen afwerken en deze lijst afneemt in plaats van groeit.

## Totalen

| Categorie | Hits | Bestanden | Projecten |
|-----------|------|-----------|-----------|
| #1 Tailwind JIT (`w-[{{}}]`, `h-[{{}}]`, `top-[{{}}]`) | 12 | 9 | JT (11), HP (1) |
| #2 CDN niet in CSP | onderzoek pending | — | alle met CDN-assets |
| #3 Dynamisch `<style>` in JS zonder nonce | 2 | 2 | HavunAdmin (parallel CSP-migratie bezig) |
| #4 Inline `on*=` handlers | 864 | 202 | alle projecten |
| #5 SW `caches.match()` zonder Response-fallback | ~~15~~ **0** | ~~6~~ **0** | ✅ ALL FIXED (2026-04-18 later) |
| #6 Alpine `x-text` object-chain zonder null-safety | 48 | 11 | JT (7), HA (1), HP (1), andere (2) |

## Per categorie — volledige file-lijst

### #1 Tailwind JIT runtime mismatch (12 hits / 9 files)

Fix-patroon: `class="w-[{{ $x }}%]"` → `style="width: {{ $x }}%"` **plus** CSP
`style-src-attr 'unsafe-inline'` in `SecurityHeaders.php`.

| Project | File | Regel | Patroon |
|---------|------|-------|---------|
| JT/laravel | `components/freemium-banner.blade.php` | 23 | `w-[{{ min($percentage, 100) }}%]` |
| JT/laravel | `organisator/wimpel/show.blade.php` | 43 | `w-[{{ ... }}%]` (wimpelpunten) |
| JT/laravel | `pages/mat/partials/_bracket.blade.php` | 42 | `h-[{{ $totaleHoogte }}px]` **KRITIEK bracket-hoogte** |
| JT/laravel | `pages/mat/partials/_bracket-b.blade.php` | 46 | `h-[{{ $totaleHoogte }}px]` **KRITIEK** |
| JT/laravel | `pages/mat/partials/_bracket-medailles.blade.php` | 12, 29, 50, 68 | 4× `top-[{{ ... }}px]` **KRITIEK medaille-posities** |
| JT/laravel | `pages/mat/partials/_bracket-potje.blade.php` | 66 | `top-[{{ $topPos }}px]` **KRITIEK** |
| JT/laravel | `pages/toernooi/dashboard.blade.php` | 27 | `w-[{{ min($toernooi->bezettings_percentage, 100) }}%]` |
| JT/laravel | `pages/toernooi/afsluiten.blade.php` | 238 | `w-[{{ $statistieken['voltooiings_percentage'] }}%]` |
| HP | `memorials/edit-design.blade.php` | 2 | 1× arbitrary runtime |

**CSP-gotcha JudoToernooi:** `SecurityHeaders.php:52` heeft `style-src 'self' 'nonce-{...}'`
**zonder** `style-src-attr`. Inline `style=""` is dan ook geblokkeerd op productie.
Fix vereist **gelijktijdig**: CSP-update + view-aanpassing, anders breekt productie.

### #3 Dynamisch `<style>` in JS zonder nonce (2 files)

- `HavunAdmin/public/js/pwa-install.js` (parallel CSP-migratie bezig)
- `HavunAdmin/public/js/swipe-navigation.js` (parallel CSP-migratie bezig)

**Advies:** andere projecten auditen nadat HavunAdmin-patroon gestabiliseerd is,
dan dat patroon overnemen (`<meta name="csp-nonce" …>` + JS picker).

### #4 Inline `on*=` handlers per project (864 hits / 202 files)

| Project | Files met `on*=` |
|---------|-----------------|
| Herdenkingsportaal | 58 |
| JudoToernooi / laravel | 51 |
| JudoToernooi / staging | 51 (idem, andere versie) |
| Infosyst | 14 |
| SafeHavun | 11 |
| HavunClub | 6 (archived — mogelijk skippen) |
| Studieplanner-api | 5 |
| HavunAdmin | 0 (parallelle CSP-migratie bezig) |
| HavunCore | 0 |
| HavunVet | 0 |

**Strategie:** wacht op HavunAdmin-patroon (delegated listeners in `csp-handlers.js` +
`data-*` attributes). Dat patroon kopieerbaar per project.

### #5 Service Worker `caches.match()` zonder Response-fallback — ✅ DONE (18-04-2026)

Fix-patroon gebruikt: `.then(r => r || Response.error())` of
`.catch(() => Response.error())` aan het eind van elke chain.

| Project | Commit | Wat gefixt |
|---------|--------|-----------|
| HavunAdmin | al eerder | `response || Response.error()` aanwezig |
| Herdenkingsportaal | `dd3bc5d` | HTML + static fallbacks toegevoegd |
| Infosyst | `1c3bf9c` | OFFLINE_URL-miss-chain-fallback |
| SafeHavun | `6067c0d` | static + default strategy fallbacks |
| JudoToernooi / laravel | `fbd9caaf` (main) + `fff8b865` (vp18-branch) | navigate + assets |
| JudoToernooi / staging | idem | idem |

Uit initiële audit (15 hits / 6 files) bleken 4 files écht ongepatcht. Nu alle
chains defensief.

### #6 Alpine `x-text` zonder null-safety (48 hits / 11 files)

Fix-patroon: `x-text="obj.a.b"` → `x-text="obj?.a?.b ?? '-'"`.

Geconcentreerd in:
- `JudoToernooi/laravel/resources/views/pages/publiek/index.blade.php` (18)
- `JudoToernooi/staging/resources/views/pages/publiek/index.blade.php` (18)
- `JudoToernooi/*/components/scoreboard.blade.php` (2×)

Publiek + scoreboard = live views tijdens toernooien — elk Alpine-crash = zichtbaar
voor toeschouwers. Hoge prioriteit.

## Follow-up plan (sessie per project)

| # | Project | Scope | Geschat |
|---|---------|-------|---------|
| 1 | **JT** | #1 (11 hits) + CSP update + #6 scoreboard null-safety (~20 hits) | 2 uur |
| 2 | **HP** | #1 (1 hit) + #5 sw.js fallback + #4 (58 views) — lange sessie | 3-4 uur |
| 3 | **Infosyst** | #5 sw.js + #4 (14 views) | 1,5 uur |
| 4 | **SafeHavun** | #5 sw.js + #4 (11 views) | 1,5 uur |
| 5 | **SP-api** | #4 (5 views) | 30 min |
| 6 | **HavunClub** | SKIP (archived) of losse commit | — |

## Wat NIET in deze audit meegenomen

- `style="..."` met statische waarden (niet runtime, geen bug)
- CDN-assets op alle projecten (cat #2) — aparte scan nodig via Mozilla Observatory
- Blade `@context` / `@type` (dat is cat. Blade-directive, aparte gotcha in `frontend-gotchas.md`)

## Log-locatie voor follow-up fixes

Elke sessie die hits uit dit rapport wegwerkt, voegt entry toe aan:
`docs/kb/reference/security-findings.md` onder kop "Runtime vs static-time fixes".

Als een categorie naar 0 hits gaat in een project → markeer hier als `✅ DONE`.
