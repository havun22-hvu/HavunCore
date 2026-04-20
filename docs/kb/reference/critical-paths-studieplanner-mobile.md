---
title: Studieplanner (mobile) — kritieke paden (audit-bewijs)
type: reference
scope: studieplanner-mobile
status: BINDING
last_reviewed: 2026-04-21
follows: "test-quality-policy.md"
---

# Kritieke paden — Studieplanner (Expo mobile)

> Deze paden moeten **100 %** gedekt zijn met zinvolle Jest-tests.
> Audit-bewijs voor de Expo-app die de Studieplanner-api consumeert.

Studieplanner mobile draait op leerlingen-devices. De data is
minderjarige-data (roosters, cijfers, mentor-notities). Een bug in de
offline-opslag of in de API-client kan persoonlijke data lekken naar
het verkeerde device of op onveilige plek (AsyncStorage-plaintext
i.p.v. SecureStore) belanden.

Repo-pad: `D:/GitHub/Studieplanner`. Test-framework: **Jest + jest-expo**
(niet PHPUnit). De `critical-paths:verify` command ondersteunt nu
Laravel-projecten; voor dit project werkt file-existence-check wel
maar `--run` niet. De test-runner per-project is `npm test -- <file>`.

## Pad 1 — API-client (backend-communicatie)

**Waarom kritiek:** alle data komt hier door. Token-handling, HTTP-
error-handling, retry-logica: fouten hier = blank dashboard of
verkeerde data zichtbaar.

**Componenten:**

- `src/services/api.ts`

**Branches / edge-cases:**

- [ ] GET met geldig token → data, statuscode check.
- [ ] 401 → token refresh of logout-flow.
- [ ] 5xx → error-state, geen crash.
- [ ] Timeout → graceful error.
- [ ] Offline → cached-data fallback via storage.

**Tests:**

- `src/services/__tests__/api.test.ts`

**Coverage-target:** 80 % lines, 70 % branches.

## Pad 2 — Storage (persistente data + SecureStore secrets)

**Waarom kritiek:** scheiding tussen ASyncStorage (plain) en SecureStore
(encrypted). Fout = tokens in plain storage → device-compromise lekt
kinderdata. Ook: backup/restore correctness.

**Componenten:**

- `src/services/storage.ts`
- `src/services/backup.ts`

**Branches / edge-cases:**

- [ ] getItem / setItem / removeItem idempotent.
- [ ] Tokens via SecureStore, geen AsyncStorage-fallback.
- [ ] Backup serialisatie deterministisch.
- [ ] Restore: failing checksum → weiger + user-prompt.

**Tests:**

- `src/services/__tests__/storage.test.ts`
- `src/services/__tests__/backup.test.ts`

**Coverage-target:** 90 % — secret-handling.

## Pad 3 — Push notifications + alarmering

**Waarom kritiek:** notifications leveren tijd-kritieke alerts (toetsen,
inleverdata). Verkeerde tijd-berekening = gemiste deadline =
leerling-paniek.

**Componenten:**

- `src/services/pushNotifications.ts`
- `src/services/alarmService.ts`
- `src/services/backgroundTimer.ts`
- `src/services/dailySummary.ts`

**Branches / edge-cases:**

- [ ] Push-token registratie met permission granted.
- [ ] Permission denied → graceful degradation.
- [ ] Alarm schedule-time correct berekend (timezone-safe).
- [ ] Daily-summary batch stuurt niet dubbel.
- [ ] BackgroundTimer cleanup bij unmount — no leak.

**Tests:**

- `src/services/__tests__/pushNotifications.test.ts`
- `src/services/__tests__/alarmService.test.ts`
- `src/services/__tests__/backgroundTimer.test.ts`
- `src/services/__tests__/dailySummary.test.ts`

**Coverage-target:** 80 %.

## Pad 4 — Device + update-check (PWA/OTA hygiëne)

**Waarom kritiek:** device-binding (preventie van account-overname) en
OTA update-flow (juiste versie-detectie voorkomt update-loops).

**Componenten:**

- `src/services/device.ts`
- `src/services/updateChecker.ts`

**Tests:**

- `src/services/__tests__/device.test.ts` (100 % na 20-04 sessie)
- `src/services/__tests__/updateChecker.test.ts`

**Coverage-target:** 90 % — OTA-logica lastig te fixen in productie.

## Pad 5 — Planning-berekeningen + formatters

**Waarom kritiek:** de kernalgoritme — "wanneer moet ik wat leren" —
bepaalt de waarde van de app. Fout in planning.ts = verkeerd advies =
leerling mist of over-werkt.

**Componenten:**

- `src/utils/planning.ts`
- `src/utils/formatters.ts`
- `src/utils/fuzzyMatch.ts`
- `src/utils/generateId.ts`
- `src/utils/colors.ts`

**Tests:**

- `src/utils/__tests__/planning.test.ts`
- `src/utils/__tests__/formatters.test.ts`
- `src/utils/__tests__/fuzzyMatch.test.ts`
- `src/utils/__tests__/generateId.test.ts`
- `src/utils/__tests__/colors.test.ts`

**Coverage-target:** 90 % — pure logic, makkelijk te dekken.

## Pad 6 — Config + theme constants

**Waarom kritiek:** configuratie-defaults. Verkeerde API-base-URL =
app stuurt data naar verkeerde backend.

**Componenten:**

- `src/constants/config.ts`
- `src/constants/theme.ts`

**Tests:**

- `src/constants/__tests__/config.test.ts`
- `src/constants/__tests__/theme.test.ts`

**Coverage-target:** 100 %.

## Pad 7 — Hooks (React-specifieke logica)

**Componenten:**

- `src/hooks/useDebouncedCallback.ts`

**Tests:**

- `src/hooks/__tests__/useDebouncedCallback.test.ts`

**Coverage-target:** 100 %.

## Test-runner / CI

```bash
cd D:\GitHub\Studieplanner
npm test                 # quick
npm test -- --coverage   # for baseline
```

Coverage-drempels (in `jest.config.js`):
- statements ≥ 80 %
- branches ≥ 60 %
- lines ≥ 80 %
- functions ≥ 60 %

Actueel (21-04): 81,33 % statements, 83 % lines — ná de 20-04 push
via `device.test.ts` + logger-exclude.

## Audit-checklist

1. Klopt het aantal paden? (7).
2. Tests actueel? — `critical-paths:verify --project=studieplanner-mobile`
   (file-existence); `npm test` voor de echte pass/fail.
3. Mutation testing? → Stryker is de JS-equivalent van Infection;
   baseline nog op te stellen.

## Proces

- **Bij elke PR** die een kritiek pad raakt: update "branches" en "tests".
- **Maandelijks**: Stryker-run + update `last_reviewed`.
- **Screens** (`src/screens/`) + **components** (`src/components/`) staan
  expliciet NIET op deze lijst — dat is glue-laag (policy §3).
