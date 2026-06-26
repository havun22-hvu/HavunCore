---
title: "Blueprint: JudoToernooi-kant HavunClub-koppeling"
type: plan
scope: judotoernooi
last_check: 2026-06-27
status: klaar om te droppen in JudoToernooi/.claude/blueprint.md
---

# Blueprint — JudoToernooi API voor HavunClub-koppeling

> Voorbereid in HavunCore (orchestrator). **Bouwen gebeurt in een JudoToernooi-sessie:**
> kopieer dit naar `JudoToernooi/laravel/.claude/blueprint.md`, open daar een sessie, `/mpc` + "ga maar".
> Contract: `HavunCore/docs/kb/contracts/havunclub-koppelingen.md`.

## Doel
3 API-endpoints zodat HavunClub (de hub) judoka-stamdata pusht, inschrijft, en resultaten ophaalt.
HavunClub is master; JT slaat op en levert terug. **De api_key identificeert de club** — geen `tenant`-param.

## Auth — spiegel het scoreboard-token-patroon
JT heeft dit al: alias `scoreboard.token` in `bootstrap/app.php` → `CheckScoreboardToken`
(`bearerToken()` → `DeviceToegang::where('api_token',$t)->first()`, JSON-401 bij fout).

**Nieuw, identiek opgezet:**
1. Migratie `club_api_tokens` — `id`, `club_naam`, `token` (hashed of plain zoals device-tokens nu), `actief`, `last_used_at`, timestamps. (JT heeft geen club/tenant-model → deze tabel *is* de tenant-registratie.)
2. Middleware `CheckClubToken` (kopie van `CheckScoreboardToken`): valideer Bearer, zet de gevonden club op `$request->attributes->set('club', $token->club_*)`. JSON-401/403 bij ontbreken/ongeldig.
3. Alias `club.token` registreren in `bootstrap/app.php` naast `scoreboard.token`.
4. Key-uitgifte: artisan `club:token-create {club}` (printf token één keer) — zoals device-tokens. Dashboard-knop optioneel later.

## Endpoints — `routes/api.php`, groep `middleware('club.token')->prefix('api')`

| Methode + pad | Controller-actie | Doet |
|---|---|---|
| `POST /api/judokas` | `Api\ClubSyncController@upsertJudoka` | upsert `StamJudoka` |
| `POST /api/inschrijvingen` | `Api\ClubSyncController@inschrijven` | maak `Judoka` op toernooi |
| `GET /api/toernooien/{toernooi}/resultaten` | `Api\ClubSyncController@resultaten` | lever uitslagen |

### `POST /api/judokas` — idempotente upsert (KRITIEK)
- Request (FormRequest): `judotoernooi_id` (nullable), `voornaam`, `achternaam`, `geboortedatum`, `geslacht`, `band` (JBN-set valideren).
- **Upsert:** bestaat `judotoernooi_id` → `StamJudoka::find()` + update; anders create. Koppel aan de club uit de token.
- **Aanrader extra veiligheid:** kolom `stam_judokas.havunclub_ref` + unique (`club`, `havunclub_ref`) zodat een herhaalde sync zónder teruggekregen id óók niet dupliceert. HavunClub stuurt dan ook z'n eigen judoka-id mee.
- Response: `{ "id": <stamjudoka-id> }`. HavunClub bewaart die als `judotoernooi_id`.

### `POST /api/inschrijvingen`
- Request: `toernooi_id`, `judoka_id` (= StamJudoka-id), `naam`, `band`.
- Hergebruik de dedup-/limietlogica uit `CoachPortalController::storeJudokaCode` (`Judoka::where('toernooi_id')...`, `isMaxJudokasBereikt`, `canAddMoreJudokas`). Trek die naar een `InschrijvingService` zodat web + API dezelfde regels delen.
- Response: `{ "id": <judoka-id> }`.

### `GET /api/toernooien/{toernooi}/resultaten`
- Lees uitslagen zoals `PubliekResultatenController` dat al doet. Per judoka: `judoka_id`, `gewichtsklasse`, `resultaat`, `partijen`.
- **Open punt → vastleggen in contract:** `resultaat`-waardenset (goud/zilver/brons/…). Bevestig in `havunclub-koppelingen.md`.
- Scope de toernooi-resolutie op de club uit de token (een club mag geen andermans resultaten zien).

## Kwaliteit (Havun-normen — verplicht)
- **Form Requests** op beide POST's; **custom exceptions** + JSON-error-shape gelijk aan scoreboard.
- **Rate limiting** `throttle:api` op de groep.
- **Tests:** feature-tests per endpoint — happy path, 401 (geen/ongeldige token), idempotentie (2× zelfde judoka → 1 rij), inschrijf-limiet, cross-club-isolatie (token A mag toernooi B niet). Coverage >80%.
- **Audit log** op token-gebruik (`last_used_at` + log).

## Werkvolgorde
1. Migratie + `club_api_tokens` + `club:token-create`. 2. `CheckClubToken` + alias. 3. `InschrijvingService` extractie. 4. `ClubSyncController` + FormRequests + routes. 5. Tests. 6. Terugkoppelen open punten in het contract-doc.
