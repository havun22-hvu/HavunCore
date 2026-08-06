---
title: Wat er per project openstaat
type: reference
scope: alle-projecten
last_check: 2026-08-06
---

# Wat er per project openstaat

> **Dit hoort niet in een HavunCore-sessie thuis.** HavunCore is orchestrator, dus deze punten
> komen hier langs — maar oppakken doe je in een sessie van dát project (`D:\GitHub\<project>`).
> De HavunCore-handover verwijst hierheen in plaats van het over te schrijven; anders vult die
> zich met werk dat er niet gedaan wordt.

## Dependency-advisories (zichtbaar geworden 03-08 door de scanfixes)

Nooit eerder gerapporteerd, want de scan mat op de verkeerde paden. **`composer update` /
`npm audit fix` op een productie-app → eerst overleg met Henk.**

| Project | Wat |
|---|---|
| Studieplanner-mobile | **2 critical** (`shell-quote`, `tar`) + 6 high — npm |
| havun.nl | 3 high (next, postcss, sharp) — npm |
| VPDUpdate | 1 high (`xlsx`, **geen fix beschikbaar** — vervangen door exceljs) |
| Studieplanner-api | 6 high + 24 medium — composer |
| JudoToernooi | 3 high + 10 medium — composer |
| SafeHavun | 3 high + 17 medium — laravel/framework, symfony, web-token/jwt |
| JudoScoreBoard | 6 GitHub-advisories (1 critical + 2 high) |

HavunAdmin, Herdenkingsportaal en HavunCore zijn schoon.

## Rode builds

HavunAdmin (3 maanden) · HavunClub (3 maanden, geparkeerd) · Veen-ledenadministratie ·
Studieplanner-api (04-08 nog steeds rood). Uitzoeken hoort in de projectsessie zelf.

## Per project

| Project | Wat er ligt |
|---|---|
| **Studieplanner-api** | Coverage is deels padding (24-07): 91,9% / 322 tests, `PremiumController` 67,7%, `UserDevice` 0%. **Ernstigst:** `MagisterApiTest`/`SOMtodayApiTest` leggen met `assertStatus(500)` vast dat een onbereikbare externe API een 500 van ónze API geeft — hoort 502/503. Volgorde in `Studieplanner-api/docs/testschuld.md`. Los daarvan: `rescue/prod-stashes-2026-07-15` afmaken of weg |
| **Studieplanner** | `chore/expo-sdk-55-upgrade`: 230/230 groen maar nooit device-getest, 3 maanden oud — mergen of verwerpen. De Pixel hangt sinds 06-08 aan de PC, dus device-testen kan nu |
| **JudoToernooi** | Stripe-sleutel geroteerd 19-07; oude `sk_live_…4l13` staat nergens actief meer — **laat 'm in Stripe verlopen**. Optioneel: webhook-secret roteren + `credentials.md` opschonen |
| **VPDUpdate** | `users.json` staat alleen op de server (+ backup) en loopt sinds 01-08 mee in de nachtelijke backup. Nog open: de secrets zitten in de git-historie — purgen is een eigen sessie |
| **Aeterna** | Prod keystore + update-adres. Week2-plan is dood — archiveren. `feat/v1.1-tor-socks5-3b` (PR #16 closed, niet merged) |
| **JudoScoreBoard** | Google-review AAB 116 (9 juni ingediend) — status alleen zichtbaar in de Play Console |
| **LastMatch** | Avast HTTPS-scanning uit = de enige APK-build-blocker |
| **Veen-ledenadministratie** | **GEPARKEERD (31-07)** — niets mee doen, ook de kleine betaalde klussen niet. Onze serveromgeving is opgeruimd; de lokale checkout blijft en wordt gescand. ⛔ De oude app van Cees op `37.34.60.216` (TransIP) is niet van ons en is niet aangeraakt. Volledig: `projects/veen-ledenadministratie.md` |

## Documentatie-onderhoud elders

- **havuncore-webapp update-banner** — niet reproduceerbaar sinds 24-07. Wéér last? Check
  `getRegistration()` op een `waiting`. `plans/webapp-sw-update-fix.md`. Vitest daar geblokkeerd
  door Avast, niet de registry — zie de notitie over SSL-interceptie op Henks machine.
- **Drie CLAUDE.md's boven de 120-regelnorm** — Studieplanner-api 135, JudoScoreBoard 136,
  havuncore-webapp 125.
- **JudoScoreBoard `context.md` op master nog 1039 regels** — opgeschoonde versie staat op
  `chore/expo-sdk-56-upgrade`; lost zichzelf op bij merge.

## Waarom dit doc bestaat

De HavunCore-handover was op 06-08 voor 43% gevuld met deze punten — negen items van de zestien,
goed voor 2.100 tekens, allemaal werk dat in een andere sessie hoort. Daarmee werd de handover
onleesbaar voor waar hij wél voor is: de stand van HavunCore zelf.

Regel: `standards/md-doc-grootte.md` (te groot → splitsen in index + deeldocs).
