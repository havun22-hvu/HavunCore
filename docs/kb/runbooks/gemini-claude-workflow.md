---
title: Gemini + Claude CLI — Hybride AI Werkwijze
type: runbook
scope: alle projecten
last_check: 2026-05-29
---

# Gemini + Claude CLI — Hybride AI Werkwijze

**Doctrine:** Macro-Architect (Gemini) ←→ Micro-Executor (Claude)

## Waarom deze werkwijze

Claude heeft ~200k context en geen geheugen tussen sessies. Langdurige sessies leiden tot context-verwatering en scope-drift. Gemini heeft 2M+ tokens en bewaart het grote plaatje.

**Oplossing:** Claude wordt ontlast van de architectuurrol. Gemini maakt de blauwdrukken — Claude is de kritische, lokale poortwachter en executor.

## Rolverdeling

| Taak | Wie |
|---|---|
| Architectuur bewaken | Gemini |
| Blauwdrukken + MD-docs schrijven | Gemini |
| Lokale bestandsnamen verifiëren | Claude |
| Code uitvoeren op schijf | Claude |
| /simplify + tests runnen | Claude |
| Langetermijngeheugen | Gemini (of de docs zelf) |

---

## Vereisten

### Gemini CLI
```bash
npm install -g @google/gemini-cli
gemini --version  # moet 0.42.0 of hoger tonen
```

### GEMINI_API_KEY
Eenmalig instellen in Windows (permanent) — doe dit in een **losse PowerShell**, niet via Claude CLI:
```powershell
[Environment]::SetEnvironmentVariable("GEMINI_API_KEY", "jouw-key", "User")

# Verificeer
[Environment]::GetEnvironmentVariable("GEMINI_API_KEY", "User")
```

**Key ophalen via HavunCore** — Claude zoekt de key op zonder dat jij zelf in `.env` hoeft te duiken:
```
"Zoek de Gemini API key op voor project X"
→ Claude SSH → /var/www/<project>/production/.env → masked weergeven
```

Key genereren (nieuw project): https://aistudio.google.com/apikey

**Veiligheidsregel: plak de key nooit in de chat.** Een key die zichtbaar is geweest in een chatvenster is gecompromitteerd (terminal-geschiedenis, logs, context-exports).

---

## De standaard cyclus (4 stappen)

### Stap 1 — Context inpakken + blauwdruk genereren
Gebruik de `/arch` skill in Claude CLI — pipeline draait automatisch op de achtergrond:
```
/arch --project=<naam> [taak beschrijving]
```

Of handmatig via PowerShell:
```powershell
php artisan havun:pack --project=<naam> | gemini "Analyseer deze context. [Taak]. Lever op als Markdown." | Out-File -Encoding utf8 "D:\GitHub\<naam>\.claude\blueprint.md"
```

### Stap 2 — Claude valideert en voert uit
Na `/arch`: typ `/mpc` om de blueprint uit te voeren.

Handmatig in Claude CLI:
```
Voer .claude/blueprint.md uit.
- Controleer of bestandsnamen en patronen kloppen met de lokale codebase
- Schrijf wijzigingen weg op schijf, pas /simplify toe
- Update lokale project-docs met Gemini's tekst
```

### Stap 3 — Test-gate
```bash
php artisan test --no-coverage
```

---

## De uitgebreide cyclus — met reflectieronde (6 stappen)

Gebruik dit voor complexe features of architectuurbeslissingen waarbij kwaliteit zwaarder weegt dan snelheid.

### Stap 1 — Gemini ontwerpt de eerste opzet
```powershell
php artisan havun:pack | gemini "[Taak]. Lever op als Markdown." | Out-File -Encoding utf8 {project}/.claude/blueprint.md
```

### Stap 2 — Claude schiet gaten
In Claude CLI — nog NIET uitvoeren:
```
Review {project}/.claude/blueprint.md. Welke lokale edge-cases, ontbrekende bestanden
of syntax-fouten zie jij die Gemini in de cloud over het hoofd heeft gezien?
Schrijf je kritiek en verbeterpunten in claude_critique.md.
```

### Stap 3 — Gemini corrigeert op basis van Claude's kritiek
```powershell
Get-Content claude_critique.md | gemini "Pas de eerdere blauwdruk aan op basis van deze kritiek van de lokale executor." | Out-File -Encoding utf8 {project}/.claude/blueprint_final.md
```

### Stap 4 — Definitieve uitvoering
In Claude CLI:
```
De blauwdruk is gecorrigeerd in {project}/.claude/blueprint_final.md.
Voer nu uit, pas /simplify toe en update de docs.
```

### Stap 5 — Test-gate
```bash
php artisan test --no-coverage
```

### Catch: Claude wacht als Gemini aan zet is
Als de gebruiker bezig is met de Gemini-pipeline, gaat Claude NIET zelf door met coderen. Geen "laat me even kijken", geen autonoom doorwerken. Claude wacht op de blueprint en voert pas uit na "ga maar".

### Catch: vage prompts geven generieke output
Als Gemini een algemene codebase-analyse geeft in plaats van de gevraagde implementatie, was de prompt te vaag.

**Slecht:** `"Optimaliseer de ETH whale tracking"`
**Goed:** `"Schrijf de volledige PHP-code voor WhaleTrackingService::fetchTxInternal() die txlistinternal gebruikt met rate limiting van max 1 call per 5 seconden. Lever als kant-en-klare PHP-methode."`

Als Gemini generiek blijft → Claude voert de taak zelf uit als executor zonder blueprint.

### Catch: maximaal één reflectieronde
LLM's kunnen eindeloos beleefd heen-en-weer praten. De cyclus is altijd:
**Gemini ontwerpt → Claude schiet gaten → Gemini corrigeert → Claude voert uit.**
Nooit meer dan één ronde. Jij bent de regisseur die de knop indrukt.

### Snelle variant (zonder bestanden)
Claude's kritiek direct verwerken zonder tussenbestanden:
```powershell
# Claude geeft kritiek mondeling in de terminal
# Jij verwerkt die direct in de volgende pipe:
php artisan havun:pack --project=<naam> | gemini "Pas de blauwdruk aan, houd rekening met: [wat Claude net zei]" | Out-File -Encoding utf8 "D:\GitHub\<naam>\.claude\blueprint.md"
```

---

## Voorbeelden

### Feature bouwen (via /arch)
```
/arch --project=safehavun "Optimaliseer ETH whale tracking via txlistinternal. Schrijf PHP-logica uit én update WHALE-TRACKING.md."
```

### Feature bouwen (handmatig)
```powershell
php artisan havun:pack --project=safehavun | gemini "Optimaliseer ETH whale tracking. Lever als Markdown." | Out-File -Encoding utf8 "D:\GitHub\SafeHavun\.claude\blueprint.md"
```

### MD-doc bijwerken
```
/arch --project=herdenkingsportaal "Update SPEC.md sectie 4 met de nieuwe auth-flow."
```

### Bug analyseren
```
/arch --project=judotoernooi "Analyseer waarom de poule-indeling bij oneven aantal judoka's crasht. Geef oorzaak + fix."
```

---

## Harde regels voor Claude

**⛔ STOP-lijn:** Claude begint NOOIT met coderen voordat `.claude/blueprint.md` op schijf staat en de gebruiker expliciet "ga maar" heeft getypt.

**Stateless discipline:** Claude heeft geen geheugen over sessies heen. De blueprint is het geheugen — vertrouw erop.

**Eigenaarschap van docs:** Claude schrijft de lokale docs bij, maar gebruikt daarvoor de inhoud die Gemini heeft geformuleerd.

**Één taak per pipe:** Geef Gemini nooit drie features tegelijk. Atomaire opdrachten.

**Scope: één project per sessie** — havun:pack voor project X, uitvoeren in sessie voor project X.

---

## Eenmalige setup (Windows)

```powershell
# Gemini hoeft niet elke keer om trust te vragen
[Environment]::SetEnvironmentVariable("GEMINI_CLI_TRUST_WORKSPACE", "true", "User")

# API key (ophalen via Claude/HavunCore als je hem kwijt bent)
[Environment]::SetEnvironmentVariable("GEMINI_API_KEY", "jouw-key", "User")
```

Gebruik altijd `| Out-File -Encoding utf8` in plaats van `>` om encoding-problemen te voorkomen.

---

## Dynamic Workflows (research preview — Claude Code nieuw)

Voor grote taken waarbij de werkwijze zelf afgedwongen moet worden.

### Wanneer gebruiken
| Taak | Aanpak |
|------|--------|
| Klein, < 5 bestanden, afgebakend | Claude direct |
| Bekend patroon, gemiddeld | `/arch` + `/mpc` |
| Groot: audit, migratie, pre-publish | Dynamic workflow |

### Hoe starten
Typ gewoon de opdracht — geen `/arch` of `/mpc` meer nodig. Claude herkent de omvang en start zelf een workflow:

```
Implementeer QR scanner voor JudoScoreBoard
```

Claude's workflow roept `havun:gemini` automatisch aan als eerste stap (architectuur), daarna parallelle implementatie.

### Voordelen vs. handmatige cyclus
- Workflow dwingt stappen af — Claude kan ze niet overslaan
- Gemini aanroep automatisch ingebouwd, niet handmatig
- Parallelle subagenten per module/bestand
- Verificatie vóór commit

### Let op
- Token-kosten zijn substantieel hoger dan normaal
- Research preview — nog niet 100% stabiel
- Eén dynamic workflow per sessie (niet stapelen)

---

## Wat we bewust NIET doen

- Claude als architect inzetten voor grote multi-file taken
- Gemini-output blind overnemen zonder lokale verificatie
- Browser openen voor Gemini — `havun:gemini` of de CLI doet het zonder browser
- Key in chat plakken of in git committen

---

## Status

Geïnstalleerd: 2026-05-20
Gemini CLI versie: 0.42.0
Gemini API model (havun:gemini): gemini-2.5-flash, override met --model=gemini-2.5-pro voor complexe taken
Blueprint locatie: `{project}/.claude/blueprint.md` — automatisch opgepakt door `/start`
Live API samples: `havun:pack --include-source` fetcht echte API-responses mee (timeout 3s, graceful fallback)
GEMINI_API_KEY: in `.env` (HavunCore) + Windows User Environment
