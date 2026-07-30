---
title: Arch — Gemini blauwdruk genereren
type: claude
scope: havuncore
last_check: 2026-05-20
---

# /arch — Gemini blauwdruk

Automatische Gemini-pipeline: havun:pack → Gemini → blueprint op schijf.
Jij typt de opdracht, de rest is achtergrondwerk.

## Instructies voor Claude

### Stap 1 — Project bepalen uit $ARGUMENTS

Controleer of `$ARGUMENTS` een `--project=X` flag bevat:
- Ja → gebruik die waarde
- Nee → detecteer uit de huidige werkmap: neem het laatste padsegment, lowercase (bijv. `D:\GitHub\SafeHavun` → `safehavun`). Uitzondering: als de map `HavunCore` is, vraag dan éénmalig welk project — HavunCore zelf heeft geen eigen pack.
- Niet detecteerbaar → vraag éénmalig, ga daarna direct door

Extraheer vervolgens de prompt (alles uit `$ARGUMENTS` zonder de `--project=X` flag).

### Stap 2 — Gemini pipeline draaien

Voer dit uit via Bash. Toon GEEN ruwe output aan de gebruiker — alleen voortgang:

```bash
cd D:\GitHub\HavunCore && php artisan havun:gemini --project={project} "{prompt}" --include-source
```

Meld kort: `Gemini pipeline gestart voor {project}...`
Wacht op voltooiing. Blueprint landt automatisch in `{projectpad}/.claude/blueprint.md`.

### Stap 3 — Blueprint lezen en presenteren

Lees `{projectpad}/.claude/blueprint.md` en presenteer beknopt:
- Timestamp bovenaan (blockquote header) — controleer of het vers is
- Wat Gemini heeft ontworpen (2-4 regels samenvatting)
- Welke bestanden/modules worden geraakt
- Eventuele risico's of openstaande vragen uit de blueprint

### Stap 4 — Handoff

Eindig ALTIJD met:
> Blueprint klaar in `.claude/blueprint.md`. Typ `/mpc` om uit te voeren, of geef aanvullingen.

## Gebruik

```
/arch Optimaliseer de whale tracking in SafeHavun
/arch --project=judotoernooi Fix de poule-berekening bij oneven deelnemers
/arch --project=herdenkingsportaal Voeg bulk-import toe voor overledenen
```

## Regels

- Toon NOOIT ruwe Gemini CLI output — alleen de verwerkte samenvatting
- Als `havun:gemini` faalt: meld de fout en stop — geen fallback naar eigen implementatie
- Eén taak per aanroep (atomair)
- Claude begint NIET zelf te coderen na de blueprint — wacht op `/mpc` + "ga maar"
- **Gaat de blauwdruk over een nieuw project of over het fundament?** Zet de vijf
  intake-vragen uit `standards/stack-keuze.md` in de prompt mee, en lees de blauwdruk
  kritisch op één punt: **kiest Gemini de stack, of erft hij hem?** Een model grijpt
  hier terug op het GitHub-gemiddelde, en dat gemiddelde is een webframework.
  Zie `standards/ai-synthese-risicos.md` + `patterns/fundament-versus-omweg.md`.
- **Stelt de blauwdruk een omweg voor** (framework omzeilen, tweede runtime, eigen poort,
  vangnet, sidecar)? Dan hoort die in `docs/omwegen.md` — en bij de tweede is het een
  architectuurreview, geen implementatie. `patterns/omwegen-tellen.md`
