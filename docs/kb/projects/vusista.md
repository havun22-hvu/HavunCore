---
title: "Project: Vusista 1 (opgeruimd)"
type: reference
scope: havuncore
last_check: 2026-08-01
---

# Vusista 1 — bestaat niet meer

**Opgeruimd op 01-08-2026, op Henks verzoek: "we hebben het niet meer nodig."** De opvolger is
[Vusista 2](vusista2.md) (`D:\GitHub\Vusista2`), die functioneel compleet is.

Dit doc blijft staan zodat wie hier zoekt vindt dát het weg is en waar het gebleven is — niet om
het project te beschrijven. **Niet opnieuw aanmaken.**

## Waar het gebleven is

| Wat | Waar |
|---|---|
| Repo, alle branches (`main`, `staging`) | `/root/backups/vusista1-cleanup-2026-08-01/vusista1-repo.bundle` — hersteltest gedaan: 370 bestanden, beide branches |
| De drie sqlite-databases, `storage/app`, `.env` | `…/vusista1-lokaal.tar.gz` (11 MB). **Die zaten in géén enkele git** — `database.sqlite` was 22 MB aan werk dat alleen op deze machine bestond |
| Serveromgeving (vhosts, cert, beide MySQL-databases) | `/root/backups/vusista-cleanup-2026-07-31` — die ging er 31-07 al af |

Beide bundels zijn `chmod 600` (er zit een `.env` in) en hun sha256 is aan beide kanten
gecontroleerd. `vendor/`, `node_modules/`, `dist/` en `resources/binaries/` zijn **niet** bewaard:
herbouwbaar, en samen 2,7 van de 2,8 GB.

## Wat er van geleerd is, en waar dat staat

De les is niet weggegooid met het project — die is de reden dat het fundament nu een keuze is:

- **Post-mortem:** [`../patterns/fundament-versus-omweg.md`](../patterns/fundament-versus-omweg.md)
  — Laravel omdat élk Havun-project zo begon, nooit gekozen; zes omwegen erachteraan, en de zesde
  maakte de app stil onbruikbaar.
- **De norm die daaruit volgde:** [`../standards/stack-keuze.md`](../standards/stack-keuze.md) en
  [`../patterns/omwegen-tellen.md`](../patterns/omwegen-tellen.md) — vijf vragen plus een
  conclusie in `docs/intake.md` vóór er een stack ligt; bij de tweede omweg een
  architectuurreview.
- **Waarom het vier maanden ongezien bleef:** het stond in `havun-projects.php` en niet in
  `quality-safety.php`. Dat gat is nu een check —
  [`../plans/registry-drift-check-plan.md`](../plans/registry-drift-check-plan.md).
- **Productbesluiten** (in-place, pixels ongemoeid, privacy, de niet-doen-lijst) zijn ongewijzigd
  meegegaan naar Vusista 2 en staan daar in `docs/besluiten/` en `docs/product/`.

## Nog openstaand voor Henk

- **DNS-record `vusista.havun.nl`** staat nog bij mijn.host en wijst nergens meer heen.
- **Deploy-key `server-read`** kan uit de (gearchiveerde) GitHub-repo.
