---
title: Memory Opfrisser
type: claude
scope: global
last_check: 2026-05-23
---

# /mem — Geheugen Opfrisser

> Gebruik dit commando bij sessiestart én tussentijds om te voorkomen dat eerder gemaakte fouten opnieuw worden gemaakt.

## Wanneer te gebruiken

- **Verplicht bij /start** (wordt automatisch uitgevoerd)
- **Verplicht bij lange sessies** — na ~1 uur werk of context-compressie
- **Op verzoek** — als Henk `/mem` typt

---

## Stap 1: Lees de Memory Index

Lees het geheugenindex-bestand voor het huidige project. Het pad volgt het patroon:

```
C:/Users/henkv/.claude/projects/[SLUG]/memory/MEMORY.md
```

De `[SLUG]` is de projectmap als pad-slug: `D:\GitHub\HavunCore` → `D--GitHub-HavunCore`.
Probeer zowel hoofdletter als kleine letter voor de schijfletter (bijv. `D--` én `d--`).

Als het bestand niet bestaat → geen geheugens voor dit project, ga door naar stap 2.

## Stap 2: Lees alle geheugenbestanden

Lees elk bestand dat in de index gelinkt is. Let op het `metadata.type`:

| Type | Betekenis |
|------|-----------|
| `feedback` | Gedragsregels — wat te vermijden, wat te herhalen |
| `project` | Projectcontext — besluiten, motivaties, deadlines |
| `user` | Henks profiel — werkwijze, kennis, voorkeuren |
| `reference` | Externe systemen — waar iets te vinden is |

## Stap 3: Toon samenvatting

Presenteer na het lezen:

```
📋 Memory geladen — [datum van oudste / nieuwste geheugen]

🔴 Feedback (fouten vermijden):
  • [regel 1]
  • [regel 2]

🔵 Project context:
  • [feit 1]

🟢 User:
  • [voorkeur 1]
```

Alleen items vermelden die **actief relevant** zijn voor het lopende werk.

---

## Stap 4: Controleer of memories nog kloppen

Memories kunnen verouderd zijn. Bij twijfel:
- Bestandspad → check of het nog bestaat
- Functienaam / flag → grep ernaar
- Projectstatus → verifieer tegen git log / huidige code

Verouderde memory → **update of verwijder** het bestand + MEMORY.md index.

---

> **Doel:** Claude hoeft nooit dezelfde fout twee keer te maken. Wat ooit als feedback is opgeslagen, stuurt elk volgend besluit.
