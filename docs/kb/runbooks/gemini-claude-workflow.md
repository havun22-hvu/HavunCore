---
title: Gemini + Claude CLI — Hybride AI Werkwijze
type: runbook
scope: alle projecten
last_check: 2026-05-20
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
```bash
php artisan havun:pack --project=<projectnaam> | gemini "Analyseer deze context. [Taak]. Lever op als Markdown." > gemini_blueprint.md
```

### Stap 2 — Claude valideert en voert uit
In Claude CLI:
```
Voer gemini_blueprint.md uit.
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
```bash
php artisan havun:pack --project=<projectnaam> | gemini "[Taak]. Lever op als Markdown." > gemini_blueprint.md
```

### Stap 2 — Claude schiet gaten
In Claude CLI — nog NIET uitvoeren:
```
Review gemini_blueprint.md. Welke lokale edge-cases, ontbrekende bestanden
of syntax-fouten zie jij die Gemini in de cloud over het hoofd heeft gezien?
Schrijf je kritiek en verbeterpunten in claude_critique.md.
```

### Stap 3 — Gemini corrigeert op basis van Claude's kritiek
```bash
cat claude_critique.md | gemini "Pas de eerdere blauwdruk aan op basis van deze kritiek van de lokale executor." > gemini_blueprint_final.md
```

### Stap 4 — Definitieve uitvoering
In Claude CLI:
```
De blauwdruk is gecorrigeerd in gemini_blueprint_final.md.
Voer nu uit, pas /simplify toe en update de docs.
```

### Stap 5 — Test-gate
```bash
php artisan test --no-coverage
```

### Catch: maximaal één reflectieronde
LLM's kunnen eindeloos beleefd heen-en-weer praten. De cyclus is altijd:
**Gemini ontwerpt → Claude schiet gaten → Gemini corrigeert → Claude voert uit.**
Nooit meer dan één ronde. Jij bent de regisseur die de knop indrukt.

### Snelle variant (zonder bestanden)
Claude's kritiek direct verwerken zonder tussenbestanden:
```bash
# Claude geeft kritiek mondeling in de terminal
# Jij verwerkt die direct in de volgende pipe:
php artisan havun:pack --project=<projectnaam> | gemini \
  "Pas de blauwdruk aan, houd rekening met: [wat Claude net zei]" \
  > gemini_blueprint.md
```

---

## Voorbeelden

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

---

## Harde regels voor Claude

**⛔ STOP-lijn:** Claude begint NOOIT met coderen voordat `gemini_blueprint.md` op schijf staat en de gebruiker expliciet "ga maar" heeft getypt.

**Stateless discipline:** Claude heeft geen geheugen over sessies heen. De blueprint is het geheugen — vertrouw erop.

**Eigenaarschap van docs:** Claude schrijft de lokale docs bij, maar gebruikt daarvoor de inhoud die Gemini heeft geformuleerd.

**Één taak per pipe:** Geef Gemini nooit drie features tegelijk. Atomaire opdrachten.

**Scope: één project per sessie** — havun:pack voor project X, uitvoeren in sessie voor project X.

---

## PowerShell encoding gotcha

Als `gemini_blueprint.md` rare tekens bevat (UTF-16 probleem):
```powershell
php artisan havun:pack --project=<projectnaam> | gemini "opdracht" | Out-File -Encoding utf8 gemini_blueprint.md
```

---

## Wat we bewust NIET doen

- Claude als architect inzetten voor grote multi-file taken
- Gemini-output blind overnemen zonder lokale verificatie
- API-integratie in havun:pack bouwen — de pipe werkt zonder extra code
- Browser openen voor Gemini — de CLI elimineert dat volledig
- Key in chat plakken of in git committen

---

## Status

Geïnstalleerd: 2026-05-20
Gemini CLI versie: 0.42.0
GEMINI_API_KEY: instellen via PowerShell (key ophalen via Claude/HavunCore SSH-lookup)
Eerste echte test: SafeHavun ETH whale tracking (volgende sessie)
