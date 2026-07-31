---
title: "Project: VeenLedenadministratie"
type: reference
scope: havuncore
last_check: 2026-07-19
---

# Project: VeenLedenadministratie

**Type:** Laravel 12 SaaS — ledenadministratie voor judoscholen met SEPA-incasso
**Eigenaar:** Cees Veen (product en klanten). Havun doet modernisering + hosting.
**Status:** ⛔ **PROJECT GEPARKEERD** (Henk, 31-07-2026) — voorlopig niets mee doen.
De herbouw was al geparkeerd (Cees vond de offerte te duur, besluit 003); nu ligt het hele
project stil, óók de kleine betaalde klussen.

> **Onze serveromgeving is 31-07 opgeruimd** — `veen.havun.nl`, de staging, het cert en beide
> databases (production 9 tabellen; staging 26 tabellen / ~18.941 rijen). Backup:
> `/root/backups/veen-cleanup-2026-07-31` (72 MB tarball + beide dumps + nginx-config), root-only
> want er zitten `.env`-bestanden in. **De lokale checkout blijft staan** — Cees kan nog vragen
> hebben.
>
> ⛔ **De oude app van Cees draait op `37.34.60.216` (TransIP) en is NIET van ons.** Die is niet
> aangeraakt en blijft ongemoeid — daar staat de live administratie met 1.177 SEPA-machtigingen
> en 15.030 betalingen.
>
> `qv:scan` blijft **aan** op de lokale checkout: een geparkeerd project waar niemand naar kijkt,
> is waar een advisory het langst blijft zitten. Dat is het verschil met Vusista 1, dat weggegooid
> wordt en daarom op `enabled => false` staat.

> **Niet via Mollie.** Het oude package zit in de code maar is nooit gebruikt. De incasso
> loopt via zelf gegenereerde pain.008-batchbestanden naar de bank.

## ⚠️ Sessiecookie zonder `Secure`-vlag — ook in de LIVE oude app (31-07-2026)

`qv:scan` gaf 1 high op de nieuwe app. Bij het natrekken bleek de **oude, draaiende** app
hetzelfde te hebben, en harder:

| | `config/session.php` | `SESSION_SECURE_COOKIE` in `.env.example` |
|---|---|---|
| Nieuwe app (geparkeerd, draait nergens) | `env('SESSION_SECURE_COOKIE')` → **null** | ontbreekt |
| **Oude app (live bij Cees)** | `env('SESSION_SECURE_COOKIE', **false**)` | **ontbreekt** |

Geen `forceScheme`/HTTPS-middleware gevonden in de legacy-app.

**Wat het betekent:** zonder `Secure` stuurt de browser de sessiecookie óók over onversleuteld
HTTP. Wie op hetzelfde netwerk meekijkt (open wifi) onderschept hem en neemt de sessie over —
bij deze app betekent dat NAW-gegevens, IBAN's en machtigingen van 1.177 leden.

**NIET geverifieerd:** of de productie-`.env` op `37.34.60.216` de variabele alsnog op `true`
zet. Dat is Cees' server; die is niet aangeraakt en dat blijft zo. Zonder die `.env` te zien of
de `Set-Cookie`-header van de live site op te vragen, is dit een **risico-indicatie, geen
vastgestelde kwetsbaarheid**. Eén `curl -sI` op het live domein zou het beantwoorden — Henks call.

**Niet gefixt:** project is geparkeerd, en de oude app is Cees' eigendom. Zie
`standards/claims-verifieren.md`: de claim staat hier mét de twijfel erbij.

## Repositories

| Wat | Waar |
|-----|------|
| Nieuwe app | `D:\GitHub\VeenLedenadministratie` · havun22-hvu/VeenLedenadministratie |
| Oude app (referentie) | `_legacy/` in dezelfde repo, buiten git |
| ~~Server (nieuw)~~ | **opgeruimd 31-07-2026** (was `veen.havun.nl` op Hetzner) |
| Server (oud, draait nog) | `37.34.60.216` (TransIP), niet meer aanraken |

## Omvang (peildatum 18-07-2026)

| | |
|---|---|
| Judoscholen | 7, waarvan 5 actief incasserend |
| Leden | 1.177 (695 gaan mee bij de migratie) |
| Mandaten | 1.177 |
| Betalingen | 15.030 |
| Batchbestanden | 427 |

## Wat je moet weten vóór je iets aanraakt

**Lid-id's en `created_at` mogen niet wijzigen.** Het SEPA-machtigingskenmerk
(`KLANTNR00042`) en de ondertekendatum zijn daaruit afgeleid; die kent de bank.
Hernummeren breekt 1.177 lopende machtigingen.

**Incassofrequentie verschilt per school.** Cees Veen en Kata Guruma per kwartaal;
Brothergym, Monstergym en Samani Gym per maand — en die maandincasso loopt één maand
vooruit (incasso in januari = contributie februari).

**De €0,01-incasso is bewust.** Een SEPA-machtiging vervalt na 36 maanden zonder incasso.
Pauzeleden (blessure, buitenland) krijgen daarom één cent per periode; zes leden zitten
ruim boven die grens. Niet opruimen zonder na te denken.

**De scholen zijn aparte bedrijven.** Eén database met een fail-closed scope; zie
[../patterns/multi-tenant-fail-closed.md](../patterns/multi-tenant-fail-closed.md).

## Documentatie

Project-specifieke kennis staat in het project zelf:

| Waar | Wat |
|---|---|
| `docs/README.md` | ingang en leesvolgorde |
| `docs/product/business-rules.md` | index van de domeinregels |
| `docs/techniek/sepa-incasso.md` | het kritieke pad |
| `docs/OPEN-VRAGEN.md` | wat nog beantwoord moet worden |
| `.claude/handover.md` | actuele stand |
| `.claude/stappenplan.md` | de acht stappen |

## Patterns die hieruit voortkwamen

- [multi-tenant-fail-closed.md](../patterns/multi-tenant-fail-closed.md) — scheiding tussen
  klanten die dicht faalt, inclusief de schrijfkant
- [legacy-gedrag-vastleggen.md](../patterns/legacy-gedrag-vastleggen.md) — karakterisatietests
  vóór een herbouw, en waarom gemaskeerde productiedata niet in git hoort
- [constraints-die-werk-blokkeren.md](../patterns/constraints-die-werk-blokkeren.md) — elke
  constraint blokkeert ergens gewoon werk; vijf keer aangetoond in dit project
- [onveranderlijke-financiele-records.md](../patterns/onveranderlijke-financiele-records.md) — verstuurde
  incassos vastzetten met triggers; drie lagen, elk gebroken door een controleronde
- [testen-op-de-echte-database.md](../patterns/testen-op-de-echte-database.md) — waarom de
  suite hier op MySQL draait en niet op SQLite
- [unique-met-soft-deletes.md](../patterns/unique-met-soft-deletes.md) — een unique naast
  `deleted_at` faalt altijd te streng of te los; hier op vijf tabellen tegelijk
