---
title: "Project: VeenLedenadministratie"
type: reference
scope: havuncore
last_check: 2026-07-19
---

# Project: VeenLedenadministratie

**Type:** Laravel 12 SaaS — ledenadministratie voor judoscholen met SEPA-incasso
**Eigenaar:** Cees Veen (product en klanten). Havun doet modernisering + hosting.
**Status:** herbouw van Laravel 5.5 → 12, gestart 18-07-2026

> **Niet via Mollie.** Het oude package zit in de code maar is nooit gebruikt. De incasso
> loopt via zelf gegenereerde pain.008-batchbestanden naar de bank.

## Repositories

| Wat | Waar |
|-----|------|
| Nieuwe app | `D:\GitHub\VeenLedenadministratie` · havun22-hvu/VeenLedenadministratie |
| Oude app (referentie) | `_legacy/` in dezelfde repo, buiten git |
| Server (nieuw) | `veen.havun.nl` (Hetzner) |
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
