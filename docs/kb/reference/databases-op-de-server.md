---
title: "Databases op 188.245.159.115 — wat is wat"
type: reference
scope: havuncore
last_check: 2026-08-01
---

# Databases op de server

**Waarom dit bestaat:** van 15 maart tot 27 juli 2026 dumpte de nachtelijke backup
`herdenkingsportaal_production` — een dood restant van 22 tabellen en 47 rijen — terwijl de app op
`herdenkingsportaal_prod` draait met 50.520 rijen. Twee namen die op elkaar lijken, en niemand die
kon nazoeken welke de echte was. Dat is het gat dat dit doc dicht.

**De regel:** de `.env` van de app is de bron van waarheid, nooit een lijst. `qv:scan --only=backup-coverage`
leest `DB_DATABASE` uit elke productie-`.env` en eist dat díé database in de backupverwachting staat.

## Overzicht (gemeten 01-08-2026)

| Database | Van | Rijen | Backup | Opmerking |
|---|---|---:|---|---|
| `havunadmin_production` | HavunAdmin prod | 10.951 | ✅ nachtelijk | |
| `havunadmin_staging` | HavunAdmin staging | 509 | ✅ nachtelijk | |
| `havuncore` | HavunCore | 270.791 | ✅ nachtelijk | |
| `herdenkingsportaal_prod` | Herdenkingsportaal prod | 50.520 | ✅ sinds 28-07 | **let op de naam:** `_prod`, niet `_production` |
| `herdenkingsportaal_staging` | Herdenkingsportaal staging | 17.669 | ✅ nachtelijk | |
| `judo_toernooi` | JudoToernooi prod | 2.291 | ✅ nachtelijk | |
| `staging_judo_toernooi` | JudoToernooi staging | 2.080 | ✅ nachtelijk | naamvolgorde wijkt af van de rest |
| `safehavun` | SafeHavun | 591.819 | ✅ nachtelijk | |
| `studieplanner` | Studieplanner-api | 91 | ✅ nachtelijk | |

## Bewust géén backup, bewust laten staan

Alle vier hebben `UPDATE_TIME NULL` — er wordt niet naar geschreven. **Niet opruimen zonder Henks
go; dit staat hier zodat een volgende sessie ze niet opnieuw "ontdekt" en weer voorstelt ze weg te
gooien.**

| Database | Rijen | Waarom hij blijft |
|---|---:|---|
| `herdenkingsportaal_production` | 47 | Dood restant, en de directe oorzaak van het backupgat. Dump veiliggesteld: `/root/backups/hp-dode-db-2026-08-01`. **Droppen zou de valstrik wegnemen — wacht op Henks go** |
| `havunvet_staging` | 80 | HavunVet is **geparkeerd** (Henk, 01-08): "niet interessant voorlopig". Repo gearchiveerd 24-07 |
| `testsite_wp` | 179 | WordPress zónder app op de server — geen `.env` verwijst ernaar. Henk: "mag voorlopig uit zicht" |
| `havunadmin_central_staging` | 4 | Hoort bij een lévende app (`havunadmin/staging/.env`), maar is leeg. Geen backup nodig zolang dat zo blijft |

## Restore-tests (01-08-2026)

Een dump die bestaat is niet hetzelfde als een dump die terugzet. Beide zijn teruggeladen in een
tijdelijke database en tabel voor tabel vergeleken met live; de testdatabases zijn daarna weer
verwijderd.

| Database | Uitkomst |
|---|---|
| `herdenkingsportaal_prod` | 52/52 tabellen, elke tabel gelijk op `page_views` en `sessions` na — die lopen door tussen dump en meting |
| `havunadmin_production` | 39/39 tabellen. **Alle veertien boekhoudtabellen exact gelijk** (`invoices`, `local_invoices` 74, `journal_entries` 24, `ledger_accounts` 61, `transactions` 61, `time_entries` 203, …). Alleen `api_syncs`, `audit_logs`, `qr_sessions` en `sessions` wijken af — allemaal lopend verkeer |

**HavunAdmin's bestanden zaten tot 01-08 in géén backup.** Het script archiveerde
`storage/invoices`, een pad dat nooit bestaan heeft, en sloeg dat *stil* over. De facturen staan in
`storage/app/public/facturen`. Sinds vandaag loopt `havunadmin_storage.tar.gz` mee (3,8 MB, 89
bestanden: uitgaande facturen, bunq-exports en `administratie-verantwoording-2024.md`) — op de
Storage Box staat hij alleen bij 01-08, daarvoor nergens. De **database** was al die tijd wél goed
(groeide netjes van 198 KB in juli naar 246 KB nu).

## Als je een database toevoegt of hernoemt

1. Zet `DB_DATABASE` in de `.env` van de app — dat is wat telt.
2. Voeg `<naam>.sql.gz` toe aan `config/havun-backup.verificatie.verwacht` in HavunCore.
3. Voeg de databasenaam toe aan `PROD_DATABASES` of `STAGING_DATABASES` in
   `/usr/local/bin/havun-backup.sh` op de server.
4. Draai `php artisan qv:scan --only=backup-coverage` — die faalt met **high** als stap 1 en 2 niet
   overeenkomen.

Sla je stap 3 over, dan meldt de check morgen dat het bestand ontbreekt. Sla je stap 2 over, dan
meldt hij dat de app-database niet gedekt is. Beide zijn de bedoeling.

## Verwant

- `plans/registry-drift-check-plan.md` — hoe het gat gevonden is en wat de checks doen
- `standards/server-hygiene.md` — nooit blind wissen op prod
