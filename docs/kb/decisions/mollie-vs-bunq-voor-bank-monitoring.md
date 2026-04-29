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

## Bevestigd 2026-04-29 — Bizcuit-route onderzocht

Mollie Business Account toont integraties (Excel, SnelStart, ...) via
**Bizcuit** (PSD2 AIS-broker, Bizcuit B.V. KvK 68122853). Onderzocht of dit
een gratis achterdeur naar real-time multi-bank webhooks is.

**Bevindingen:**
- Bizcuit heeft een **echte developer-API + sandbox** (developer.bizcuit.nl).
- Webhook-functionaliteit voor real-time transactie-events: **niet
  expliciet gedocumenteerd** — bevestiging vereist `partnersupport@bizcuit.nl`.
- Bizcuit-voorwaarden (gelezen 2026-04-29) tonen 5 structurele risico's
  voor app-kritieke afhankelijkheid:
  - Art. 17.1 — kettingafhankelijkheid: onze toegang stopt automatisch
    als Mollie's contract met Bizcuit eindigt.
  - Art. 7.1 + 7.5 — maandelijkse vergoeding kan eenzijdig worden verhoogd.
  - Art. 11.4 — "fair use": bij veel API-calls voor app-logica kan Bizcuit
    extra factureren.
  - Art. 4.4 + 23.1 — Bizcuit mag voorwaarden + dienst eenzijdig wijzigen.
  - Art. 14.3-14.4 — Bizcuit kan toegang opschorten "naar eigen inzicht".

**Conclusie:** voor app-kritieke flows (Studieplanner premium-toggle)
**niet** via Bizcuit. Te veel schakels (Mollie→Bizcuit→ons) en te veel
voorwaarden die de andere partij eenzijdig kan wijzigen. Bunq direct blijft
de juiste keuze.

**Wel nuttig voor:** boekhouding-automatisering (Mollie→Bizcuit→SnelStart
of Moneybird). Geen eigen Laravel-integratie nodig — kant-en-klare sync.
Aparte beslissing, niet vervangend voor Bunq-monitoring.
