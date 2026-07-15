---
title: Alle open punten wegwerken (15-07-2026)
type: plan
scope: havuncore
last_check: 2026-07-15
---

# Alles fixen — plan

**Opdracht Henk (15-07):** "fix alles".

Niet alles kán door mij: een deel is een business- of UI-keuze, of een handeling in een externe UI.
Die scheiding staat hieronder expliciet, zodat "alles" niet stilletjes "alles behalve" wordt.

## Ik fix het (technisch, geen keuze van Henk nodig)

| # | Wat | Waarom nu |
|---|-----|-----------|
| A1 | **3 rescue-branches beoordelen** | Ze blokkeren het opruimen; de inhoud is technisch te beoordelen |
| A2 | **VPDUpdate `users.json` untracken** | 🔴 Live bcrypt-hashes + TOTP-secrets in de GitHub-historie |
| A3 | **Auto-commit-cron HavunCore prod** | Elke deploy struikelt op ff-only; terugkerend |
| A4 | **Projectlijst dedupliceren** | `havun-projects.php` ↔ hardcoded lijst in `DocIndexer` — je vergeet er altijd één (JSB/Aeterna/LastMatch ontbraken tot 15-07) |
| A5 | **HavunAdmin Bearer-token uit docs** | Staat sinds 16-06 open; strijdig met de eigen secrets-regel |
| A6 | **KB-chunking** | Lange docs alleen op hun begin doorzoekbaar — raakt de kernfunctie |

## Henk beslist (ik lever de feiten, hij kiest)

| Wat | Waarom niet door mij |
|-----|---------------------|
| **Aeterna-APK op HavunClub** | De link kan bij testers liggen → weghalen is een keuze, geen opruiming |
| **JudoScoreBoard `context.md` op master** (1039 regels) | Lost op bij de merge van `chore/expo-sdk-56-upgrade`; die branch mergen = 22 commits aan features live zetten |
| **Actief kanaal voor `critical` alerts** | Keuze push/mail/Telegram, raakt `.env` |
| **Blijvend-ingelogd-plan** | Wacht op "ga maar" |
| **VPDUpdate deployen** (50 achter) | Kan pas ná A2 |
| **GitGuardian #33883984 op Resolved** | Handeling in hun UI |
| **WIP-stash in webapp-repo** | Henks eigen werk (`git stash pop`) |
| **havuncore-webapp push-frontend** | Eigen sessie (UI-werk) |

## Volgorde en risico

1. **A2 eerst** — het enige echte security-punt. Let op: `users.json` untracken breekt een verse
   deploy als er geen fallback is → seed/template meeleveren, niet alleen `git rm --cached`.
2. **A1** — snel, en de branches mogen daarna weg.
3. **A3** — raakt cron (server-config). Fix: cron laten genereren zónder commit, óf de
   auto-gegenereerde bestanden gitignoren. Tweede is veiliger: geen cron-wijziging nodig.
4. **A4, A5** — klein.
5. **A6 als laatste** — grootste, raakt het DB-schema (meerdere rijen per bestand). Alleen doen als
   de rest staat.

## Grenzen

- **Vusista en JudoToernooi niet aanraken:** daar draait een parallelle sessie (19:06/19:13).
- Prod-deploy blijft Henks go.
- Geen `.env`/credentials wijzigen.
