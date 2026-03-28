# Claude Werkwijze - Alle Projecten

> **Doel:** Kwaliteit boven snelheid. Eerst lezen, dan denken, dan doen.
> **Geldt voor:** ALLE Havun projecten

---

## 1. Mindset: SaaS-bouwer, NIET probleemoplosser

**Bij ELKE beslissing, vraag jezelf:**
- Werkt dit voor ALLE klanten, niet alleen voor dit ene scenario?
- Wat als 50 tenants dit tegelijk gebruiken?
- Wat ziet de klant? (errors, UX, foutmeldingen = professioneel)
- Is dit productie-waardig? Geen hacks, geen "werkt op mijn machine"

---

## 2. LEES-DENK-DOE-DOCUMENTEER

### LEES (Hierarchisch!)

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

**Nooit:**
- Direct code schrijven zonder relevante context
- Aannemen hoe iets werkt
- Vorige oplossingen vergeten

### DENK (Analyseer)

**Voor je begint:**
- Begrijp ik het probleem volledig?
- Heb ik alle relevante bestanden gelezen?
- Zijn er bestaande patterns/oplossingen in de codebase?
- Wat zijn de mogelijke gevolgen van mijn wijziging?
- Moet ik eerst vragen stellen?

**Bij twijfel: VRAAG** - Liever 1 vraag teveel dan 1 aanname verkeerd. Wacht op antwoord.

### DOE (Uitvoeren)

- Kleine, atomaire wijzigingen
- Test na elke significante wijziging
- Geen haast - kwaliteit boven snelheid
- Bij fout: stop, analyseer, vraag indien nodig
- Nooit meerdere onafhankelijke wijzigingen tegelijk

### TEST (Standaard bij code wijzigingen!)

**Bij ELKE code wijziging:**
1. **VOOR:** Draai bestaande tests (`php artisan test` / `npm test`)
2. **NA:** Draai tests opnieuw — als test faalt → jouw wijziging is fout, niet de test
3. **BUG FIX:** Schrijf EERST een test die de bug reproduceert, dan pas fixen
4. **NIEUWE FEATURE:** Schrijf guard tests (response structuur, routes, views)

**Soorten tests:**
- **Regression test** — voorkomt dat een opgeloste bug terugkeert
- **Guard test** — verifieert dat kritieke code/methodes nog bestaan
- **Smoke test** — checkt dat views de verwachte elementen bevatten

Volledig pattern: `docs/kb/patterns/regression-guard-tests.md`

### DOCUMENTEER (Altijd!)

**Na elke taak/oplossing:**

| Type informatie | Locatie |
|-----------------|---------|
| Project-specifiek | `{project}/CLAUDE.md` of `{project}/.claude/` |
| Herbruikbaar pattern | `HavunCore/docs/kb/patterns/` |
| How-to/procedure | `HavunCore/docs/kb/runbooks/` |
| Architectuur beslissing | `HavunCore/docs/kb/decisions/` |
| Credentials/server info | `HavunCore/.claude/context.md` |

---

## 3. DOCS-FIRST Workflow

> **De #1 regel:** Code volgt docs. Nooit andersom.

### Classificeer elke vraag

**GROTE WIJZIGING (-> Docs-first):**
- Nieuwe features, bestaande features aanpassen
- Prijzen, teksten, labels, styling/design wijzigen
- User flows, business rules
- Alles wat de gebruiker ZIET of ERVAART

**SMALLWORK (-> Log in smallwork.md):**
- Typos in code, bug fixes, performance optimalisaties
- Technische refactoring, dependency updates

### Docs-first stappen (grote wijzigingen)

```
1. ZOEK alle MD files over dit onderwerp (grep/glob)
2. LEES volledig (niet scannen!)
3. MELD aan gebruiker:
   - Wat er staat per bestand
   - Inconsistenties
   - Wat ontbreekt
4. WACHT op bevestiging
5. UPDATE DOCS EERST als nodig
6. DAN PAS CODE schrijven vanuit de docs
```

### Smallwork stappen

```
1. Log in .claude/smallwork.md (datum, wat, waarom, bestanden)
2. Voer fix uit
3. Bij /end: review of iets permanent moet
```

### Voorbeeld: goed vs fout

**Gebruiker:** "Pas de checkout flow aan"

**FOUT:** Claude begint direct code te schrijven...

**GOED:**
```
Over checkout vond ik:

SPEC.md (regel 45-67):
  - Stap 1: Pakket kiezen → Stap 2: Gegevens → Stap 3: Betalen

CHECKOUT-FLOW.md (regel 12-34):
  - Na betaling: redirect naar /success + email

Inconsistentie: SPEC.md noemt geen email, CHECKOUT-FLOW.md wel
Ontbreekt: Wat gebeurt bij mislukte betaling?

Is dit correct en compleet? Wat wil je aanpassen?
```

---

## 4. 5 Beschermingslagen

> **Probleem:** Claude verwijdert of wijzigt UI-elementen die bewust zijn toegevoegd.
> **Oplossing:** 5 lagen van bescherming, van licht tot zwaar.

| Laag | Wat | Effort | Wanneer |
|------|-----|--------|---------|
| **1. MD docs** | Documenteer in `.claude/` WAAROM iets bestaat | Laag | Bij niet-vanzelfsprekende features |
| **2. DO NOT REMOVE** | `<!-- DO NOT REMOVE -->` comments bij kritieke code | Zeer laag | Bij eerder onterecht verwijderde elementen |
| **3. Tests** | Regressietests die breken als features verdwijnen | Medium | Bij 2x+ per ongeluk verwijderd |
| **4. CLAUDE.md regels** | Project-brede regels voor alle sessies | Eenmalig | Bij project-brede patronen |
| **5. Memory** | Cross-session context in memory files | Zeer laag | Bij project-overstijgende patronen |

### Escalatietabel

| Situatie | Minimale laag | Aanbevolen |
|----------|--------------|------------|
| Feature voor het eerst gebouwd | Laag 1 (docs) | Laag 1 + 2 |
| Feature 1x per ongeluk verwijderd | Laag 2 (comment) | Laag 2 + 4 |
| Feature 2x+ per ongeluk verwijderd | Laag 3 (test) | Laag 2 + 3 + 4 |
| Project-breed patroon | Laag 4 (CLAUDE.md) | Laag 4 + 5 |
| Cross-project patroon | Laag 5 (memory) | Laag 4 + 5 |

**Vuistregel:** Hoe vaker een fout voorkomt, hoe meer lagen je toepast. Begin met laag 1-2 (goedkoop), escaleer naar 3-5 als het probleem terugkeert.

---

## 5. PKM: Waar Staat Wat

**Projecten zijn zelfstandig, HavunCore is de orchestrator.**

```
           HavunCore (orchestrator)
                    |
    +---------------+---------------+
    v               v               v
 Project A       Project B       Project C
 (zelfstandig)   (zelfstandig)   (zelfstandig)
 Eigen docs      Eigen docs      Eigen docs
 Weet iets niet? -> Vraag Core
```

### In elk PROJECT (zelfstandig):

```
Project/
├── CLAUDE.md              <- Korte regels + context (max 60 regels)
├── .claude/
│   ├── context.md         <- ALLES over dit project (features, auth, DB, deploy)
│   └── rules.md           <- Security regels
└── docs/                  <- Eventuele extra project docs
```

### In HAVUNCORE (gedeeld):

```
HavunCore/
├── CLAUDE.md              <- Korte regels
├── .claude/
│   ├── context.md         <- Server, credentials, API keys
│   └── rules.md           <- Security regels
└── docs/kb/
    ├── runbooks/          <- Procedures (hoe doe ik X?)
    ├── patterns/          <- Herbruikbare code patterns
    ├── contracts/         <- Gedeelde definities tussen projecten
    ├── reference/         <- API specs, server config
    ├── decisions/         <- Architectuur beslissingen
    ├── templates/         <- Setup templates
    └── projects/          <- Per-project details
```

### Wat-staat-waar

| Vraag | Antwoord |
|-------|----------|
| Project-specifieke info? | In het project zelf |
| Credentials, server paths? | HavunCore/.claude/context.md |
| "Hoe werkt feature X"? | In het project dat feature X heeft |
| Gedeelde definities? | HavunCore/docs/kb/contracts/ |
| Herbruikbare code? | HavunCore/docs/kb/patterns/ |

### Kennisflow

Project weet iets niet -> HavunCore geeft antwoord -> Update project docs -> Volgende keer weet project het zelf.

### Token-efficientie

```
CLAUDE.md = Wat ik ALTIJD moet weten (gedrag, regels) - max 60 regels
.claude/  = Wat ik moet kunnen OPZOEKEN (details) - on-demand
```

---

## 6. UI/UX Standaarden

**Altijd automatisch toepassen, niet wachten tot gebruiker vraagt.**

### Scroll positie behouden
Bij ELKE async operatie die de UI update:
- Bewaar `window.scrollY` voor de operatie
- Herstel met `window.scrollTo(0, scrollPos)` na voltooiing
- Geldt voor: AJAX/fetch, modal open/close, dropdown wijzigingen, DOM manipulaties

### Focus behouden
- Na form submit zonder page reload: focus terug op relevant element
- Na modal sluiten: focus terug naar trigger element

### Loading states
- Toon loading indicator bij operaties > 200ms
- Disable knoppen tijdens operatie (voorkom dubbel klikken)

---

## 7. Concrete Regels

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
5. Fix
6. Documenteer oplossing voor toekomst
```

### Bij nieuw Laravel project
```
ALTIJD i18n voorbereiden, ook bij alleen Nederlands:
1. config/app.php: locale, fallback_locale, available_locales
2. lang/nl.json aanmaken (mag leeg {} zijn)
3. Alle user-facing strings: __() gebruiken, NOOIT hardcoded
4. Zie pattern: docs/kb/patterns/laravel-i18n.md
```

### Bij herhaling van informatie door gebruiker
```
DIT MAG NIET GEBEUREN!
1. Stop direct
2. Vraag waar dit opgeslagen moet worden
3. Sla het NU op
4. Bevestig aan gebruiker
```

---

## 8. Checklist

Voordat je code schrijft, vraag jezelf:

- [ ] Heb ik CLAUDE.md gelezen?
- [ ] Heb ik de relevante code/docs bekeken?
- [ ] Begrijp ik wat de gebruiker wil?
- [ ] Werkt dit voor ALLE klanten/tenants? (SaaS-mindset!)
- [ ] Zijn er bestaande patterns die ik kan volgen?
- [ ] Moet ik eerst vragen stellen?
- [ ] Heb ik DO NOT REMOVE comments gecheckt?
- [ ] Verwijder ik geen bestaande UI-elementen of features?
- [ ] Behoud ik scroll/focus bij async operaties?
- [ ] Zijn docs-first stappen gevolgd? (grote wijziging)
- [ ] Heb ik bestaande tests gedraaid VOOR mijn wijziging?
- [ ] Heb ik tests geschreven/bijgewerkt NA mijn wijziging?
- [ ] Bij bug fix: is er een regression test die de bug reproduceert?

**Als je ook maar 1 vakje niet kunt aanvinken: STOP en lees/vraag eerst.**

---

*Laatst bijgewerkt: 28 maart 2026*
