---
title: "Project: Vusista 2"
type: reference
scope: havuncore
last_check: 2026-08-01
---

# Project: Vusista 2

**Type:** Fotoalbum **desktop-app** (Rust + Iced, Windows) — Picasa-eenvoud, lokale foto's in-place
organiseren, privacy-vriendelijk.
**Status:** functioneel compleet, draait op 75.056 items. Nog niet doorgetest door Henk.
**Pad:** `D:\GitHub\Vusista2` · **Server:** géén, en dat is een regel — zie hieronder.

Herbouw van [Vusista 1](vusista.md), dat 01-08-2026 is opgeruimd. Waaróm er herbouwd is, staat in
[`../patterns/fundament-versus-omweg.md`](../patterns/fundament-versus-omweg.md).

## De regel die alles bepaalt

> **Gooi de app én de index weg — de gebruiker mag niets kwijt zijn.**

Scherper: **naast een foto staat de naam zelf, nooit een verwijzing naar een centrale lijst.** Eén
map met foto's moet op zichzelf genoeg zijn om te weten wie erop staat. Picasa verloor Henks
gezichten precies daar: de vakjes stonden lokaal, de namen centraal.

## Stack

Twee crates: `kern/` (alles met foto's, **kent geen UI** — crategrens, geen afspraak) en `ui/`.

- **Kern:** Rust — geen GC-pauzes tijdens scrollen, veilige parallelliteit via `rayon`
- **UI:** **Iced 0.14**, geen webview en geen HTML (Tauri is er 31-07 uit: een losse `</div>` hoort
  een compileerfout te zijn)
- **Index:** kolom-vectoren in geheugen, weggooibaar en herbouwbaar · **Thumbnails:** append-only
  pack met offset-tabel · **Gezichten:** YuNet + SFace via ONNX Runtime, in-process
- **Meegeleverd:** exiftool en ffmpeg als binaries

Waarom deze stack en niet een webstack: de vijf intakevragen mét conclusie staan in
`D:\GitHub\Vusista2\docs\intake.md` — inclusief het omkeerpunt.

## Geen server, en dat is opzet

Geen staging, geen productie, geen deploy-pipeline, geen SSH, geen database-server. Uitleveren gaat
via `maak-uitlevering.ps1` + `installeer.ps1`. Komt er een voorstel voor web-infra, een API of
accounts: lees eerst `BERICHT-HAVUNCORE.md` in het project — daar ging Vusista 1 aan kapot.

## V&K

- `qv:scan` scant de crates (`cargo audit`); registratie in `quality-safety.php` staat er sinds dag
  één, want een project dat de registries niet kennen wordt ook niet gescand.
- **Eigen CI sinds 01-08** (`.github/workflows/ci.yml`, Windows-runner): `cargo test`,
  `clippy -- -D warnings`, `fmt --check` en `cargo audit`, alle vier hard.
- Geen browser-E2E: Iced rendert native, dus de Playwright-eis (`test-quality-policy.md` §10)
  geldt hier niet. De crategrens ís de teststrategie — `kern/` is testbaar zonder venster.

## Documentatie

Staat **in het project**: `docs/besluiten/` (ADR's), `docs/techniek/`, `docs/product/`,
`docs/plannen/`, `docs/runbooks/`, `docs/omwegen.md`. Begin bij `CLAUDE.md` en `PLAN.md`.
Sessiewerk in `.claude/`.

Zoeken: `php artisan docs:search "<onderwerp>" --project=vusista2`
