---
title: Noodplan Websites — Thiemo & Mawin
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# Noodplan Websites — Thiemo & Mawin

> **Wat is dit?** Stap-voor-stap instructies voor als papa niet bereikbaar is en er iets mis is met de websites.
> **Voor wie?** Thiemo en Mawin — geen technische kennis nodig.
> **Hoe lang duurt het?** 5-10 minuten

---

## Wat je moet weten (de basis)

- Papa heeft websites gemaakt die op een server draaien (een computer in een datacenter in Duitsland)
- Die server draait 24/7 — ook als de PC thuis uit staat
- **Claude** is een AI-assistent die de websites kan checken en problemen kan oplossen
- **Jouw rol:** Claude opstarten, de stappen volgen, en papa op de hoogte houden via WhatsApp

---

## Stap-voor-stap

### Stap 1: Open Visual Studio Code

1. Open **Visual Studio Code** (blauw icoon op het bureaublad of in het Start-menu)
2. Open het project **HavunCore**:
   - **Optie A:** Klik links in de zijbalk op het **mapjes-icoon** (twee mapjes boven elkaar) en kies **HavunCore**
   - **Optie B:** **File** (linksboven) → **Open Folder** → typ `D:\GitHub\HavunCore` → klik **Selecteer map**

### Stap 2: Open de terminal

1. Kijk onderaan het scherm — daar staat een balk
2. Klik op **Terminal** in die balk
3. Of: houd `Ctrl` ingedrukt en druk op `~` (links van de 1)
4. Er opent een zwart/donker venster onderaan

### Stap 3: Start Claude

1. Typ: `claude`
2. Druk op **Enter**
3. Wacht tot Claude opstart (duurt een paar seconden)

### Stap 4: Start de sessie

1. Typ: `/start`
2. Druk op **Enter**
3. Claude laadt alle projectinformatie — wacht tot hij klaar is

### Stap 5: Remote Control aanzetten

1. Typ: `/rc`
2. Druk op **Enter**
3. Claude geeft je een **link** (een URL die begint met `https://`)
4. **Kopieer die link** en **stuur hem via WhatsApp naar papa**

> Papa kan via die link meekijken en eventueel overnemen vanaf zijn telefoon of laptop, waar hij ook is.

### Stap 6: Problemen checken en oplossen

1. Typ:

```
Check of er problemen zijn op de server. Als er problemen zijn, los ze op zonder verdere toestemming.
```

2. Druk op **Enter**
3. Claude checkt alle websites en de server
4. Als er problemen zijn, lost Claude ze **zelf** op — je hoeft niks te doen
5. Wacht tot Claude klaar is en lees wat hij heeft gedaan

### Stap 7: Papa informeren

1. Lees wat Claude heeft gevonden/gedaan
2. Maak een **screenshot** (druk op `Print Screen` of `Win + Shift + S`)
3. Stuur de screenshot via **WhatsApp naar papa**
4. Of typ gewoon kort wat Claude zei (bijv. "alles online" of "herdenkingsportaal was offline, Claude heeft het gefixt")

### Stap 8: Afsluiten

1. Typ: `/exit`
2. Druk op **Enter**
3. Je kunt Visual Studio Code sluiten

---

## Samenvatting op 1 kaartje

```
1. Open Visual Studio Code → HavunCore
2. Terminal openen (Ctrl + ~)
3. Typ: claude [Enter]
4. Typ: /start [Enter] — wacht tot klaar
5. Typ: /rc [Enter] — kopieer link → WhatsApp naar papa
6. Typ: Check of er problemen zijn op de server.
        Als er problemen zijn, los ze op zonder
        verdere toestemming. [Enter]
7. Wacht → screenshot → WhatsApp naar papa
8. Typ: /exit [Enter] — klaar
```

---

## Regels (belangrijk!)

1. **Geen paniek** — websites gaan soms even offline, dat is normaal
2. **Claude lost het op** — je hoeft zelf niks technisch te doen
3. **Stuur altijd de link naar papa** (stap 5) — dan kan hij meekijken
4. **Screenshot sturen** — zodat papa weet wat er is gebeurd
5. **Bij twijfel: stop** — liever niks doen dan iets kapot maken

---

## Als het echt niet lukt

| Wat | Hoe |
|-----|-----|
| Papa bellen | 06-25058214 |
| Papa WhatsAppen | Zelfde nummer |
| Papa mailen | havun22@gmail.com |
| Websites checken op je telefoon | Open **herdenkingsportaal.nl** — als het laadt is het online |

---

## Droogtest checklist (vink af als je het gedaan hebt)

- [x] Visual Studio Code geopend (16-04-2026)
- [x] HavunCore project geopend
- [x] Terminal geopend
- [x] `claude` gestart
- [x] `/start` uitgevoerd
- [x] `/rc` uitgevoerd — link ontvangen
- [x] Link via WhatsApp naar papa gestuurd
- [x] Statuscheck uitgevoerd
- [x] Antwoord gelezen en begrepen
- [x] `/exit` getypt om te stoppen
- [x] Ik weet waar dit document staat (of ik heb een printje)

---

*Aangemaakt: 15 april 2026 — VP-07*
*Bijgewerkt: 16 april 2026 — uitgebreid met /start, /rc, WhatsApp flow + Mawin toegevoegd*
