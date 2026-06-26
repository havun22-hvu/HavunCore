---
title: "Blueprint: HavunAdmin-kant HavunClub-koppeling"
type: plan
scope: havunadmin
last_check: 2026-06-27
status: klaar om te droppen in HavunAdmin/.claude/blueprint.md
---

# Blueprint — HavunAdmin pull-job voor HavunClub-facturen

> Voorbereid in HavunCore (orchestrator). **Bouwen gebeurt in een HavunAdmin-sessie:**
> kopieer naar `HavunAdmin/.claude/blueprint.md`, open daar een sessie, `/mpc` + "ga maar".
> Contract: `HavunCore/docs/kb/contracts/havunclub-koppelingen.md`.

## Doel
HavunAdmin **trekt** facturen + betalingen op uit HavunClub (HavunClub = master financiële data)
en boekt ze in als `Invoice` + `InvoiceItem` met `source = "havunclub"`. Sluit aan op de
bestaande sync-laag (`InvoiceSyncController`, `sync:mollie`/`sync:bunq`).

## Auth (HA = aanroeper)
HavunClub levert per club een Sanctum-token (abilities `facturen:read`, `betalingen:read`).
HA bewaart dat token per gekoppelde club (in HA's tenant-config). Endpoints:
- `GET {havunclub}/api/v1/facturen?sinds=<ISO>`
- `GET {havunclub}/api/v1/betalingen?sinds=<ISO>`
Bearer-auth; JSON-401/403; `throttle:api`. `?sinds=` = incrementeel (laatste sync-tijdstip).

## Implementatie — spiegel `sync:mollie` / `sync:bunq`
1. **Command** `sync:havunclub {--tenant=} {--sinds=}` (signature zoals `SyncMolliePayments`).
   Per gekoppelde club: `Http::withToken($clubToken)->get($base.'/api/v1/facturen', ['sinds' => $laatste])`.
2. **Service** `HavunClubSyncService` met `MollieService`-achtige opzet (DI in command).
   Mapt payload → models, idempotent.
3. **Mapping factuur → `Invoice`** (velden bestaan al in `invoices.fillable`):
   | payload | Invoice |
   |---|---|
   | `external_reference` | `external_reference` (+ `source='havunclub'`) |
   | `invoice_number`/`invoice_date`/`due_date`/`payment_date`/`status`/`description` | idem |
   | `subtotal`/`vat_amount`/`vat_percentage`/`vat_type`/`total` | idem |
   | `customer{naam,email,iban}` | `customer_snapshot` (+ match/aanmaak `customer_id`) |
   | `regels[]` | `InvoiceItem` per regel (`description,quantity,unit_price,vat_percentage,subtotal,vat_amount,total`) |
4. **Idempotentie:** `Invoice::updateOrCreate(['source'=>'havunclub','external_reference'=>$ref], [...])`.
   InvoiceItems bij update: vervang-set (delete + herinsert) of match op `sort_order`.
5. **Grootboek/kostenplaats:** HA wijst zelf toe via bestaande import-filters (`SetupImportFilters` /
   `category_id` → `LedgerAccount`). HavunClub levert dit NIET. Default-categorie "contributie/HavunClub"
   instelbaar per tenant.
6. **Betalingen:** zelfde patroon → koppel aan factuur via `external_reference`, vul `payment_date`/`status`.
7. **Scheduler** (`routes/console.php`): `Schedule::command('sync:havunclub')->everyFifteenMinutes()`
   naast `sync:bunq` (alleen voor tenants met een HavunClub-token).

## Beslissing terug te koppelen in contract
- **Regel- vs header-BTW:** HA's `invoice_items` ondersteunt per-regel → **gebruik `regels[]`**.
  Bevestig in `havunclub-koppelingen.md` dat HavunClub regels meelevert (anders 1 regel = header).

## Kwaliteit (Havun-normen — verplicht)
- **Form/DTO-validatie** op de binnenkomende payload (geen blind vertrouwen op externe data).
- **Circuit breaker / retry** op de HTTP-call (HavunClub down ≠ HA-job crasht); log + alert.
- **Idempotentie-tests:** 2× sync zelfde factuur → 1 Invoice, regels niet gedupliceerd.
- **Tests:** mapping-test (payload→Invoice+Items, bedragen kloppen), dedup, `?sinds=` incrementeel,
  betaling-koppelt-aan-factuur, auth-fout-afhandeling. Coverage >80%.
- **Audit log** per sync-run (aantal nieuw/bijgewerkt, tijdstip, tenant).

## Werkvolgorde
1. `HavunClubSyncService` + payload-DTO. 2. `sync:havunclub` command. 3. Invoice/Item-mapping + dedup.
4. Betalingen. 5. Scheduler + per-tenant token-config. 6. Tests. 7. Contract-terugkoppeling (regel-BTW).
