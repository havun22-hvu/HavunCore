---
title: Mollie vs. Bunq voor zakelijke bank-monitoring
type: decision
scope: alle-projecten
last_check: 2026-04-25
status: resolved
note: bevat geen provider-specifieke prijs/feature-details (verouderen snel) — alleen rol-architectuur
---

# ADR: Bunq (native API) i.p.v. Mollie voor bank-afschrift-monitoring

> **Context:** vraag 2026-04-18 — kan Mollie ook webhooks afvuren bij
> inkomende betalingen op de zakelijke rekening (zoals Studieplanner nu
> met Bunq doet), zonder de ~€0,32/iDEAL-fee per transactie?
>
> **Beslissing:** nee — Mollie is hiervoor technisch ongeschikt. Bunq-native
> API blijft de juiste keuze voor bank-monitoring. Mollie alleen erbij als
> er *hosted-checkout* of *iDEAL-payment-flow* nodig is.

## Kern-verschil

| Dienst | Rol | Wat het doet |
|--------|-----|--------------|
| **Bunq** | **Bank** | Bezit de rekening zelf; biedt native API + webhooks op elke incoming transaction |
| **Mollie** | **PSP** (Payment Service Provider) | Staat **tussen** koper en ontvangst-rekening; verwerkt betalingen, int de fee, stort door |

## Waarom Mollie hier niet past

1. **Mollie is geen bank** — zij kunnen jouw **eigen** zakelijke rekening
   (Bunq/ING/Rabobank/etc.) niet monitoren. Ze zien alleen transacties die
   *door hun platform* zijn verwerkt.
2. **Geen PSD2 AIS (Account Information Service)** — Mollie's publieke docs
   (2026-04-18) bieden geen AIS-endpoints. Alleen payment-processing
   (`/payments`, `/subscriptions`, `/refunds`, `/orders`).
3. **Kostenstructuur** — Mollie rekent **per transactie** (geen gratis tier
   voor AIS-monitoring). Dat is inherent: jij gebruikt ze voor verwerking, niet voor monitoring.
4. **Geen toegevoegde waarde** voor het monitoring-use-case — Bunq doet dit
   gratis als onderdeel van je Bunq Business-abonnement.

## Wanneer Mollie *wel* nuttig is (complementair aan Bunq)

- Hosted iDEAL/Creditcard checkout (bv. Herdenkingsportaal memorial-payments)
- Terugkerende abonnementen (Mollie Subscriptions → HavunClub, …)
- Klant betaalt in vreemde valuta (Mollie handelt currency af)

→ **Architectuur:** Mollie vóór (klant → Mollie → jouw rekening), Bunq
**erna** (monitoring + boekhoud-automatisering). Elk voor hun eigen rol.

## Alternatieven voor bank-monitoring als je Bunq zou verlaten

| Optie | Kosten | Oordeel |
|-------|--------|---------|
| **Bunq native API** (huidig) | gratis bij Business-abo | ✅ beste keuze |
| **TrueLayer / Tink / Plaid** (PSD2 AIS-broker) | €20-50+/mnd + per-call | duurder dan Bunq, zinvol bij multi-bank |
| **Knab / Revolut Business API** | wisselend | beperkt; Knab heeft geen echte dev-API |
| **Mollie** | n.v.t. — biedt dit niet | ❌ |

## Referentie-bronnen

- Mollie docs getting-started (2026-04-18): https://docs.mollie.com/docs/getting-started
- Mollie pricing NL (2026-04-18): https://www.mollie.com/nl/pricing
- Bunq Business API: zie `patterns/bunq-webhook-integration.md` (tbd)

## Praktische implementatie per project

- **Studieplanner-api** (huidig): Bunq-monitoring → Laravel webhook → boeking.
  Geen wijziging nodig.
- **HavunAdmin**: Bunq-sync voor transactie-koppeling (`reconciliation`), Mollie
  niet in gebruik voor monitoring.
- **Herdenkingsportaal**: Mollie voor memorial-checkout (klant → Mollie → Bunq),
  Bunq-webhook voor reconciliatie erna.
- **Infosyst / SafeHavun / HavunVet**: geen directe Mollie/Bunq-integratie nodig.

## Hergebruik

Als deze vraag terugkomt: **eerst deze ADR verwijzen**, pas daarna opnieuw
beoordelen als Mollie nieuwe features release (check update-datum).

> **Bewust geen prijs/feature-details opgeslagen** — die veranderen continu
> bij Mollie/Bunq productvernieuwingen en zouden de ADR onbetrouwbaar maken.
> Bij her-evaluatie altijd live op leveranciers-docs checken, niet op cached
> info hier.

## Bevestigd 2026-04-25
Hernieuwde live check Mollie-producten (Bank Statements, Business Account,
Bank Transfer payment method) bevestigde de oorspronkelijke beslissing:
**Bunq blijft** voor low-price-realtime-app-flows zoals Studieplanner €1.
