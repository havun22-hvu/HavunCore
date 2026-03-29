# Runbook: GitHub Actions CI/CD

> Automatische tests bij elke push/PR. Voorkomt dat kapotte code op master terechtkomt.

## Overzicht per project

| Project | CI | Tests | Coverage | Branch |
|---------|-----|-------|----------|--------|
| HavunCore | tests.yml | PHPUnit | nee | master |
| JudoToernooi | ci.yml | PHPUnit + Pint + PHPStan + Security | xdebug | main |
| Herdenkingsportaal | ci.yml | PHPUnit + Security + Integrity | pcov | main |
| HavunAdmin | ci.yml | PHPUnit + Security | pcov | main |
| Studieplanner-api | tests.yml | PHPUnit | nee | master |
| Studieplanner (Expo) | ci.yml | Jest + TypeScript + Integrity | nee | master |
| JudoScoreBoard (Expo) | ci.yml | Jest + TypeScript + Integrity | nee | master |

## Wat wordt getest?

### Laravel projecten (PHPUnit)
1. Composer dependencies installeren
2. SQLite test database opzetten
3. Migrations draaien
4. `php artisan test` met coverage
5. `composer audit` (security check)
6. `.integrity.json` validatie (indien aanwezig)

### Expo/React Native projecten (Jest)
1. npm dependencies installeren
2. `npm test` met coverage
3. TypeScript type check (`tsc --noEmit`)
4. `.integrity.json` validatie (indien aanwezig)

## Wanneer draait CI?

- Bij elke **push** naar master/main
- Bij elke **pull request** naar master/main

## CI faalt — wat nu?

1. Ga naar GitHub → Actions tab → klik op de falende run
2. Bekijk welke stap faalt
3. Fix lokaal → push opnieuw → CI draait automatisch weer

## Nieuw project toevoegen

### Laravel
Kopieer `.github/workflows/ci.yml` van Herdenkingsportaal en pas aan:
- Branch naam (main/master)
- Working directory (indien genest, bijv. `laravel/`)
- PHP versie

### Expo/React Native
Kopieer `.github/workflows/ci.yml` van Studieplanner en pas aan:
- Branch naam
- Node versie

## Kosten

GitHub Actions is gratis voor private repos:
- 2000 minuten/maand (genoeg voor ~200 pushes)
- Geen extra kosten zolang je binnen limiet blijft

---

*Aangemaakt: 29 maart 2026*
