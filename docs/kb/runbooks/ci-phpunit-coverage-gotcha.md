---
title: CI Gotcha: PHPUnit 11 Auto-Coverage Timeout
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# CI Gotcha: PHPUnit 11 Auto-Coverage Timeout

> **Datum:** 13 april 2026  
> **Impact:** CI hing 6x achter elkaar op 30+ min timeout

## Probleem

PHPUnit 11+ genereert **automatisch** code coverage als:
1. Er een `<source>` sectie in `phpunit.xml` staat
2. Een coverage driver (PCOV of Xdebug) als PHP extensie geladen is

Dit gebeurt **zelfs zonder** `--coverage-*` CLI flags. 

### Symptomen
- CI "Run tests" stap duurt 30+ minuten in plaats van 2 minuten
- Lokaal werkt alles snel (geen PCOV geïnstalleerd lokaal)
- Lijkt alsof een test hangt, maar het is coverage-generatie

## Oplossing

In de CI workflow BEIDE maatregelen nemen:

```yaml
# Snelle test run (geen coverage)
- name: Run tests
  run: php -d pcov.enabled=0 vendor/bin/phpunit --no-coverage

# Aparte coverage stap (optioneel, met timeout)
- name: Generate coverage
  run: php -d pcov.directory=app vendor/bin/phpunit --coverage-clover=coverage.xml --testsuite=Unit
  continue-on-error: true
  timeout-minutes: 10
```

### Waarom beide flags nodig?
- `-d pcov.enabled=0` → voorkomt dat PHP de PCOV extensie activeert
- `--no-coverage` → vertelt PHPUnit expliciet om geen coverage te genereren

Eén van de twee is niet genoeg:
- Alleen `--no-coverage` → PCOV is nog steeds geladen, PHP doet extra werk
- Alleen `-d pcov.enabled=0` → PHPUnit 11 probeert nog steeds coverage te doen via de `<source>` config

## Referentie
- PHPUnit 11 changelog: `<source>` vervangt `<coverage><include>` en triggert auto-coverage
- GitHub Actions workflow: `.github/workflows/tests.yml`
