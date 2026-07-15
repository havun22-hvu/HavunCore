---
title: Security-review JudoToernooi ↔ JudoScoreBoard API (2026-07-15)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Security-review — JudoToernooi ↔ JudoScoreBoard

> **Aanleiding:** Henk vroeg (15 jul 2026) of de koppeling veilig genoeg is om externe testers toe te laten.
> **Conclusie:** Nee, niet in de huidige staat — maar niet vanwege de testers. Elk uitgegeven
> scorebord-token heeft nu schrijfrechten op het héle systeem, ook op andere toernooien.
> **Fixen = JudoToernooi-sessie.** Dit doc is de bevindingenlijst.

## Het beveiligingsmodel (zoals gebouwd)

1. Organisator maakt een `DeviceToegang` (rol `scoreboard`/`mat`) → 12-teken code (`Str::random(12)`, ~62 bits).
2. App POST `/api/scoreboard/auth` met die code → krijgt Bearer token (`bin2hex(random_bytes(32))`, 256 bits) + `reverb_config` (incl. `app_key`).
3. Verdere calls met `Authorization: Bearer <token>`, gevalideerd door `CheckScoreboardToken`.

De APK is publiek downloadbaar (`config/scoreboard.php` → `SCOREBOARD_DOWNLOAD_URL`), dus
"de app in handen krijgen" is geen drempel en was dat nooit. Het enige echte secret is de code.

## Wat goed is (app-kant — JudoScoreBoard)

- **Geen hardcoded token/secret** in de source; token is puur runtime (code → token-ruil).
- Token + auth-data in **SecureStore** (Android Keystore), met eenmalige migratie weg uit AsyncStorage.
- `usesCleartextTraffic="false"` + network-security-config (alleen system-CA's), `allowBackup="false"`.
- **Geen secrets in git of in de historie**; keystores/`keystore.properties` zijn ongetrackt.
- Geen `EXPO_PUBLIC_*`/`process.env` → niets gevoeligs in de bundle. Decompilatie levert alleen base-URL + endpoints.
- Geen certificate pinning (acceptabel gat, geen blocker).

## Bevindingen (server-kant — JudoToernooi)

### 🔴 1. `/api/scoreboard/result` scope't niet op het toernooi van het token
`ScoreboardController.php:116,134` — `'wedstrijd_id' => 'required|exists:wedstrijden,id'` +
`Wedstrijd::findOrFail(...)`, zónder check `$wedstrijd->...->toernooi_id === $toegang->toernooi_id`.

**Gevolg:** een token van toernooi A zet uitslagen op wedstrijden van toernooi B (andere organisator),
inclusief `EliminatieService`-bracketcorrecties en het doorschuiven van de mat-beurt. Optimistic
locking op `updated_at` is de enige rem en is te omzeilen (veld is `nullable` → weglaten).

**Contrast:** `ClubSyncController` scope't wél consequent (`Toernooi::where('organisator_id', ...)->findOrFail()`).
Dat is het patroon om te kopiëren.

### 🔴 2. `/api/scoreboard/event` lekt het volledige `DeviceToegang`-record op een publiek kanaal
Keten: `CheckScoreboardToken.php:36` (`$request->merge(['device_toegang' => $toegang])`) →
`ScoreboardController.php:281` (`$eventData = $request->all()`) → `ScoreboardEvent.php:39`
(`'data' => $this->eventData`) → publiek kanaal `scoreboard-display.{toernooiId}.{matId}`.
`DeviceToegang` heeft **geen `$hidden`**, dus alles serialiseert mee.

**Empirisch geverifieerd** (15 jul, wegwerp-probe met `Event::fake()`, daarna verwijderd) — payload bevatte:
`api_token`, `code`, `device_token`, `naam`, `telefoon`, `email`.

**Gevolg:** elke `timer.start`/`score.update` zendt het Bearer-token van dat scorebord naar iedereen
die meeluistert. Gecombineerd met #1 = volledige schrijfrechten op elk toernooi. Kanaalnamen zijn
`{toernooi_id}.{mat_id}` → triviaal te enumereren.

### 🟡 3. Alle scoreboard-kanalen zijn publiek; `channels.php` wordt niet eens geladen
Events gebruiken `Illuminate\Broadcasting\Channel` (public), geen `PrivateChannel`.
Er is geen `withBroadcasting()` in `bootstrap/app.php` en geen `BroadcastServiceProvider` →
`routes/channels.php` is **dode code** (en zou sowieso niets tegenhouden: alles op één na `return true`).
Met de `app_key` (uitgedeeld bij elke auth + aanwezig in publieke blade-views) kan iedereen meeluisteren
op elk toernooi/mat.

### 🟡 4. Geen throttle op de beschermde routes
`api.php:42` = alleen `Route::middleware('scoreboard.token')`. `current-match`, `result`, `event`,
`heartbeat`, `tv-link` hebben géén rate limit. `/auth` heeft wél `throttle:login` (5/min/IP) en de
club-groep wél `throttle:api` — dus dit is een omissie, geen bewuste keuze.

### 🟡 5. Tokens hebben geen expiry en geen revocatie
`api_token` (plaintext in `device_toegangen.api_token`) kent geen `expires_at` en geen intrek-pad.
Een token blijft geldig tot iemand opnieuw `auth()` doet op dezelfde code. Een tester houdt zijn
toegang dus permanent. Validatie is een SQL-equality-lookup op een unique index (geen `hash_equals`,
geen hashing — praktisch risico laag, principieel onnetjes).

### 🔵 6. CORS staat open op `/api/*`
Geen `config/cors.php` → framework-default `allowed_origins: ['*']`, `allowed_methods: ['*']`,
`allowed_headers: ['*']`, `supports_credentials: false`. Geen cookies, dus beperkt — maar elke site
kan met een token vanuit de browser tegen de API praten.

### 🔵 7. Datagevoeligheid van de normale payload is laag
`formatMatch()` geeft judoka-id, **naam** en **clubnaam** — geen geboortedatum, e-mail, gewicht, adres.
Dat is wat sowieso op het scorebord in de zaal staat. Wel: namen van (jeugd-)judoka's gaan via
`web.php:741` (`/live/scorebord/{mat}/state`, geen auth, geen throttle) en de publieke Reverb-kanalen
zonder enige drempel naar buiten. AVG-afweging, geen hack.

## Contract-afwijking (los van security)

`JudoScoreBoard/CONTRACTS.md` **C-02** stelt: *"Het scoreboard ontvangt alleen scores van JudoToernooi.
Geen lokale wijziging. JSB toont — JT bepaalt."* De praktijk is omgekeerd: `ControlScreen` POST
uitslagen naar `/scoreboard/result`. Ook **C-03** noemt "OAuth-token"; het is een 12-teken code.
Contract of code moet bijgewerkt — bewuste keuze van Henk.

## Advies

**Blokkerend vóór externe testers (of vóór welke breed uitgedeelde code dan ook):**
1. `result()` scopen op `$toegang->toernooi_id` (kopieer het `ClubSyncController`-patroon).
2. `/event`: niet `$request->all()` broadcasten — whitelist de velden, of haal `device_toegang`
   uit de payload. Plus `$hidden = ['api_token', 'device_token', 'code']` op `DeviceToegang` als vangnet.
3. `throttle:api` op de beschermde groep.

**Aanbevolen daarna:** private channels (vereist `withBroadcasting()` + echte auth-callbacks),
token-expiry/revocatie, expliciete `config/cors.php`.

**Testers los daarvan:** geef ze een **eigen staging-instance met eigen DB** en wegwerp-toernooien —
nooit een code op de productie-DB. Dan is #1/#2 niet ineens ongevaarlijk, maar de blast radius is
beperkt tot testdata. Herkomst van de tester is niet de risicofactor; het uitgegeven token is dat.
