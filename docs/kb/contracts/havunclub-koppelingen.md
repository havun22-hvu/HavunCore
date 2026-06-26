---
title: "Contract: HavunClub ↔ JudoToernooi ↔ HavunAdmin"
type: reference
scope: havuncore
last_check: 2026-06-27
status: JT + HA geïmplementeerd (feature-branches) — wacht op HavunClub-bevestiging
---

# Integratiecontract HavunClub ↔ JudoToernooi ↔ HavunAdmin

> **Bron:** `HavunClub/docs/integratie-contract.md` (HavunClub-kant, al live).
> **Dit doc** = de door HavunCore vastgestelde afspraken + werklijst per app.
> Coördinatie loopt via HavunCore; elke app bouwt alleen in z'n eigen repo.

## Architectuur in één regel

**HavunClub is de hub.** Het is de enige app die met JudoToernooi én HavunAdmin praat.
JudoToernooi en HavunAdmin hebben onderling **geen** koppeling.

```
        push stamdata/inschrijving        pull facturen/betalingen
  JudoToernooi  ◄──────────  HavunClub  ──────────►  HavunAdmin
  (aanroeper = HavunClub)    (de hub)    (aanroeper = HavunAdmin)
```

---

## Vastgestelde beslissingen (Henk, 26 jun 2026)

### 1. Tenant-id — elke app blijft master van z'n eigen id
Er komt **geen** centrale HavunCore-uitgegeven club-id. Elke app houdt z'n eigen
identiteit; **HavunClub bewaart de crosswalk** (bestaande velden
`clubs.judotoernooi_tenant_id` + `clubs.havunadmin_tenant_id`, beide met api_key).

**Koppelsleutel = de opgeslagen, bevestigde mapping — NIET een runtime e-mailvergelijking.**
E-mail van de judoschoolhouder + naam van de judoschool zijn enkel de *menselijke
herkenning* in de HavunClub-beheer-UI `/koppelingen` ("is dit dezelfde school?").
De judoschoolhouder (of Henk) bevestigt de link één keer → vanaf dan draait alle sync
op `tenant_id` + `api_key`, niet op de string-match. Reden: e-mail/naam kunnen per app
verschillen of wijzigen; daarop joinen breekt de koppeling stil.

### 2. HavunAdmin-richting — HA trekt op (pull)
HavunAdmin **haalt op** uit HavunClub. HavunClub blijft master van de financiële data.
Sluit aan op HA's bestaande `InvoiceSyncController`. Het ongebruikte
`clubs.havunadmin_api_key`-veld (push-richting) **vervalt** — verwijderen aan HavunClub-zijde.

### 3. Factuurvelden — HavunClub levert platte factuur, HA boekt zelf in
HavunClub levert factuurkop + **regels met BTW per regel**. HavunAdmin wijst zelf
grootboek/kostenplaats toe (heeft al `LedgerAccount` + `invoices.category_id` +
`SetupImportFilters`, net als bij bunq/mollie/gmail-ingest). Grootboek hoeft dus **niet**
uit HavunClub te komen. BTW gaat per regel mee omdat HA's `invoice_items` dat al ondersteunt.

---

## Koppeling 1 — HavunClub → JudoToernooi

HavunClub roept aan; **JudoToernooi bouwt de endpoints**. JT heeft géén tenant-concept,
maar wél een bewezen Bearer-token-API-patroon (`scoreboard.token`-middleware,
`routes/api.php`). Hergebruik dat patroon: de **api_key identificeert de club** — een
aparte `tenant`-parameter is daarmee overbodig (JT leidt de organisator/club af uit de key).

**Auth:** `Authorization: Bearer <judotoernooi_api_key>` (per club, JT geeft uit via JT-dashboard).
**Base-URL:** bevestigen — `https://judotoernooi.nl/api` of `api.`-subdomein.

| Doel | Methode + pad | Request (HavunClub stuurt) | Response |
|---|---|---|---|
| Judoka-sync | `POST /api/judokas` | `judotoernooi_id`, `voornaam`, `achternaam`, `geboortedatum`, `geslacht`, `band` (officiële JBN) | `{ "id": "<jt-judoka-id>" }` |
| Inschrijven | `POST /api/inschrijvingen` | `toernooi_id`, `judoka_id`, `naam`, `band` | `{ "id": "<inschrijving-id>" }` |
| Resultaten | `GET /api/toernooien/{toernooi_id}/resultaten` | — | `[ { "judoka_id", "gewichtsklasse", "resultaat", "partijen" } ]` |

**Eisen aan JT-zijde:**
1. **Idempotentie:** `POST /judokas` = upsert op (club-uit-key + `judotoernooi_id`). Herhaalde sync → geen duplicaten.
2. `resultaat`-waardenset vastleggen (goud/zilver/brons/…) en in dit doc terugkoppelen.
3. Rate limiting (`throttle:api`) + JSON-401/403 bij onbevoegd, conform scoreboard-patroon.
4. Veldnamen inschrijving + resultaten bevestigen (of bovenstaande overnemen).

**Trigger (HavunClub, staat al):** judoka als wedstrijdjudoka opgeslagen bij gekoppelde club
→ async job `POST /judokas`, circuit-breaker-beschermd.

---

## Koppeling 2 — HavunAdmin ← HavunClub (HA trekt op)

**HavunAdmin roept aan**; HavunClub levert via z'n Sanctum-API `/api/v1` met een token dat
HavunClub uitgeeft (`/koppelingen`). Tenant = automatisch de club van het token; geen extra
parameter. HA hangt dit aan z'n bestaande `InvoiceSyncController` (zoals bunq/mollie/gmail).

**Auth:** `Authorization: Bearer <sanctum-token>`. Onbevoegd → JSON `401`/`403`. `throttle:api`.

| Doel | Methode + pad | Ability | Levert |
|---|---|---|---|
| Facturen | `GET /api/v1/facturen` | `facturen:read` | factuurkop + regels (zie payload) |
| Betalingen | `GET /api/v1/betalingen` | `betalingen:read` | betaling + koppeling naar factuur |

**Incrementeel ophalen:** beide endpoints `?sinds=<ISO-datum>` ondersteunen (HavunClub
heeft dit al op `judokas` — gelijktrekken). HA pollt periodiek met laatste sync-tijdstip.

**Factuur-payload (HavunClub → HA), mapt op HA's `invoices` + `invoice_items`:**
```jsonc
{
  "external_reference": "hc-factuur-1234",   // HavunClub factuur-id → HA external_reference (idempotentie)
  "invoice_number": "2026-0123",
  "invoice_date": "2026-06-01",
  "due_date": "2026-06-15",
  "payment_date": null,                       // gevuld zodra betaald
  "status": "open",                           // open | betaald | ...
  "description": "Contributie Q2 2026",
  "customer": { "naam": "...", "email": "...", "iban": "..." },
  "subtotal": 100.00, "vat_amount": 21.00, "vat_percentage": 21, "vat_type": "hoog", "total": 121.00,
  "regels": [
    { "description": "Contributie", "quantity": 1, "unit_price": 100.00,
      "vat_percentage": 21, "subtotal": 100.00, "vat_amount": 21.00, "total": 121.00 }
  ]
}
```
HA zet `source = "havunclub"`, bewaart `external_reference` voor dedup, en wijst
`category_id`/grootboek zelf toe bij ingest.

**Eisen aan HA-zijde:**
1. Pull-job + mapping payload → `Invoice` + `InvoiceItem` (hergebruik `InvoiceSyncController`-patroon).
2. Dedup op (`source=havunclub`, `external_reference`) — geen dubbele import.
3. Grootboek-/kostenplaats-toewijzing via bestaande import-filters.
4. Bevestigen of header-BTW volstaat of regels nodig zijn (contract levert beide).

---

## Werklijst per app

| App | Taak | Status |
|---|---|---|
| **JudoToernooi** | 3 API-endpoints bouwen op het `scoreboard.token`-patroon (key = tenant), idempotente judoka-upsert, key-uitgifte via dashboard | **te bouwen** |
| **HavunAdmin** | Pull-job tegen `GET /api/v1/facturen` + `/betalingen`, payload→Invoice/InvoiceItem, dedup op external_reference | **te bouwen** (op bestaande sync-laag) |
| **HavunClub** | `tenant`-param uit JT-calls halen (key volstaat); `havunadmin_api_key`-veld verwijderen; `?sinds=` op facturen/betalingen; verder bevestigen | **kleine aanpassing** (rest staat live) |
| **HavunCore** | Dit contract = single source of truth; mappings/keys eventueel via Vault (scoped per project) | **coördinatie** |

## Implementatie-blueprints (klaar om te droppen in de project-sessie)
- **JudoToernooi:** `docs/kb/plans/havunclub-koppeling-jt-blueprint.md` → kopieer naar `JudoToernooi/laravel/.claude/blueprint.md`.
- **HavunAdmin:** `docs/kb/plans/havunclub-koppeling-ha-blueprint.md` → kopieer naar `HavunAdmin/.claude/blueprint.md`.

## Implementatiestatus (27 jun 2026)

**JudoToernooi** — branch `feat/havunclub-koppeling` (commit `9102e6f`). 3 endpoints live op het
`club.token`-patroon (token = Organisator = tenant). Token uitgeven: `php artisan club:token-create`.
7 tests groen. Doc: `JudoToernooi/laravel/docs/2-FEATURES/HAVUNCLUB-KOPPELING.md`.
- **Antwoord `resultaat`-waardenset:** = `eindpositie` van de poule → **1 = goud, 2 = zilver, 3 = brons, …**; `partijen` = gewonnen+verloren+gelijk.
- **Let op veld-mapping:** JT mapt `voornaam`+`achternaam` → `naam` en `geboortedatum` → **alleen `geboortejaar`** (StamJudoka kent geen volledige datum). Optioneel `havunclub_judoka_id` meesturen = robuustere idempotentie.

**HavunAdmin** — branch `feat/havunclub-koppeling` (commit `adcfe32`). Pull via `sync:havunclub`
(scheduler elke 15 min, no-op zonder config). 4 tests groen. Doc: `HavunAdmin/docs/05-api-integration/HAVUNCLUB-SYNC.md`.
- **Antwoord BTW:** HA gebruikt **regel-BTW** (`regels[]` → `InvoiceItem`). Grootboek wijst HA zelf toe.
- **Nuance richting:** HA's bestaande `InvoiceSyncController` is een *push-ontvanger* (Herdenkingsportaal), geen puller. De pull is nieuw, maar hergebruikt `Invoice::createFromHavunClub()` + `TransactionMatchingService`. Pull-config: `HAVUNCLUB_BASE_URL` + `HAVUNCLUB_API_TOKEN`.
- **Betaling-payload die HA verwacht** (`/api/v1/betalingen`): `factuur_external_reference`, `status`, `betaald_op?`, `methode?`.

## Open punten (HavunClub-zijde / terugkoppelen)
- [ ] HavunClub: bevestig base-URL JT (`judotoernooi.nl/api` vs `api.`-subdomein).
- [ ] HavunClub: `geslacht`-waarden die je stuurt (JT normaliseert m/man/male resp. v/f/vrouw → M/V).
- [ ] HavunClub: betaling-payload gelijktrekken met bovenstaande veldnamen.
- [ ] HavunClub: `havunadmin_api_key`-pushveld verwijderen (richting = pull bevestigd).
- [ ] Beide PR's reviewen + mergen, daarna deploy + migraties (JT: 2 migraties).
