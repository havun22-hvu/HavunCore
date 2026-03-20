# Focus Command

> Laad alle relevante kennis over een onderwerp VOORDAT je code schrijft.
> Gebruik: `/f inschrijving-flow judotoernooi` of `/f "betaalmodule refactoren herdenkingsportaal"`

## Input

De gebruiker geeft een doel/onderwerp mee als argument: $ARGUMENTS

## Stappen

### 1. Bepaal zoektermen

Splits het doel op in 2-4 zoektermen. Bijvoorbeeld:
- "inschrijving-flow judotoernooi" → `inschrijving`, `registration`, `judotoernooi`
- "betaalmodule herdenkingsportaal" → `betaling`, `payment`, `mollie`, `herdenkingsportaal`

### 2. Doorzoek de kennisbank

```bash
cd D:\GitHub\HavunCore && php artisan docs:search "ZOEKTERM1" --limit=5
cd D:\GitHub\HavunCore && php artisan docs:search "ZOEKTERM2" --limit=5
```

Herhaal voor elke zoekterm.

### 3. Lees ALLE gevonden relevante bestanden

- MD docs die gevonden worden: **volledig lezen**
- Code bestanden die gevonden worden: **volledig lezen**
- Project CLAUDE.md + .claude/context.md van het betreffende project: **volledig lezen**
- Memory bestanden die relevant zijn: **lezen**

**GEEN bestanden overslaan.** Dit is het hele punt van /f.

### 4. Grep de codebase

Zoek in het betreffende project naar gerelateerde code:

```bash
# In het project zelf
cd [project directory]
```

Gebruik Grep tool met de zoektermen om relevante controllers, models, views, routes te vinden.
Lees de gevonden bestanden.

### 5. Samenvatting geven

Geef een gestructureerde samenvatting aan de gebruiker:

```
🎯 Focus: [onderwerp]
📁 Project: [projectnaam]

📄 Docs gevonden:
  - [bestand]: [wat er staat, 1 regel]
  - [bestand]: [wat er staat, 1 regel]

💻 Code gevonden:
  - [bestand]: [wat het doet, 1 regel]
  - [bestand]: [wat het doet, 1 regel]

⚠️ Aandachtspunten:
  - [DO NOT REMOVE items]
  - [inconsistenties tussen docs]
  - [ontbrekende docs]

🧠 Samenvatting:
[3-5 regels over hoe dit onderwerp nu werkt, wat de huidige staat is,
en waar je op moet letten]

Klopt dit? Mag ik beginnen?
```

### 6. WACHT op bevestiging

**Schrijf GEEN code** totdat de gebruiker bevestigt dat de samenvatting klopt.

## Regels

- Als er GEEN docs gevonden worden: meld dit. Vraag of er docs aangemaakt moeten worden.
- Als er inconsistenties zijn: meld deze EERST. Niet beginnen met code.
- Als het project niet herkend wordt: vraag welk project bedoeld wordt.
- Lees ALTIJD de DO NOT REMOVE comments in bestanden die je gaat aanraken.
