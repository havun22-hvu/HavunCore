# Werkplan Droogtest — Thiemo

> **Wat is dit?** Een oefening zodat jij weet wat je moet doen als papa niet bereikbaar is en er iets mis is met de websites.
> **Hoe lang duurt het?** ~10 minuten
> **Wanneer?** 1x samen doen, daarna weet je hoe het werkt.

---

## Wat je moet weten (de basis)

- Papa heeft websites gemaakt die op een server draaien (een computer in een datacenter in Duitsland)
- Die server draait 24/7 — ook als de PC thuis uit staat
- Als er iets misgaat, kan "Claude" (een AI-assistent) het vaak zelf oplossen
- **Jouw rol:** als papa echt niet bereikbaar is, kun jij Claude starten en vragen om te helpen

---

## De oefening — stap voor stap

### Stap 1: Open het programma

1. Open **Visual Studio Code** (blauw icoon, staat op het bureaublad of in het Start-menu)
2. Als het opent, zie je links een zijbalk met icoontjes
3. Klik op het **mapjes-icoon** (4e van onderen, twee mapjes boven elkaar) — dit is de Project Manager
4. Kies **HavunCore** uit de lijst

> **Lukt het niet?** Ga dan naar **File** (linksboven) → **Open Folder** → typ `D:\GitHub\HavunCore` → klik **Selecteer map**

### Stap 2: Open de terminal

1. Kijk onderaan het scherm — daar staat een balk
2. Klik op **Terminal** in die balk
3. Of gebruik de sneltoets: houd `Ctrl` ingedrukt en druk op de `~` toets (links van de 1)
4. Er opent een zwart/donker venster onderaan — dat is de terminal

### Stap 3: Start Claude

1. Typ in de terminal: `claude`
2. Druk op **Enter**
3. Wacht tot Claude opstart (duurt een paar seconden)
4. Je ziet een invoerveld waar je kunt typen

### Stap 4: Vraag Claude om alles te checken

1. Typ dit (of kopieer het):

```
Check de status van alle Havun websites en vertel me of er problemen zijn.
```

2. Druk op **Enter**
3. Claude gaat nu alle websites controleren
4. Wacht tot Claude klaar is (kan 30 seconden duren)

### Stap 5: Lees het antwoord

Claude geeft je een overzicht, bijvoorbeeld:

```
✓ herdenkingsportaal.nl — online
✓ judotoernooi.havun.nl — online
✓ havuncore.havun.nl — online
```

- **Alles groen/✓?** → Niks aan de hand, klaar!
- **Iets rood/✗?** → Ga naar stap 6

### Stap 6: Laat Claude het oplossen (alleen als er iets mis is)

1. Typ:

```
Er is een probleem met [naam van de website]. Kun je het oplossen?
```

2. Claude vraagt misschien of hij iets mag doen
   - **"y" of "yes"** = ja, doe maar
   - **"n" of "no"** = nee, stop maar
3. Bij twijfel: typ **n** en bel papa

### Stap 7: Afsluiten

1. Typ: `/exit`
2. Druk op **Enter**
3. Claude stopt
4. Je kunt Visual Studio Code sluiten

---

## Samenvatting op 1 kaartje

```
1. Open Visual Studio Code
2. Open HavunCore (Project Manager of File → Open Folder)
3. Open Terminal (Ctrl + ~)
4. Typ: claude [Enter]
5. Typ: Check de status van alle Havun websites [Enter]
6. Lees het antwoord
7. Bij probleem: vraag Claude om het op te lossen
8. Afsluiten: /exit [Enter]
```

---

## Regels (belangrijk!)

1. **Geen paniek** — websites gaan soms even offline, dat is normaal
2. **Claude weet wat hij doet** — je hoeft zelf niks technisch te doen
3. **Bij twijfel: stop** — liever niks doen dan iets kapot maken
4. **Probeer papa te bereiken** — bel, WhatsApp, SMS
5. **Pas op met "yes"** — als Claude iets wil doen dat je niet snapt, zeg "no"

---

## Als het echt niet lukt

| Wat | Hoe |
|-----|-----|
| Papa bellen | 06-25058214 |
| Papa mailen | havun22@gmail.com |
| Websites checken zonder PC | Open herdenkingsportaal.nl op je telefoon — als het laadt is het online |

---

## Droogtest checklist (vink af als je het gedaan hebt)

- [ ] Visual Studio Code geopend
- [ ] HavunCore project geopend
- [ ] Terminal geopend
- [ ] `claude` gestart
- [ ] Statuscheck uitgevoerd
- [ ] Antwoord gelezen en begrepen
- [ ] `/exit` getypt om te stoppen
- [ ] Ik weet waar dit document staat (of ik heb een printje)

---

*Aangemaakt: 15 april 2026 — VP-07 droogtest*
