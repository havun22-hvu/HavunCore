# Decision: AutoFix Hardening — Kennis-drift & Validatie

> **Datum:** 15 maart 2026
> **Aanleiding:** Externe audit door Gemini AI (als gesimuleerde klant)
> **Status:** Geïmplementeerd

## Context

Gemini simuleerde een potentiële klant die kritische vragen stelde over de HavunCore-methodiek. Twee zwakke punten werden geïdentificeerd:

1. **Kennis-drift** — AutoFix wijzigde code op de server zonder git commit/push. De lokale ontwikkelomgeving en KB liepen uit sync met productie.
2. **Post-fix validatie ontbrak** — Na het toepassen van een fix werd niet gevalideerd of de code syntactisch correct was. Een kapotte fix kon alle gebruikers treffen.

## Gemini's Vragen (en onze eerlijke antwoorden)

| Vraag | Beoordeling | Actie |
|-------|-------------|-------|
| Doc-First & code-standaarden | Goed geregeld (CLAUDE.md + KB) | Geen actie nodig |
| AutoFix guardrails | Basis aanwezig, syntax check ontbrak | **Opgelost** |
| Onafhankelijkheid / vendor lock-in | Geen lock-in (sterkste punt) | Geen actie nodig |
| Kennis-drift | Bekende zwakte, handmatige sync | **Opgelost** |

## Wat is geïmplementeerd

### 1. PHP Syntax Check + Auto-rollback

**Waar:** `AutoFixService.applyFix()` + `AutoFixController.applyFix()`
**Projecten:** JudoToernooi, Herdenkingsportaal

Na `file_put_contents()` wordt `php -l` uitgevoerd op PHP bestanden. Bij een syntax error:
- Backup wordt automatisch teruggezet
- OPCache wordt geïnvalideerd
- Fix wordt als `failed` gemarkeerd met duidelijke error

### 2. Git Commit + Push na succesvolle fix

**Waar:** `AutoFixService.gitCommitAndPush()` (public method)
**Projecten:** JudoToernooi, Herdenkingsportaal

Na een succesvolle fix (inclusief syntax check):
- `git add {file}` + `git commit -m "autofix: ..."` + `git push`
- Bij git failure: log warning, fix blijft actief (niet teruggedraaid)
- Controller (`approve()`) hergebruikt dezelfde method via DI

### 3. Auto Pull bij sessie start

**Waar:** `.claude/commands/start.md` — nieuw Stap 0
**Project:** HavunCore (geldt voor alle projecten)

Bij elke `/start` wordt automatisch `git pull` uitgevoerd om AutoFix-wijzigingen te synchroniseren.

### 4. Controller hardening

**Waar:** `AutoFixController.applyFix()` (JudoToernooi)

- 24h rollback check toegevoegd (ontbrak, was alleen in Service)

## Wat bewust NIET is geïmplementeerd

| Voorstel | Reden afwijzing |
|----------|-----------------|
| Docker containers | Overkill — 1 server, vaste poorten, alles werkt |
| API-gateway | Al opgelost met simpele HTTPS API calls |
| SSH-tunnels | Bestonden niet — Gemini verzon dit probleem |
| Volledige test suite na fix | Te zwaar voor exception handler in productie |
| Automatische KB updates door AI | Risico op "hallucinerende documentatie" |

### 5. Gestructureerde commit messages

**Waar:** `AutoFixService.gitCommitAndPush()` (JudoToernooi + Herdenkingsportaal)

Commit messages zijn geoptimaliseerd voor leesbaarheid door DocIndexer en `git log`:

```
autofix(FileName): Claude's analysis summary (max 72 chars)

File: app/Services/MyService.php
Exception: ErrorException
Risk: low
Proposal: #42
```

- ANALYSIS regel wordt automatisch geëxtraheerd uit Claude's response
- RISK level wordt meegenomen
- Proposal ID linkt terug naar de database

### Commit Format: Onze keuze vs Gemini's voorstel

**Gemini stelde voor:** `autofix: {error_message} in {file_name} [Ref: {db_id}]`

**Onze implementatie (gekozen):**

```
autofix(FileName): Claude's analysis summary (max 72 chars)

File: app/Http/Controllers/BlokController.php
Exception: ErrorException
Risk: low
Proposal: #42
```

**Waarom ons format beter is:**

| Aspect | Gemini | Ons |
|--------|--------|-----|
| Scope in titel | Nee | Ja — `(FileName)` filterable met `git log --grep` |
| Beschrijving | Ruwe error message | Claude's analyse (root cause, niet symptoom) |
| Structuur | Alles op 1 regel | Multi-line met metadata op aparte regels |
| Risk level | Ontbreekt | Aanwezig |
| DocIndexer parsing | Moeilijk (1 regel, vrij format) | Makkelijk (key: value pairs) |

## Resterende verbeterpunten (toekomst)

- `isProjectFile()` deduplicatie (Service + Controller)
- Admin dashboard: notify_only stats toevoegen
- Herdenkingsportaal: `excluded_message_patterns` toevoegen in config

## Flow na wijzigingen

```
Production Error
  → AutoFixService::handle()
  → shouldProcess() checks
  → askClaude() → FIX response
  → applyFix():
    1. Parse FILE/OLD/NEW
    2. Backup maken
    3. str_replace uitvoeren
    4. php -l syntax check ← NIEUW
       └─ FAIL → rollback + return error
    5. Clear caches
  → Succes:
    1. DB: status=applied
    2. Email: success notification
    3. git add + commit + push ← NIEUW
  → Lokale sessie start:
    1. git pull ← NIEUW
    2. Code in sync met server
```

---

*Dit document is het resultaat van een externe audit en dient als referentie voor toekomstige evaluaties.*
