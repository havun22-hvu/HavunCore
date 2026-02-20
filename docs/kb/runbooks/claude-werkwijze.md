# Claude Werkwijze - Alle Projecten

> **Doel:** Kwaliteit boven snelheid. Eerst lezen, dan denken, dan doen.
> **Geldt voor:** ALLE Havun projecten

## Mindset: SaaS-bouwer, NIET probleemoplosser

**Bij ELKE beslissing, vraag jezelf:**
- Werkt dit voor ALLE klanten, niet alleen voor dit ene scenario?
- Wat als 50 tenants dit tegelijk gebruiken?
- Wat ziet de klant? (errors, UX, foutmeldingen = professioneel)
- Is dit productie-waardig? Geen hacks, geen "werkt op mijn machine"

## Het Probleem

- Te snel beginnen met code schrijven zonder volledige context
- Fouten maken die later hersteld moeten worden
- Informatie niet opslaan, waardoor gebruiker zich moet herhalen
- Slechte documentatie, moeilijk terug te vinden

## De Oplossing: LEES-DENK-DOE-DOCUMENTEER

### 1. LEES (Hiërarchisch!)

> **Volledig systeem:** `docs/kb/PKM-SYSTEEM.md`

**Lees in deze volgorde, stop wanneer je genoeg weet:**

```
Niveau 1: ALTIJD lezen (elke sessie)
├── CLAUDE.md van het project (regels, context)
│
Niveau 2: Bij specifieke taak
├── .claude/context.md (project details)
├── Relevante code voor de taak
│
Niveau 3: Bij onduidelijkheid
├── docs/ van het project
├── HavunCore/docs/kb/ (gedeelde kennis)
│
Niveau 4: Bij nieuwe patronen/systemen
└── HavunCore/docs/kb/patterns/ of /runbooks/
```

**Principe:** Lees alleen wat RELEVANT is voor de taak. Niet alles - projecten zijn te groot.

**Bij specifieke opdracht:**
1. Lees CLAUDE.md (altijd)
2. Zoek relevante code/docs voor DIE opdracht
3. Lees die specifieke bestanden opnieuw, grondig
4. Pas dan beginnen met uitvoeren

**Nooit:**
- Direct code schrijven zonder relevante context
- Aannemen hoe iets werkt
- Vorige oplossingen vergeten

### 2. DENK (Analyseer)

**Voor je begint:**
- Begrijp ik het probleem volledig?
- Heb ik alle relevante bestanden gelezen?
- Zijn er bestaande patterns/oplossingen in de codebase?
- Wat zijn de mogelijke gevolgen van mijn wijziging?
- Moet ik eerst vragen stellen?

**Bij twijfel: VRAAG**
- Liever 1 vraag teveel dan 1 aanname verkeerd
- Wacht op antwoord voordat je doorgaat

### 3. DOE (Uitvoeren)

**Principes:**
- Kleine, atomaire wijzigingen
- Test na elke significante wijziging
- Geen haast - kwaliteit boven snelheid
- Bij fout: stop, analyseer, vraag indien nodig

**Verboden:**
- Meerdere onafhankelijke wijzigingen tegelijk
- "Even snel" iets fixen zonder context
- Doorgaan bij onduidelijkheid

### 4. DOCUMENTEER (Altijd!)

**Na elke taak/oplossing:**
- Sla nieuwe kennis op in de juiste plek
- Update relevante docs als er iets veranderd is
- Noteer waarom een beslissing is genomen

**Waar opslaan:**
| Type informatie | Locatie |
|-----------------|---------|
| Project-specifiek | `{project}/CLAUDE.md` of `{project}/.claude/` |
| Herbruikbaar pattern | `HavunCore/docs/kb/patterns/` |
| How-to/procedure | `HavunCore/docs/kb/runbooks/` |
| Architectuur beslissing | `HavunCore/docs/kb/decisions/` |
| Credentials/server info | `HavunCore/.claude/context.md` |

## Concrete Regels

### Bij nieuwe taak
```
1. Lees CLAUDE.md
2. Lees relevante code/docs
3. Stel vragen bij onduidelijkheid
4. WACHT op antwoord
5. Maak plan (indien complex)
6. Voer uit
7. Documenteer
```

### Bij foutmelding/probleem
```
1. Lees de VOLLEDIGE foutmelding
2. Zoek in codebase naar gerelateerde code
3. Check of dit probleem eerder voorkwam (git log, docs)
4. Analyseer root cause
5. Stel vragen indien nodig
6. Fix
7. Documenteer oplossing voor toekomst
```

### Bij herhaling van informatie door gebruiker
```
DIT MAG NIET GEBEUREN!

Als de gebruiker iets moet herhalen:
1. Stop direct
2. Vraag waar dit opgeslagen moet worden
3. Sla het NU op
4. Bevestig aan gebruiker
```

## Snelheid vs Kwaliteit

| Fout | Goed |
|------|------|
| Snel 3x proberen | 1x rustig goed doen |
| Aannemen | Vragen |
| Code eerst, docs later | Docs lezen, dan code |
| "Dat wist ik niet" | "Laat me dat opzoeken" |
| Vergeten wat eerder besproken | Documenteren en teruglezen |

## UI/UX Standaarden

**Altijd automatisch toepassen, niet wachten tot gebruiker vraagt:**

### Scroll positie behouden
Bij ELKE async operatie die de UI update:
- Bewaar `window.scrollY` vóór de operatie
- Herstel met `window.scrollTo(0, scrollPos)` na voltooiing

```javascript
async function doAsyncOperation() {
    const scrollPos = window.scrollY;
    // ... fetch/ajax call ...
    // Na success:
    window.scrollTo(0, scrollPos);
}
```

**Geldt voor:**
- AJAX/fetch calls
- Modal open/close
- Dropdown/select wijzigingen
- DOM manipulaties
- Preset opslaan/laden/verwijderen

### Focus behouden
- Na form submit zonder page reload: focus terug op relevant element
- Na modal sluiten: focus terug naar trigger element

### Loading states
- Toon loading indicator bij operaties > 200ms
- Disable knoppen tijdens operatie (voorkom dubbel klikken)

---

## Checklist voor Claude

Voordat je code schrijft, vraag jezelf:

- [ ] Heb ik CLAUDE.md gelezen?
- [ ] Heb ik de relevante code bekeken?
- [ ] Begrijp ik wat de gebruiker wil?
- [ ] Werkt dit voor ALLE klanten/tenants? (SaaS-mindset!)
- [ ] Weet ik waar dit in past in de architectuur?
- [ ] Zijn er bestaande patterns die ik kan volgen?
- [ ] Moet ik eerst vragen stellen?
- [ ] Behoud ik scroll/focus bij async operaties? (standaard!)

**Als je ook maar 1 vakje niet kunt aanvinken: STOP en lees/vraag eerst.**
