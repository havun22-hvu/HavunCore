---
title: Legal compliance — wat dekken we, wat ontbreekt
type: decision
scope: havuncore
status: living-doc
date: 2026-05-01
applies_to: [herdenkingsportaal]
last_check: 2026-05-01
---

# Legal compliance — checklist (NL/EU/internationaal)

> **Niet juridisch advies.** Dit is een technisch-niveau overzicht voor
> ontwikkel-werk. Finale tekst van privacy/voorwaarden komt via Termly +
> eenmalige NL-jurist-review.
> **Single source of truth voor "voldoen we?"-vraag.**

## Conclusie vooraf

EU- en NL-regels werken **samen, niet tegen elkaar**. AVG = GDPR (één EU-verordening, NL-naam vs internationale naam). NL kan strenger zijn dan EU (bijv. UAVG-uitwerkingen, hogere AP-boetes), nooit lager. Voor HP zijn er een paar specifieke aandachtspunten op het snijvlak — vooral rond persoonsgegevens van overledenen en blockchain-permanence.

## Status per regelgeving

| Wet/regel | Wat | Voor HP | Status |
|-----------|-----|---------|--------|
| **AVG / GDPR** | EU-verordening, direct werkend | Hoofdwet voor privacy | ✅ Privacy-statement live, AVG-rechten geïmplementeerd |
| **UAVG** (NL) | Uitwerking AVG voor NL | UAVG art. 41: nabestaanden-rechten over overledenen-data | 🟡 Statement noemt het, expliciete clausule open |
| **Telecommunicatiewet** (NL) | Implementatie EU e-Privacy Directive — cookies | Cookie-consent-banner | ✅ Banner live, alleen functioneel zonder consent |
| **DSA** (EU, sinds 2024) | Digital Services Act — online platforms | HP is "online platform" | 🟡 Klachten-flow + transparantie aanwezig; jaarrapport-verplichting alleen >50 users |
| **AI Act** (EU, 2024-2026) | AI-transparantie | Claude-chatbot moet als AI gelabeld | ✅ Chatbot toont "AI-Powered" labeling |
| **PSD2** (EU) | Sterke client-authenticatie betalingen | Mollie regelt SCA | ✅ Mollie-integratie |
| **Aw art. 21** (NL) | Portretrecht | Foto's overledenen → toestemming nabestaanden | 🟡 Form-field bij upload, expliciete clausule open |
| **DMA** (EU) | Digital Markets Act, alleen big tech | N.v.t. | — |
| **NIS2** (EU) | Network/Information Security | Niet "essentiële entiteit" | — |
| **CCPA** (US California) | US-privacy | Geen aparte tekst — EU-clausule volstaat | ⚠️ Actief uitsluiten via "Dutch law applies" clausule |
| **UK GDPR** | Post-Brexit UK | 95% gelijk aan AVG | ⚠️ Geen aparte tekst; herzien bij significante UK-traffic |

## Sectorspecifieke nuance — HP rouw-context

### 1. Persoonsgegevens van overledenen (UAVG art. 41)

AVG zegt: niet van toepassing op overledenen. **UAVG voegt toe:** nabestaanden hebben rechten op wijzigen/verwijderen onder voorwaarden.

**Voor HP:**
- Eigenaar memorial = nabestaande (niet de overledene)
- Verwijdering uit DB+UI = wel doen (soft-delete + 410 Gone)
- Blockchain-versie = niet verwijderbaar — zie [BLOCKCHAIN-PERMANENCE.md](../../../Herdenkingsportaal/docs/2-FEATURES/BLOCKCHAIN-PERMANENCE.md)

### 2. Blockchain-permanence + recht op vergetelheid (AVG art. 17)

**Probleem:** Compleet pakket bevat blockchain-archivering via Arweave. Onomkeerbaar.

**Mitigatie + verdediging:**
- AVG art. 17 lid 3 kent uitzonderingen (archivering algemeen belang, rechtsvordering)
- Informed consent is leidend — user krijgt expliciete waarschuwing + bevestigt actief
- Compleet is opt-in premium pakket (Basis/Standaard gebruiken geen blockchain)
- Non-discoverability — blockchain-content niet via Google indexeerbaar onder naam

**Vereiste UI-waarschuwingen (alle 6 live):** zie BLOCKCHAIN-PERMANENCE.md sectie "UI-waarschuwingen — checklist".

### 3. Privacy-keuze per memorial (eigenaar-verantwoordelijkheid)

Eigenaar kiest expliciet `public`/`link_only`/`private` per memorial. Niet HP's verantwoordelijkheid om die keuze te overrulen.

### 4. Datalek-meldplicht (AVG art. 33, 72u)

⬜ **Open:** runbook ontbreekt formeel. Maken: `D:/GitHub/Herdenkingsportaal/docs/4-DEPLOYMENT/INCIDENT-RESPONSE-DATALEK.md` met:
- Detection-stappen
- 72u timeline + AP-meldingsformulier (autoriteitpersoonsgegevens.nl)
- Communicatie naar betrokkenen (>250 personen + hoog risico)
- Postmortem-template

### 5. Verwijzingsregister (AVG art. 30)

Voor MKB <250 medewerkers alleen verplicht bij hoog-risico verwerking. HP-content (rouw, foto's, persoonsgegevens) is **wel** gevoelig → verstandig om register aan te maken.

⬜ **Open:** `D:/GitHub/Herdenkingsportaal/docs/4-DEPLOYMENT/VERWERKINGSREGISTER.md` met:
- Lijst persoonsgegevens-verwerkingen (registratie, betalingen, mail, blockchain, AI-chatbot, ad-targeting via Pulse)
- Doel + rechtsgrond per verwerking
- Bewaartermijn per categorie
- Ontvangers (Brevo, Mollie, Claude/Anthropic, Cloudflare, Arweave, Umami)

### 6. Internationale data-overdracht

| Verwerker | Locatie | Rechtsgrond |
|-----------|---------|-------------|
| Mollie | EU (Amsterdam) | EU-intern, geen issue |
| Brevo | EU (Frankrijk) | EU-intern |
| Anthropic Claude API | US | SCC (Standard Contractual Clauses) — Anthropic heeft DPA |
| Cloudflare | US (HQ) maar EU-region beschikbaar | SCC + EU-data-residency-toggle |
| Arweave | Globally distributed | Geen klassieke "verwerker" — open netwerk; vermelden in privacy-statement als specifieke sub-processor |
| Umami | Self-hosted (HP-server) | EU-intern, geen overdracht |

⬜ **Open:** privacy-statement moet **expliciet** alle sub-processors noemen + landen + rechtsgrond. Termly genereert dit per default mits ingevoerd.

## Compliance-tooling-stack

| Tool | Doel | Status | Kosten |
|------|------|--------|--------|
| Termly of iubenda | Privacy/voorwaarden/cookies in alle talen | ⬜ Niet ingericht | ~€15/maand |
| Mollie | Betalingen + EU-BTW + sancties-screening | ✅ Live | per-transactie |
| Cookie-consent-banner | Tw-conform (NL) | ✅ Live, locale-aware | — |
| Cloudflare cf-ipcountry | Geo-detectie voor land-default | ⬜ Optioneel, fase 2 | gratis |
| Eenmalige jurist-review | NL/EU-specialist | ⬜ Plannen | €500-1000 |
| **Total jaar 1:** ~€700 | | | |
| **Total jaar 2+:** ~€180/jaar | | | |

## Action-items (geprioriteerd)

### Direct relevant (vóór `.com`-launch)
1. ⬜ Termly-abonnement + master-template-NL invullen
2. ⬜ NL-jurist eenmalig laten reviewen (€500-1000)
3. ⬜ Privacy-statement uitbreiden met:
   - Blockchain-permanence clausule (zie BLOCKCHAIN-PERMANENCE.md)
   - UAVG-overledenen clausule (NL-jurist toetst)
   - Sub-processors lijst (Anthropic, Cloudflare, Arweave, Umami)
   - Internationale overdracht (SCC vermelden)
4. ⬜ Datalek-runbook (`docs/4-DEPLOYMENT/INCIDENT-RESPONSE-DATALEK.md`)
5. ⬜ Verwerkingsregister (`docs/4-DEPLOYMENT/VERWERKINGSREGISTER.md`)

### Bij internationale uitbreiding
6. ⬜ Termly-vertaling activeren voor target-talen
7. ⬜ "Dutch version prevails"-clausule onderaan elke vertaling
8. ⬜ Cookie-banner-content per locale (al gemigreerd via `__()` — alleen content invullen)
9. ⬜ Sub-processor-lijst herzien als nieuwe partners toegevoegd worden

### Doorlopend
10. ⬜ AP-uitspraken-monitor (CCV-jurisprudentie heeft impact op blockchain-archivering kunnen krijgen)
11. ⬜ Bij elk nieuwe verwerker: register bijwerken + privacy-statement update

## NIET onze verantwoordelijkheid (afbakening)

- Sales-tax per Amerikaanse staat — Mollie regelt EU-BTW; geen US-fiscale-verplichting want geen actieve marketing daar
- CCPA-banner — uitgesloten via "Dutch law applies"-clausule
- UK-GDPR aparte tekst — 95% gelijk aan AVG, AP/ICO werken samen
- Memorial-content moderatie buiten DSA-vereisten — eigenaar is verantwoordelijk voor wat hij plaatst, behalve voor evidente illegaal materiaal (HP-flag-systeem dekt dat)

## Trigger voor herziening

- Substantiële klacht uit niet-EU jurisdictie → jurist consulteren
- AP-onderzoek of jurisprudentie over blockchain-archivering → BLOCKCHAIN-PERMANENCE.md heroverwegen
- Omzet >€100k/jaar uit specifiek niet-EU land → aparte compliance overwegen
- DSA: zodra HP >50 unieke gebruikers per maand → jaarrapport-verplichting checken

## Referenties

- ADR i18n-strategie: [`internationalization-strategy.md`](internationalization-strategy.md)
- HP feature-doc blockchain: [`Herdenkingsportaal/docs/2-FEATURES/BLOCKCHAIN-PERMANENCE.md`](../../../Herdenkingsportaal/docs/2-FEATURES/BLOCKCHAIN-PERMANENCE.md)
- AP (Autoriteit Persoonsgegevens): https://autoriteitpersoonsgegevens.nl
- AVG-tekst: https://gdpr-info.eu/
- UAVG-tekst: https://wetten.overheid.nl/jci1.3:c:BWBR0040940
- DSA-tekst: https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:32022R2065
