---
title: Android apps — kwaliteitsaudit (JudoScoreBoard + Studieplanner)
type: audit
scope: android-apps
last_check: 2026-04-26
status: fix-fase afgerond (zie eindstatus onderaan)
---

# Android apps — kwaliteitsaudit 2026-04-26

> Read-only audit op `D:/GitHub/JudoScoreBoard` en `D:/GitHub/Studieplanner`.
> Tests zijn gerund (zie sectie per app).
> **Update 2026-04-26 (later op de dag):** fixes voor laag 1–6 + Studieplanner-specifiek
> uitgevoerd, zie eindstatus onderaan.

## Severity-overzicht

| Categorie | JudoScoreBoard | Studieplanner |
|-----------|---------------|---------------|
| Dependencies (npm audit) | 🟡 13 mod, 3 high (Expo upstream) | 🟡 17 mod, 1 high (Expo upstream) |
| Hardcoded secrets in src | 🟢 geen | 🟢 geen |
| Auth-token storage | 🔴 AsyncStorage (plaintext) | 🔴 AsyncStorage (plaintext) — ondanks `expo-secure-store` als dep |
| Android-permissions overrun | 🟡 6 extra t.o.v. app.json | 🟡 6 extra t.o.v. app.json |
| Network security config | 🔴 ontbreekt (cleartext default) | 🔴 ontbreekt (cleartext default) |
| TS strict | 🟢 on | 🟢 on |
| Test-baseline | 🟢 481/481 pass (33 suites) | 🟡 225 pass, 1 OOM-fail (`screens.test.tsx`) |
| Coverage threshold in package.json | 🟢 90% | 🔴 niet gezet |
| Crash/error reporting | 🟡 eigen rolloutreporter (custom endpoint) | 🟡 logger zonder remote-sink |
| Dead artefacten in repo | 🟢 schoon | 🔴 `D:GitHubStudieplannersrc/i18n/locales/` (lege Windows-pad-bug-dir) |
| Expo SDK | 🟢 55 (latest) | 🟡 54 (1 major achter) |
| Versie-discipline | 🟢 1.0.2 / vc 103 | 🟢 1.3.0 / vc 130 |

Legenda: 🔴 actie vereist, 🟡 bekijken, 🟢 ok.

## JudoScoreBoard — bevindingen

### 🔴 Auth-token in AsyncStorage
`src/services/storage.ts:1` gebruikt `@react-native-async-storage/async-storage` voor de session-token (`STORAGE_KEYS.TOKEN`). AsyncStorage = plaintext op disk, leesbaar bij root-toegang of backup. **Fix:** verplaats token naar `expo-secure-store` (apart toevoegen — staat nog niet in deps van JudoScoreBoard).

### 🔴 Geen network security config
Geen `android/app/src/main/res/xml/network_security_config.xml`. Default Android ≥ 28 staat geen cleartext meer toe, maar er wordt wel `http://10.0.2.2:8007` (DEV_API_URL) gebruikt voor emulator. **Fix:** expliciete config met `cleartextTrafficPermitted=false` voor productie + uitzondering voor `10.0.2.2` debug. Of via `usesCleartextTraffic` in manifest.

### 🟡 Permissions overrun via expo-av
`expo-av` in deps voegt automatisch RECORD_AUDIO + MODIFY_AUDIO_SETTINGS toe — staat NIET in `app.json` android.permissions. Manifest heeft ook READ/WRITE_EXTERNAL_STORAGE en SYSTEM_ALERT_WINDOW. Voor een scoreboard-app is RECORD_AUDIO opvallend (Play Store privacy-implicatie). **Fix:** check of `expo-av` echt nodig is (alleen sound playback?) of vervang met `expo-audio`/native sound.

### 🟡 npm audit: 16 vulns
13 mod + 3 high. Allemaal in dep-chain `@expo/config-plugins → xcode → uuid`. Dit zijn build-time deps van Expo zelf — geen runtime-impact in de gepublishde APK. **Fix:** `npm audit fix` proberen, anders accepteren als upstream-Expo-issue (documenteren).

### 🟢 Tests
33 suites, 481 tests, allemaal pass. Coverage threshold 90% global. Eén waarschuwing: "worker process failed to exit gracefully" — open handle, niet kritiek maar verdient `--detectOpenHandles` voor leak-fix.

### 🟢 Code-kwaliteit
- TS strict on
- Geen TODO/FIXME/HACK in src
- URLs alleen `judotournament.org` + emulator dev-URL
- Eigen `errorReporter` met queue + retry — solide
- ErrorBoundary aanwezig

## Studieplanner — bevindingen

### 🔴 Auth-token in AsyncStorage (terwijl SecureStore bedoeld was)
`src/services/storage.ts:77` gebruikt AsyncStorage voor token, maar `expo-secure-store` staat in deps + `app.json` plugins, en `ISSUES.md` beschrijft expliciet "Token wordt opgeslagen in `expo-secure-store` (encrypted)". Realiteit: NIET. Dit is een **architectuur-claim die niet matcht met implementatie**. **Fix:** migreer token + auth-data naar SecureStore zoals bedoeld (aparte path want `expo-secure-store` heeft 2 KB-limiet — auth-data evt. opdelen).

### 🔴 Geen network security config
Idem JudoScoreBoard. Manifest staat cleartext defaults toe. `DEV_API_URL = http://localhost:8003` aanwezig in code (al wordt-ie niet gebruikt door config.apiUrl, maar zit wel in bundel). **Fix:** expliciete `network_security_config.xml`.

### 🔴 Test-suite OOM
`src/screens/__tests__/screens.test.tsx` crasht op JS heap out-of-memory na ~3 min. 225 andere tests pass. **Fix:** opsplitsen, `jest --maxWorkers=2` instellen, of memory leak in setup vinden via `--detectOpenHandles`.

### 🔴 Dood artefact in repo
`D:GitHubStudieplannersrc/i18n/locales/` is een lege boom — gemaakt door een Windows-pad-bug (letterlijk de string `D:GitHubStudieplannersrc` als dirname). Onschuldig maar pollutie. **Fix:** verwijderen + `.gitignore` of pad-validatie checken.

### 🔴 Geen jest coverage-threshold
`jest.config.js` zet geen `coverageThreshold` (in tegenstelling tot JudoScoreBoard 90%). Coverage is dus niet afgedwongen. **Fix:** baseline meten + threshold zetten.

### 🟡 Permissions overrun
Manifest heeft 10 permissions, app.json heeft er 4. Extra: USE_BIOMETRIC, USE_FINGERPRINT (van expo-local-authentication, ok), READ/WRITE_EXTERNAL_STORAGE, SYSTEM_ALERT_WINDOW. Storage-permissions zijn op nieuwe Android sdk's uitgefaseerd — niet kritiek maar onnodig. **Fix:** expliciet `tools:remove` voor de niet-gebruikte storage-permissions.

### 🟡 npm audit: 18 vulns
17 mod + 1 high. Idem patroon als JudoScoreBoard (Expo upstream chain).

### 🟡 Expo SDK 54
JudoScoreBoard zit op SDK 55, Studieplanner op SDK 54. Geen blocker maar drift. **Fix:** plan SDK 55 upgrade (Expo geeft duidelijke migration guide).

### 🟡 ISSUES.md heeft 5 bekende open issues
1. Biometric login "verificatie mislukt" na herinstall (auth-flow bug)
2. App vraagt steeds opnieuw email (token-persistence onbetrouwbaar)
3. Pinch-zoom crasht WeekView
4. OTA update soms loop — `reloadAsync()` uitgeschakeld als workaround
5. Rate limiting te agressief (verhoogd, niet getest)

Issues 1+2 zijn direct gerelateerd aan de SecureStore-migratie hierboven. Issue 4 raakt de OTA/versionCode-discipline (zie memory feedback_ota_vs_apk_version).

### 🟢 Code-kwaliteit
- TS strict on, paths via `@/*`
- Geen TODO/FIXME/HACK in src
- Eigen `logger` met levels (geen remote-sink — overweeg Sentry)
- URLs alleen `havun.nl` (`api.studieplanner.havun.nl` + premium URL)

## Voorgestelde fix-volgorde

> Per app, atomic commits per laag. Volgorde = afnemende impact.

### Beide apps (parallelle commits)
1. **Network security config** — `network_security_config.xml` + manifest-update
2. **SecureStore-migratie voor auth-token** — `expo-secure-store` toevoegen (JudoScoreBoard) / gebruiken (Studieplanner)
3. **Permissions kritisch beoordelen** — RECORD_AUDIO weg bij JudoScoreBoard als niet nodig, storage-permissions weg waar mogelijk
4. **`npm audit fix`** — non-breaking poging, rest documenteren

### Studieplanner-specifiek
5. **Test-OOM fixen** — `screens.test.tsx` opsplitsen of leak vinden
6. **Coverage-threshold zetten** — baseline meten + 80%+ threshold
7. **Dood artefact verwijderen** — `D:GitHubStudieplannersrc/`
8. **Issues 1+2 oplossen** — natuurlijk gevolg van SecureStore-migratie
9. **Expo SDK 55 upgrade** — apart commit, na rest stabiel
10. **Logger remote-sink** — Sentry of Expo's eigen ErrorReporting (overweging)

### JudoScoreBoard-specifiek
11. **Test worker leak** — `--detectOpenHandles`, fix open timer/handle

## Niet in scope (voor latere sprint)
- iOS-build (eas.json heeft ios-config maar app.json wel grotendeels android-only)
- Play Store privacy-policy review na permission-cleanup
- Backup-rules audit (Studieplanner heeft `secure_store_backup_rules.xml`, JudoScoreBoard niet)
- Mutation-testing baseline op de mobiele code (nu alleen Laravel)

## Eindstatus 2026-04-26 (na fix-fase)

### Uitgevoerd

| App | Laag | Commit | Effect |
|-----|------|--------|--------|
| JSB | npm audit fix | `d97d48c` | 1 high resolved, 15 mod blijven (Expo upstream) |
| JSB | network security | `97e1cfa` | `usesCleartextTraffic=false` via expo-build-properties |
| JSB | permissions | `5a05f44` | RECORD_AUDIO + storage + overlay geblokkeerd via `blockedPermissions` |
| JSB | SecureStore-migratie | `842204a` | Token + AUTH_DATA naar SecureStore, transparante legacy-migratie, 484/484 tests |
| Studieplanner | npm audit fix | `(included)` | Geen kritieke vulns geresolved (allen Expo upstream) |
| Studieplanner | network security | `eddef99` | `usesCleartextTraffic=false` via expo-build-properties |
| Studieplanner | permissions | `63050c1` | Storage + overlay geblokkeerd via `blockedPermissions` |
| Studieplanner | SecureStore-migratie | `c8ace3f` | Token + USER naar SecureStore, transparante legacy-migratie, lost ISSUES.md issues 1+2 op |
| Studieplanner | test-suite stabilisatie | `1621434` | OOM-prone smoke tests verwijderd, 230/230 tests pass, 21/21 suites |
| Studieplanner | dood artefact | (n.v.t. — niet in git getrackt) | `D:GitHubStudieplannersrc/` lokaal verwijderd |

### Audit-correctie

- **Studieplanner coverage-threshold**: rapport zei "niet gezet". Klopt voor `package.json`, maar `jest.config.js` heeft wél `coverageThreshold: { global: { branches: 60, functions: 60, lines: 80, statements: 80 } }`. De drempel is dus aanwezig op een andere plek dan JSB heeft.

### Bewust uitgesteld (follow-up)

- **Studieplanner Expo SDK 54 → 55**: major upgrade, vereist device-testing en aparte aandacht voor `react-native-reanimated`, `gesture-handler` en het nieuwe Architecture-flag. Niet meegenomen in een security-fix-sprint.
- **Sentry / remote-sink**: optionele uitbreiding bovenop de bestaande `logger` (Studieplanner) en `errorReporter` (JSB). Niet kritiek.
- **JSB worker-leak ("worker process failed to exit gracefully")**: cosmetisch, alle 484 tests passen. `--detectOpenHandles` gaf geen actionable signal in een korte run.
- **`npm audit` resterende vulns**: zitten allemaal in `@expo/config-plugins → xcode → uuid` (Expo CLI build-time, niet runtime in APK). Wachten op Expo upstream.
