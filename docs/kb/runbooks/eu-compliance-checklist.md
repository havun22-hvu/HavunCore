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
- Telefoonnummer
- Bereikbaarheidsuren

**Status per project:**

| Gegeven | Herdenkingsportaal | JudoToernooi | Studieplanner |
|---------|:--:|:--:|:--:|
| Bedrijfsnaam | ✅ | ✅ | ❌ |
| KVK-nummer | ✅ (config) | ✅ (98516000) | ❌ |
| BTW-status | ✅ (KOR) | ✅ (KOR) | ❌ |
| Vestigingsadres | ❌ | ❌ | ❌ |
| E-mailadres | ✅ | ✅ | ❌ |
| Telefoonnummer | ❌ | ❌ | ❌ |
| Bereikbaarheidsuren | ❌ | ❌ | ❌ |

> **Actie:** Adres, telefoon en bereikbaarheidsuren toevoegen aan footer van alle projecten.
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

**Status:**

| Project | Herroepingsinfo | Formul ier | Uitzondering vermeld |
|---------|:--:|:--:|:--:|
| Herdenkingsportaal | ✅ (terms) | ❌ | ✅ (digitale content) |
| JudoToernooi | ⚠️ (impliciet) | ❌ | ❌ |
| Studieplanner | ❌ | ❌ | ❌ |

---

### 3. Herroepingsknop (NIEUW — verplicht per 19 juni 2026)

Een duidelijke knop/link waarmee klanten hun aankoop kunnen annuleren. Na klikken:
1. Klant bevestigt de annulering
2. Systeem stuurt bevestigingsmail
3. Retourtermijn start

**Status:** ❌ Alle projecten — nog niet gebouwd

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

## Actielijst per Project

### Herdenkingsportaal
- [ ] Vestigingsadres toevoegen aan footer
- [ ] Telefoonnummer toevoegen aan footer (of "geen telefonisch contact")
- [ ] Bereikbaarheidsuren toevoegen
- [ ] Herroepingsformulier (EU modelformulier) toevoegen
- [ ] Herroepingsknop bouwen (deadline: 19 juni 2026)
- [ ] JudoToernooi klachtenprocedure uitbreiden (nu alleen email)

### JudoToernooi
- [ ] Vestigingsadres toevoegen aan footer
- [ ] Telefoonnummer toevoegen aan footer
- [ ] Bereikbaarheidsuren toevoegen
- [ ] Herroepingsrecht expliciet in terms uitwerken
- [ ] Herroepingsformulier toevoegen
- [ ] Herroepingsknop bouwen (deadline: 19 juni 2026)
- [ ] Klachtenprocedure uitbreiden

### Studieplanner
- [ ] Privacy pagina aanmaken
- [ ] Algemene Voorwaarden aanmaken
- [ ] Cookiebeleid aanmaken
- [ ] Footer bijwerken met alle verplichte gegevens
- [ ] Herroepingsrecht informatie toevoegen
- [ ] Herroepingsformulier toevoegen
- [ ] Herroepingsknop bouwen (deadline: 19 juni 2026)
- [ ] Klachtenprocedure toevoegen

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
- Tel: zie credentials.md of "Geen telefonisch contact beschikbaar"

---

## Deadline

**19 juni 2026** — Herroepingsknop verplicht op alle webshops/dienstensites.

---

*Bijgewerkt: 19 maart 2026*
