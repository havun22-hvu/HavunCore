---
title: Internationaliserings-strategie — EU-based, wereldwijd toegankelijk
type: decision
scope: havuncore
status: vastgelegd
date: 2026-05-01
applies_to: [herdenkingsportaal]
last_check: 2026-05-01
---

# ADR — Internationaliserings-strategie

## Beslissing

**EU-based site, wereldwijd toegankelijk voor alle nabestaanden, ongeacht herkomst of nationaliteit.**

Eén set voorwaarden onder NL/EU-recht. Geen aparte compliance per niet-EU land. Iedereen mag registreren. Taalkeuzes op basis van doelgroep-vraag, niet op basis van geopolitiek.

## Context

Herdenkingsportaal wil meertalig worden. Vraag was: hoe omgaan met internationale gebruikers en regelgeving? Verschillende opties overwogen:
- Optie A: EU+UK only (afgebakend)
- Optie B: EU-based, wereldwijd toegankelijk (gekozen)
- Optie C: Per-land compliance (te complex, te duur)

## Waarom optie B

- **Herdenken is universeel** — een Russische, Oekraïense, Amerikaanse of Australische nabestaande heeft dezelfde menselijke behoefte als een Nederlandse
- **Politiek staat los van piëteit** — site dient nabestaanden, niet geopolitieke afwegingen
- **Eén juridisch kader** — NL/EU-recht voor iedereen, geen complexe per-land aanpassingen
- **Schaalbaar** — Termly + Mollie + Cloudflare dekken praktisch alles
- **Werkbaar voor MKB** — geen jurist-team nodig

## Wat dit betekent

### Wel doen
- Site onder NL/EU-recht, AVG-compliant
- Iedereen mag registreren ongeacht IP-locatie
- Mollie regelt sancties-screening voor individuele personen automatisch
- Talen toevoegen op basis van doelgroep-analytics (Umami)
- Eén voorwaarden-document, vertaald per taal als service (NL bindend)
- Verplichte clausule onderaan elke vertaalde versie:
  > *This service is operated from the Netherlands under European Union law. Dutch law and Dutch courts apply to all use of this service. By using this service, you accept these terms. Local consumer protection laws of your country of residence remain intact where mandatory by EU law. **Translations are provided for convenience; in case of dispute, the Dutch version prevails.***
- Eén NL-jurist reviewt master-template (eenmalig €500-€1000); Termly genereert vertalingen (geen jurist-per-taal nodig)

### Niet doen
- Geen aparte CCPA-banner voor Californiërs
- Geen aparte UK GDPR-voorwaarden (95% gelijk aan AVG)
- Geen geo-blocking op landen-niveau (alleen Mollie's automatische individuele sancties-blokkering)
- Geen sales-tax-implementatie per Amerikaanse staat
- Geen taal uitsluiten op basis van politieke gevoeligheid
- Geen actieve marketing buiten EU (om "actief mikken"-criterium niet te triggeren)
- Geen 1:1 LLM-vertaling van juridische pagina's — gebruik Termly templates met jurist-gereviewde NL-master
- Geen vlaggen voor talen (politiek-gevoelig). Taalnamen in eigen schrijfwijze: `Русский`, `Українська`, `Polski`. Voor NL/EN-only is de huidige vlag-dropdown acceptabel — bij toevoegen RU/UK omschakelen naar tekst-only.
- Geen `.com/nl/...` als duplicate van `.nl/...` — nginx 301-redirect verplicht
- Geen auto-translate van memorial-content (piëteit-risico)

## Talen — basis + optioneel

**Basisset:** Nederlands, Engels, Duits, Frans

**Optioneel op vraag:** Russisch, Oekraïens, Pools, Spaans, Arabisch, Turks, etc.

**Beslissingscriterium voor toevoeging:**
- **Drempel A (analytics):** Umami toont >5% niet-NL traffic in een rollend kwartaal, of >500 unieke bezoekers/maand uit doelgroep-land
- **Drempel B (klant):** specifieke aanvraag van klant/partner met concrete use-case
- **Drempel C (omzet, fase 2):** omzet >€10k/jaar uit specifiek niet-NL land → eigen domein verantwoorden

**Geen taal uitgesloten op basis van politieke afwegingen** — alleen op basis van praktische haalbaarheid (vertaling-kwaliteit, doelgroep-grootte).

**Aanbeveling EN-variant:** Brits Engels — formeler, past bij EU-publiek en rouw-context. Amerikaanse families ervaren dat niet als barrier.

## Compliance-tooling

| Tool | Doel | Kosten |
|------|------|--------|
| Termly of iubenda | Voorwaarden + privacy + cookies | ~€15/maand |
| Mollie | Betalingen + EU-BTW + sancties-screening | per-transactie |
| Cloudflare | Geo-detectie via `cf-ipcountry` (optioneel) | gratis |
| Eenmalige jurist-review | NL/EU-specialist | €500-€1000 |

**Total kosten jaar 1:** ~€700, daarna ~€180/jaar.

## Domeinstrategie

### Fase 1 (start)
- `herdenkingsportaal.nl` = NL-only, geen taalswitcher
- `[engels-merk].com` = meertalig met URL-prefix (`/en/`, `/de/`, `/fr/`, ...)
- `.com/nl/...` bestaat **niet** — nginx 301 → `herdenkingsportaal.nl/...` (voorkomt duplicate content + Google-penalty)

### Fase 2 (bij bewezen volume per taal)
- Eigen domein per taal: `.de`, `.fr`, `.co.uk` etc.
- 301-redirect vanuit `.com/{lang}/` zodat SEO-juice meegaat

### Naam `.com`-domein — open beslissing
- **`inmemoryof.com`** (aanbevolen) — sterker consumer-merk, internationaal direct herkenbaar
- `remembranceportal.com` — formele variant, lijkt op NL-naam (maar brand-consistency is fictief)
- `herdenkingsportaal.com` — alleen verdedigbaar als noodgreep

**Vóór aanschaf:** trademark-check via WIPO Global Brand Database + USPTO TESS.

### CDN cache
URL-prefix per taal is automatisch cache-key-uniek. **Geen `Vary: Accept-Language` header nodig** — die zou per browser-locale een cache-entry creëren (cache-miss-storm).

## Trigger voor herziening

Deze beslissing herzien wanneer:
- Omzet >€100k/jaar uit specifiek niet-EU land — dan loont aparte compliance
- Substantiële klacht uit niet-EU jurisdictie — dan jurist consulteren
- Expliciete uitbreiding naar Amerikaanse markt (CCPA wordt dan onontkoombaar)

**Tot dan:** EU-based, wereldwijd toegankelijk, zonder aanpassingen per land.

## Gerelateerde docs

- HP feature-plan: `Herdenkingsportaal/docs/2-FEATURES/MULTILANGUAGE-INTERNATIONAL.md`
- Database-impact: zie feature-plan sectie "Database-impact"
- Implementatie-volgorde: zie feature-plan sectie "Implementatie-volgorde"

## Status

**Vastgelegd:** 2026-05-01
**Implementatie:** nog niet gestart, plan staat klaar
**Volgende stap:** definitieve `.com`-naam kiezen, dan pas implementatie inplannen
