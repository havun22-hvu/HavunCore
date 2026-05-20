---
title: Gemini + Claude CLI — Hybride AI Werkwijze
type: runbook
date: 2026-05-20
applies_to: alle projecten
---

# Gemini + Claude CLI — Hybride AI Werkwijze

## De kern in één zin

Gemini is het langetermijngeheugen en de architect. Claude is de kritische lokale uitvoerder. Ze werken samen via een geautomatiseerde pipe, niet via copy/paste.

## Waarom deze werkwijze

Claude heeft ~200k context en geen geheugen tussen sessies. Gemini heeft 2M context en kan de volledige codebase onthouden. De combinatie is sterker dan elk model apart:

- Gemini ziet het grote plaatje en maakt blauwdrukken met echte bestandsnamen
- Claude controleert lokaal of die blauwdruk klopt met de codebase en voert uit
- Geen "blinde executor" — Claude grijpt in als Gemini iets verkeerd aanneemt

## Rolverdeling

| Taak | Wie |
|---|---|
| Architectuur bewaken | Gemini |
| Blauwdrukken maken | Gemini |
| MD-docs schrijven/bijwerken | Claude |
| Code uitvoeren | Claude |
| Lokale bestandsnamen verifiëren | Claude |
| /simplify + tests runnen | Claude |
| Langetermijngeheugen | Gemini (of de docs zelf) |

## Vereisten

### Gemini CLI
```bash
npm install -g @google/gemini-cli
gemini --version  # moet 0.42.0 of hoger tonen
```

### GEMINI_API_KEY
Eenmalig instellen in Windows (permanent):
```powershell
[Environment]::SetEnvironmentVariable("GEMINI_API_KEY", "jouw-key", "User")
```
Key genereren via: https://aistudio.google.com/apikey

## De dagelijkse workflow

### Stap 1 — Context inpakken
Vanuit de projectmap in VS Code terminal:
```bash
php artisan havun:pack --project=projectnaam > gemini_context.md
```

### Stap 2 — Blauwdruk genereren (frictieloos, geen browser)
```bash
cat gemini_context.md | gemini "Jouw opdracht hier. Lever op als Markdown." > gemini_blueprint.md
```

Of in één pipe:
```bash
php artisan havun:pack --project=projectnaam | gemini "Jouw opdracht hier." > gemini_blueprint.md
```

### Stap 3 — Claude voert uit
In Claude CLI:
```
Voer gemini_blueprint.md uit. Controleer of bestandsnamen en patronen 
kloppen met de lokale codebase. Pas /simplify toe, run de tests.
```

## Voorbeelden per use case

### Feature bouwen
```bash
php artisan havun:pack --project=safehavun | \
  gemini "Optimaliseer ETH whale tracking via txlistinternal. Schrijf PHP-logica uit én update WHALE-TRACKING.md. Lever als Markdown." \
  > gemini_blueprint.md
```

### MD-doc bijwerken
```bash
php artisan havun:pack --project=herdenkingsportaal | \
  gemini "Update SPEC.md sectie 4 met de nieuwe auth-flow. Lever alleen de bijgewerkte sectie als Markdown." \
  > gemini_blueprint.md
```

### Bug analyseren
```bash
php artisan havun:pack --project=judotoernooi | \
  gemini "Analyseer waarom de poule-indeling bij oneven aantal judoka's crasht. Geef oorzaak + fix als Markdown." \
  > gemini_blueprint.md
```

## Regels

**Eén taak per pipe** — geef Gemini nooit drie features tegelijk. Atomaire opdrachten per keer.

**Claude controleert altijd** — nooit blind uitvoeren. Claude vergelijkt blauwdruk met lokale codebase voordat er iets wordt aangepast.

**CLAUDE.md blijft compact** — de ⛔ STOP-lijn + project-gotchas is genoeg. Geen honderden regels gedragsregels meer.

**Scope: één project per sessie** — havun:pack voor project X, uitvoeren in sessie voor project X.

**API key nooit in chat** — stel GEMINI_API_KEY in via PowerShell, niet via Claude CLI.

## PowerShell encoding gotcha

Als gemini_blueprint.md rare tekens bevat (UTF-16 probleem):
```powershell
php artisan havun:pack --project=projectnaam | gemini "opdracht" | Out-File -Encoding utf8 gemini_blueprint.md
```

## Wat we bewust NIET doen

- Claude opvoeden via steeds meer MD-regels — werkt niet structureel
- Gemini als blinde dictator — Claude blijft kritische poortwachter
- API-integratie in havun:pack bouwen — de pipe werkt al zonder extra code
- Browser openen voor Gemini — de CLI elimineert dat volledig

## Status

Geïnstalleerd: 2026-05-20
Gemini CLI versie: 0.42.0
GEMINI_API_KEY: moet nog permanent worden ingesteld via PowerShell
Eerste echte test: SafeHavun ETH whale tracking (volgende sessie)
