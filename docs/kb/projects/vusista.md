---
title: "Project: Vusista"
type: reference
scope: havuncore
last_check: 2026-07-15
---

# Project: Vusista

**Type:** Fotoalbum **desktop-app** (Laravel 12 + NativePHP/Electron)
**Status:** Fase 1 (MVP) functioneel af — open: installer-test op schone PC
**Platform:** Windows eerst (macOS/Linux fase 2)
**Demo:** https://staging.vusista.havun.nl (browser-demo met dummydata; prod bevroren)

## Wat is het?

Picasa-opvolger voor de gewone gebruiker: lokale foto's en video's **in-place**
indexeren, ordenen en verrijken (tags, bijschriften, locatie, datum, albums,
favorieten). Alles op de eigen PC — geen cloud, geen upload, geen externe AI.

**Geen webapp.** Dit is het enige Havun-project dat als installeerbare desktop-app
wordt uitgeleverd; een release is een NativePHP-build, geen server-deploy.

## Gulden regels

1. **Pixels worden nooit aangeraakt.** Metadata (XMP/IPTC) mag naar de bestanden,
   beelddata nooit. Elke schrijfactie verifieert dat (ImageDataHash) en rolt terug.
2. **Foto's blijven waar ze staan.** In-place indexeren, nooit kopiëren/verplaatsen.
3. **Alles lokaal.** Foto's + EXIF/GPS zijn persoonsgegevens (AVG).

## Omgevingen

| Omgeving | Pad | Poort | Database |
|----------|-----|-------|----------|
| Local | `D:\GitHub\Vusista` | 8008 (browser) / venster via `native:serve` | SQLite (app-data) |
| E2E | idem | 8018 | SQLite (`database/e2e.sqlite`) |
| Staging | `/var/www/vusista/staging` | php-fpm socket | browser-demo, dummydata |
| Production | `/var/www/vusista/production` | — | **bevroren** |

## Stack

Laravel 12, Blade + Livewire v4 + Alpine, SQLite, NativePHP (Electron), exiftool +
ffmpeg (meegeleverde binaries), Leaflet/OpenStreetMap + Nominatim.
Geen imagick/libheif (ffmpeg dekt HEIC). Geen auth, geen PWA.

## Let op (projectspecifiek)

- **PHP 8.4 vereist** (NativePHP 1.x wil ^8.3). Laragon-default is 8.2 → PATH prefixen:
  `export PATH="/c/laragon/bin/php/php-8.4.23-Win32-vs17-x64:$PATH"`
- **`resources/binaries/` is gitignored** (exiftool/ffmpeg) — zonder die binaries
  skippen de integratietests stilzwijgend.
- **`php -S` is single-threaded op Windows** → zie
  [../patterns/php-built-in-server-beperkingen.md](../patterns/php-built-in-server-beperkingen.md).
  Raakt zowel de app (NativePHP gebruikt `php -S`) als de E2E-suite.
- **Test nooit de build uit `dist/`** tijdens ontwikkelen: die bevat de code van het
  moment van bouwen. Gebruik `native:serve`.

## Documentatie

Project-specifieke docs staan **in het project**: `D:\GitHub\Vusista\docs\`
(product, techniek, besluiten/ADR's, runbooks, valkuilen). Begin bij
`docs/README.md`. Sessiewerk staat in `.claude/`.

Zoeken: `php artisan docs:search "<onderwerp>" --project=vusista`
