# Runbook: Mutation Testing met Infection PHP

> **Doel:** Verifiëren dat de tests werkelijk regressies vangen — niet alleen "code-statements raken".
> **Wanneer toepassen:** Per kwartaal automatisch via GitHub Actions, en handmatig na grote payment-wijzigingen.
> **Vereisten:** Infection PHP geïnstalleerd als dev-dep in het project (zie installatie hieronder).
> **Klaar-criteria:** MSI ≥ 75% op payment-pijler, met rapport in `storage/logs/infection-summary.log` en commit-comment bij failure.

## Wat is mutation testing?

Een tool die opzettelijk **kleine fouten** in jouw productiecode introduceert (bijvoorbeeld `>` vervangen door `>=`, of een `return true` weghalen) en dan de testsuite draait. Als de tests **wel** falen door zo'n mutatie → goed: jouw test heeft de bug gevangen. Als de tests **niet** falen → die mutatie "overleefde" en jouw test heeft een gat.

**MSI (Mutation Score Indicator)** = % gedode mutaties / totaal aantal mutaties. Hoe hoger, hoe beter de test-effectiviteit.

| MSI | Betekenis |
|-----|-----------|
| 0-50% | Tests dekken weinig daadwerkelijk gedrag — coverage liegt |
| 50-75% | Acceptabel — gemiddelde projecten |
| **75-90%** | **Norm voor publieke betalingen (HP)** |
| 90%+ | Mission-critical (lucht, medisch) |

## Scope (HP)

Mutation testing op de hele app duurt uren. We meten alleen de **payment-pijler** — daar zit het echte risico:

- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/PaymentWebhookController.php`
- `app/Http/Controllers/PaymentCryptoController.php`
- `app/Http/Controllers/PaymentEPCController.php`
- `app/Services/RealMollieService.php` + `MockMollieService` + `PaymentServiceFactory`
- `app/Services/InvoiceService.php` + `BunqApiService` + `TikkiePaymentService` + `XrpPaymentService`
- `app/Models/PaymentTransaction.php` + `Invoice.php`
- `app/Jobs/CheckBunqPayments.php` + `CheckCryptoPayments.php` + `SyncInvoiceJob.php`

Configuratie staat in `infection.json5` (HP-repo root).

## Installatie (eenmalig — vereist eigenaar-akkoord)

Infection PHP is nog niet geïnstalleerd. Vereist een **expliciete `composer require`** door de eigenaar:

```bash
cd /d/GitHub/Herdenkingsportaal
composer require --dev infection/infection
```

Dit voegt Infection toe als dev-dep (alleen voor lokaal + CI, niet productie). Daarna is `vendor/bin/infection` beschikbaar.

## Uitvoeren

### Lokaal (na installatie)

```bash
cd /d/GitHub/Herdenkingsportaal
mkdir -p storage/logs storage/framework/cache/infection
vendor/bin/infection --threads=4 --min-msi=75 --min-covered-msi=80
```

Duur: 10-30 minuten afhankelijk van CPU. Output:
- `storage/logs/infection-summary.log` — eindcijfer
- `storage/logs/infection-per-mutator.md` — welke mutaties overleefden

### Automatisch via GitHub Actions

Workflow: `.github/workflows/mutation-test.yml`

Triggers:
- **Handmatig:** GitHub Actions → "Mutation Testing (quarterly)" → Run workflow
- **Automatisch:** 1e van januari, april, juli, oktober — 03:00 UTC

Bij failure (MSI < 75%): rapport-artifact wordt 90 dagen bewaard + GitHub annotation op de commit.

## Resultaten interpreteren

Open `storage/logs/infection-per-mutator.md` voor het overzicht per mutatortype. De relevantste mutators voor payment-code:

| Mutator | Wat het doet | Waarom kritiek voor betalingen |
|---------|--------------|--------------------------------|
| **PublicVisibility** | Verbergt publieke methods | Vangt: vergeten autorisatie-check op betaalmethod |
| **Decrement / Increment** | `$x++` → `$x--` | Vangt: foute bedragberekening, off-by-one bij retries |
| **TrueValue / FalseValue** | `return true` → `return false` | Vangt: payment-status verwisseld |
| **PregMatchMatches** | Regex aanpassen | Vangt: zwakke validatie van Mollie-payment-IDs |
| **MethodCallRemoval** | `Mail::send()` weghalen | Vangt: vergeten notificatie bij betaling |

Voor elke "overlevende" mutatie: **schrijf een test die hem zou doden** of leg uit waarom de mutatie acceptabel is (bijv. defensive code).

## Eerste-keer drempel

De eerste run zal waarschijnlijk **MSI 60-70%** geven (gat tussen statement-coverage en gedrag-coverage). Dat is geen falen — het is precies wat we willen meten. Plan: per kwartaal MSI met 5%-punt omhoog brengen tot 80%+.

## Wat NIET met mutation testing

- Tests fixen door assertions te verzwakken (anti-pattern, zie `test-repair-anti-pattern.md`)
- Mutaties "negeren" door ze in `infection.json5` uit te zetten — alleen toegestaan met expliciete reden in commit-message
- Mutation testing op niet-payment-code uitbreiden zonder overleg — duur loopt snel op

## Cross-references

- `infection.json5` (HP-repo) — scope-configuratie
- `.github/workflows/mutation-test.yml` (HP-repo) — CI-setup
- `docs/kb/runbooks/test-repair-anti-pattern.md` — wat te doen bij overlevende mutatie
- `docs/audit/verbeterplan-q2-2026.md` — VP-16 (oorsprong)
- `docs/audit/werkwijze-beoordeling-derden.md` — sectie 6 (test-strategie)
