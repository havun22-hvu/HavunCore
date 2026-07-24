---
title: Testschuld — waar de dekking niet is wat het cijfer suggereert
type: reference
scope: havuncore
last_check: 2026-07-24
---

# Testschuld

**Gemeten 24-07-2026: 88,6 % over 1312 tests** (3658 assertions, 269-306 s, PCOV, twee onafhankelijke
runs met identieke tabel). **2,8 assertions per test.** Geen enkele `assertStatus(500)` en geen
`assertTrue(true)` in de hele suite — het cijfer is niet met kapotte foutpaden opgeklopt.
De 503-asserties in `AIProxyControllerTest` en `PushNotificationTest` leggen correct gedrag vast.

De verdeling klopt grotendeels: de kritieke paden staan bovenaan, niet de modellen. Eén ding klopt
niet, en het is het ergste dat kon ontbreken: **de guard die na het secret-lek van 19-07 is
toegevoegd, wordt door geen enkele test geasserteerd.** Daarnaast staan twee credential-schrijvende
commands op 0 %.

## Kritieke paden — gemeten

| Onderdeel | Dekking | Oordeel |
|---|---|---|
| `VaultController` + 4 vault-modellen + `EnsureAdminToken` + 4 vault-FormRequests | **100 %** | voldoet aan laag 1 |
| `AIProxyController` / `AIProxyService` | 100 % / 99,0 % | ok |
| `AutoFixController` / `AutoFixService` | 100 % / 100 % | ok |
| `QrAuthController` / `QrAuthService` / `WebAuthnController` | 95,0 / 98,4 / 96,6 % | ok |
| `DocIndexer` (KB-indexer) | 96,8 % | **de ontbrekende 3,2 % is de beveiliging — zie hieronder** |

## De secret-guard van 19-07 is nooit getest

`app/Services/DocIntelligence/DocIndexer.php:48-57`. Geen enkele test in `tests/` noemt
`credentials.md`, `.env` of `isSensitiveFile` — met grep over de hele suite geverifieerd.

- **regel 52** (`credentials.md` → skip) wordt wél geraakt, maar per ongeluk: geen test asserteert
  dat er niets in `doc_embeddings` belandt.
- **regel 56** (`.env` / `.env.<omgeving>` → skip) wordt **nooit uitgevoerd**.
- **regel 194-195** (dezelfde skip in de code-bestandenlus) wordt **nooit uitgevoerd**.

Dit is exact de bug-regressietest die het incident had moeten opleveren. 96,8 % op dit bestand
suggereert dekking die er op de enige regel die telt niet is.

## Dunste plekken

| Bestand | Dekking | Waarom dat telt |
|---|---|---|
| `Console/Commands/VapidSetupCommand` | **0,0 %** | genereert een VAPID-keypair en schrijft die in de Vault; `--rotate` verbreekt élk browser-abonnement |
| `Console/Commands/VaultSetupMobileMonitoringCommand` | **0,0 %** | leest een GitHub-PAT, schrijft het secret, print het bearer-token |
| `Services/WebPushService` | **18,6 %** | hele verzendweg ongedekt, inclusief het verwijderen van verlopen subscriptions |
| `Casts/Float32Vector` | 20,0 % | embedding-decoding; stille corruptie = stille zoekfouten in de KB |
| `Models/DocIntelligence/DocChunk` | 0,0 % | chunked search is sinds 15-07 de KB-kern |
| `Http/Controllers/Api/ObservabilityController` | 50,4 % | regels 186-251 ongedekt |
| `Models/HealthAlert` / `Mail/DroogtestReminderMail` | 50,0 % / 33,3 % | |
| `Console/Commands/{CleanupMetrics,HavunGemini,HavunPack,AuditClaudeMd}` | 0,0 % | glue — mag laag blijven |

Credential-handling is laag 1. `VaultSetupVeenCommand` (bijna identiek werk) staat op 79,7 % mét
test; de twee hierboven kregen er nooit een. Dat is toeval, geen keuze.

Twee ongedekte invarianten blijken niet uit de tabel: **de auth-rate-limiter knijpt nergens in een
test** (`throttle:auth`, 5/min, staat op login/register/QR-generate/WebAuthn-login, maar `429` komt
alleen voor in AI-proxy- en AutoFix-tests), en **decrypt-failure op corrupte ciphertext** — vereiste
branch in `kb/reference/critical-paths-havuncore.md`, nergens getest.

## Padding

Minder dan in de andere projecten, maar aanwezig:

1. `tests/Unit/ModelCoverageTest.php:222-247` — twee relationship-type asserts
   (`assertInstanceOf(HasMany::class, …)`). Staat letterlijk onder "nooit testen".
2. `tests/Unit/ModelCoverageTest.php:105-125` — `test_has_changed_returns_false_for_unchanged_file`
   asserteert `assertTrue($doc->hasChanged())`. Naam en assertie spreken elkaar tegen; de comment
   erboven geeft toe dat het bedoelde pad niet bereikt wordt. Dekt hetzelfde als de test erboven.
3. `tests/Unit/ModelCoverageTest.php:278-299` — asserteert een hardcoded map van negen
   ontwikkelaarspaden (`D:/GitHub/…`). Config-constante, geen gedrag.
4. `tests/Unit/DocIndexerCoverageTest.php:511-560` — vijf varianten van `detectFileType`, via
   reflectie op een protected method. Implementatiedetail.
5. `tests/Unit/ModelCoverageTest.php:31-89` en `tests/Unit/AIProxyServiceCoverageTest.php:153-211` —
   4 + 4 period-varianten van hetzelfde where-patroon, elk met één assertie.
6. Elf bestanden heten `*CoverageTest` / `*ExtendedTest`, en tien docblocks zeggen letterlijk *"om
   HavunCore Unit-coverage richting 80 % te tillen"* (o.a. `tests/Unit/AuthDeviceTest.php:12-14`,
   `tests/Unit/VaultSecretTest.php:10-11`). De naam bekent een getal-doel; de inhoud is meestal wél
   zinvol (Vault-encryptie, CircuitBreaker-state-machine). **Hernoemen, niet schrappen.**
7. `tests/Unit/QrAuthDeviceUpdateHardeningTest.php:98` — een hardening-test die het flow-pad
   `markTestSkipped`t in plaats van het te asserteren.

## Aanpakvolgorde op risico

1. **Regressietest op `isSensitiveFile`** — indexeer een map met `credentials.md`, `.env` en
   `.env.production`, assert dat er geen rij en geen chunk ontstaat, en dek de code-bestandenlus mee.
   Koppel de testnaam aan het incident van 19-07.
2. **429 op `throttle:auth`** — één test per brute-force-endpoint dat de zesde poging knijpt.
3. **De twee credential-commands** (`vapid:setup`, `vault:setup-mobile-monitoring`) — idempotentie,
   `--rotate`, lege/ontbrekende input, en dat het secret versleuteld landt.
4. **`WebPushService`** — verzendpad met gefakete client: succes, verlopen subscription (rij weg),
   mislukking (rij blijft).
5. **Decrypt-failure** op corrupte ciphertext: geen cleartext in de response.
6. **Padding pas hierna** (punt 1-5 hierboven), apart via `refactor(tests): verwijder padding-tests`.
   Het percentage zakt daardoor — dat is de bedoeling, niet het probleem.

`CLAUDE.md` bevatte geen coverage-cijfer of drempel; daar is dus niets gecorrigeerd.
