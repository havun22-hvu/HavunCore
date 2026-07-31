---
title: "Project: Vusista"
type: reference
scope: havuncore
last_check: 2026-07-15
---

# Project: Vusista

**Type:** Fotoalbum **desktop-app** (Laravel 12 + NativePHP/Electron)
**Status:** ⚠️ **Blijft staan — achtergrondmateriaal voor de herbouw.**
**Verwijderen mag pas als Vusista2 áf is** (Henk, 31-07-2026). Niet eerder, en niet "vast
alvast opruimen": de map, de GitHub-repo én de KB-index zijn de bron waar Vusista2 uit put.
**Demo:** vervallen — de serveromgeving is 31-07-2026 opgeruimd (zie Omgevingen)

> **Wat dit betekent voor wie hier komt werken:**
> - **Niets in Vusista 1 meer repareren.** De laatste V&K-scan gaf `critical 1 · high 2 · medium 4`
>   (25% form-validatie, `session.php` secure-default, verwijderde tests, guzzle-advisories).
>   Die blijven **bewust open** — fixen in een app die verdwijnt is weggegooid werk.
> - **`qv:scan` staat daarom uit** voor dit project (`enabled => false` in `quality-safety.php`,
>   mét reden). De **KB-index blijft wél draaien**: de docs zijn juist de waarde die overblijft.
> - **Wat je hier zoekt, zoek je als bronmateriaal:** de productbesluiten, `niet-doen.md` en de
>   valkuilen gaan ongewijzigd mee naar de herbouw. De *techniek* niet.

> ⛔ **Het fundament is verkeerd gekozen — herbouw ligt klaar (30-07-2026).** Een lokale
> fotomanager voor 76.797 bestanden en één gebruiker draait hier op Laravel + `php -S` in een
> Electron-schil. Die keuze is nooit gemaakt: het project begon als Laravel-project omdat élk
> Havun-project zo begon. Zes omwegen om het eigen fundament heen volgden; de zesde maakte de
> app stil onbruikbaar. Post-mortem: [`../patterns/fundament-versus-omweg.md`](../patterns/fundament-versus-omweg.md).
> Herbouwplan (Rust + Tauri v2) staat in `D:\GitHub\Vusista2\PLAN.md` en wacht op Henks "ga maar"
> — dat is een **Vusista2-sessie**, geen HavunCore-werk. De stack hieronder beschrijft dus de
> **huidige** app, niet de gewenste.

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
| ~~Staging~~ | ~~`/var/www/vusista/staging`~~ | — | **opgeruimd 31-07-2026** |
| ~~Production~~ | ~~`/var/www/vusista/production`~~ | — | **opgeruimd 31-07-2026** |

**De serveromgeving bestaat niet meer.** Vhosts, Let's Encrypt-cert en beide MySQL-databases
(`vusista_production`, `vusista_staging`) zijn 31-07 verwijderd: een desktop-app hoort daar niet
te draaien, en de staging-deploy faalde dertien dagen ongemerkt. Backup (65 MB tarball + db-dumps
+ nginx-configs): `/root/backups/vusista-cleanup-2026-07-31` op 188.245.159.115.
**Het DNS-record `vusista.havun.nl` staat er nog** — dat is Henks actie bij mijn.host.

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
