---
title: EU Compliance Checklist — Online Diensten
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# EU Compliance Checklist — Online Diensten

> Wettelijke verplichtingen voor Havun projecten met online diensten/betalingen.
> Bron: KVK + ACM regels, Europese Consumentenrichtlijn (geldig vanaf 19 juni 2026).
> Bijgewerkt: 19 maart 2026

## Welke projecten vallen hieronder?

| Project | Type | Betalingen | Prioriteit |
|---------|------|-----------|-----------|
| Herdenkingsportaal | B2C webdienst | Mollie (gepland) | Hoog |
| JudoToernooi | B2C platform | Mollie Connect + Stripe | Hoog |
| Studieplanner | B2C app + website | Mollie (freemium) | Hoog |
| SafeHavun | B2B tool | n.v.t. | Laag |
| HavunAdmin | Intern | n.v.t. | Geen |

---

## De 10 Verplichte Elementen (KVK/ACM)

### 1. Bedrijfsgegevens in footer (VERPLICHT)

Elke pagina moet bevatten:
- Bedrijfsnaam (ingeschreven bij KVK)
- KVK-nummer
- BTW-nummer (of BTW-vrijstelling vermelden)
- Vestigingsadres
- E-mailadres
- Telefoonnummer OF vermelding dat je alleen per e-mail bereikbaar bent
- Bereikbaarheidsuren

> **Havun keuze:** Geen telefoonnummer — alleen e-mail. Dit is toegestaan mits expliciet vermeld.

**Status per project:**

| Gegeven | Herdenkingsportaal | JudoToernooi | Studieplanner |
|---------|:--:|:--:|:--:|
| Bedrijfsnaam | ✅ | ✅ | ❌ |
| KVK-nummer | ✅ (config) | ✅ (98516000) | ❌ |
| BTW-status | ✅ (KOR) | ✅ (KOR) | ❌ |
| Vestigingsadres | ❌ | ❌ | ❌ |
| E-mailadres | ✅ | ✅ | ❌ |
| Bereikbaarheidsuren | ❌ | ❌ | ❌ |

> **Actie:** Adres, bereikbaarheidsuren en "alleen per e-mail bereikbaar" toevoegen aan footer van alle projecten.
> **Havun gegevens:** Zie `credentials.md` (bedrijfsgegevens sectie)

---

### 2. Herroepingsrecht (14 dagen bedenktijd)

Klanten hebben 14 dagen bedenktijd na aankoop. Uitzonderingen:
- Digitale content die direct beschikbaar is gesteld (mits klant hiermee instemt)
- Services die al volledig zijn geleverd

Verplicht op website:
- Informatie over het herroepingsrecht
- Link naar herroepingsformulier (modelformulier EU)
- Uitleg wanneer het recht NIET van toepassing is (met expliciete toestemming klant)

**Herroepingsrecht per project:**

| Project | Type dienst | Herroeping verplicht? | Aanpak |
|---------|------------|----------------------|--------|
| Herdenkingsportaal | Directe digitale toegang na betaling | Nee (mag uitgesloten worden) | ✅ Checkbox bij checkout |
| JudoToernooi | Directe inschrijving na betaling | Nee (mag uitgesloten worden) | ✅ Checkbox bij checkout |
| Studieplanner | Jaarabonnement (doorlopend) | Ja (14 dagen) | ✅ Herroepingsformulier + info in terms |

> **Regel:** Checkbox uitsluitend gebruiken bij diensten die DIRECT na betaling beschikbaar zijn. Bij doorlopende diensten (abonnement) is herroepingsrecht verplicht.

**Status checkbox implementatie:**

| Project | Checkbox aanwezig | Betaalknop geblokkeerd zonder vinkje |
|---------|:--:|:--:|
| Herdenkingsportaal | ✅ (options.blade.php) | ✅ |
| JudoToernooi | ✅ (afrekenen.blade.php) | ✅ |
| Studieplanner | n.v.t. | n.v.t. |

---

### 3. Herroepingsknop (NIEUW — verplicht per 19 juni 2026)

Een duidelijke knop/link waarmee klanten hun aankoop kunnen annuleren. Na klikken:
1. Klant bevestigt de annulering
2. Systeem stuurt bevestigingsmail
3. Retourtermijn start

> Voor HP en JT: niet nodig want klant sluit recht uit via checkbox bij checkout.
> Voor SP: wel verplicht (jaarabonnement, 14 dagen herroeping).

**Status:** ⚠️ Studieplanner — nog bouwen vóór 19 juni 2026

---

### 4. Privacybeleid (AVG/GDPR)

Moet vermelden:
- Welke gegevens worden verzameld
- Waarvoor ze worden gebruikt
- Hoe lang ze worden bewaard
- Rechten van de betrokkene (inzage, correctie, verwijdering)
- Contactgegevens verwerkingsverantwoordelijke

**Status:**

| Project | Aanwezig | Compleet |
|---------|:--:|:--:|
| Herdenkingsportaal | ✅ | ✅ |
| JudoToernooi | ✅ | ✅ |
| Studieplanner | ❌ | ❌ |

---

### 5. Algemene Voorwaarden

**Status:**

| Project | Aanwezig |
|---------|:--:|
| Herdenkingsportaal | ✅ |
| JudoToernooi | ✅ |
| Studieplanner | ❌ |

---

### 6. Cookiebeleid

**Status:**

| Project | Aanwezig |
|---------|:--:|
| Herdenkingsportaal | ✅ |
| JudoToernooi | ✅ |
| Studieplanner | ❌ |

---

### 7. Klachtenprocedure

Verplicht te vermelden hoe klanten een klacht kunnen indienen.

**Status:**

| Project | Aanwezig |
|---------|:--:|
| Herdenkingsportaal | ✅ (AP) |
| JudoToernooi | ⚠️ (alleen email) |
| Studieplanner | ❌ |

---

### 8. Reviews (alleen echte klanten)

Vermeld hoe reviews worden gecontroleerd op echtheid. Geen gekochte reviews.

**Status:** Geen van de projecten heeft reviews — n.v.t.

---

### 9. Kortingsregels

Bij kortingen: altijd de laagste prijs van de afgelopen 30 dagen tonen als "van-prijs". Geen nepkortingen.

**Status:** Geen van de projecten heeft kortingsacties — n.v.t.

---

### 10. Aftelklokjes

Alleen gebruiken als de aanbieding echt afloopt op dat tijdstip.

**Status:** Geen van de projecten heeft aftelklokjes — n.v.t.

---

## Actielijst per Project (bijgewerkt 19 maart 2026)

### Herdenkingsportaal
- [x] KVK, BTW in footer
- [x] E-mailadres in footer
- [x] Vestigingsadres in footer (Jacques Bloemhof 57, 1628 VN Hoorn)
- [x] Privacy, Voorwaarden, Cookies pagina's aanwezig
- [x] Herroepingscheckbox bij checkout (options.blade.php) — betaalknop geblokkeerd zonder vinkje
- [ ] Bereikbaarheidsuren toevoegen aan footer
- [ ] Herroepingsknop bouwen voor Studieplanner (deadline: 19 juni 2026) — HP/JT niet nodig

### JudoToernooi
- [x] KVK, BTW in footer
- [x] E-mailadres in footer
- [x] Vestigingsadres in footer (Jacques Bloemhof 57, 1628 VN Hoorn)
- [x] Privacy, Voorwaarden, Cookies pagina's aanwezig
- [x] Herroepingscheckbox bij checkout (afrekenen.blade.php) — betaalknop geblokkeerd zonder vinkje
- [ ] Bereikbaarheidsuren toevoegen aan footer
- [ ] Klachtenprocedure uitbreiden (nu alleen email, geen geschillencommissie)

### Studieplanner
- [x] Privacy pagina aangemaakt (/privacy)
- [x] Algemene Voorwaarden aangemaakt (/voorwaarden)
- [x] Cookiebeleid aangemaakt (/cookies)
- [x] Herroepingsformulier aangemaakt (/herroeping)
- [x] Footer bijgewerkt: KVK, BTW, adres, email, links naar legal pagina's
- [x] Herroepingsrecht info in terms (14 dagen, jaarabonnement)
- [ ] Bereikbaarheidsuren toevoegen aan footer
- [ ] Herroepingsknop bouwen (deadline: 19 juni 2026) — verplicht want jaarabonnement

---

## EU Modelformulier Herroeping

Het standaard herroepingsformulier (verplicht beschikbaar te stellen):

```
MODELFORMULIER VOOR HERROEPING

Aan: [Bedrijfsnaam], [Adres], [E-mail]

Ik/Wij (*) deel/delen (*) u hierbij mede dat ik/wij (*) onze overeenkomst
betreffende de verkoop van de volgende goederen/levering van de volgende
dienst (*) herroep/herroepen (*):

Besteld op (*)/Ontvangen op (*): _______________
Naam/Namen consument(en): _______________
Adres consument(en): _______________
Handtekening consument(en): _______________
Datum: _______________

(*) Doorhalen wat niet van toepassing is.
```

---

## Havun Bedrijfsgegevens (voor footer)

> Zie `credentials.md` voor actuele gegevens — NIET hier opslaan.

Velden die in footer moeten:
- Naam: Havun (of volledige KVK-naam)
- KVK: 98516000
- BTW: BTW-vrijgesteld (KOR-regeling)
- Adres: zie credentials.md
- Email: zie credentials.md
- Email: havun22@gmail.com
- Tel: niet beschikbaar — alleen e-mail

---

## Deadline

**19 juni 2026** — Herroepingsknop verplicht op alle webshops/dienstensites.

---

*Bijgewerkt: 19 maart 2026*
