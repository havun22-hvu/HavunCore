# MD File Audit Command

> **Doel:** Automatische audit van alle MD bestanden in alle Havun projecten

## Wat dit commando doet

1. Indexeert alle MD files van alle projecten in de database
2. Detecteert issues: duplicaten, broken links, verouderde docs, inconsistenties
3. Toont een samenvatting per project

## Uitvoering

### Stap 1: Run de audit

```bash
cd D:\GitHub\HavunCore
php artisan docs:detect --index
```

### Stap 2: Toon resultaten aan gebruiker

```bash
php artisan docs:issues --summary
```

Als `--summary` niet bestaat, gebruik:

```bash
php artisan docs:issues 2>&1 | head -100
```

### Stap 3: Rapporteer aan gebruiker

Geef een overzicht in dit format:

```
📊 MD File Audit - [datum]
═══════════════════════════════════════

Gescand: X projecten, Y MD bestanden

SAMENVATTING
────────────
🔴 HIGH:   X issues
🟡 MEDIUM: X issues
🟢 LOW:    X issues

TOP ISSUES (alleen HIGH + MEDIUM tonen)
───────────────────────────────────────
[Per project: issue type + korte beschrijving]

AANBEVOLEN ACTIES
─────────────────
1. [Eerste prioriteit]
2. [Tweede prioriteit]
...
```

### Stap 4: Bij fixes

| Type | Actie |
|------|-------|
| Broken links | Direct fixen |
| Duplicaten | Overleggen welke te behouden |
| Verouderde docs | Controleren of nog relevant |
| Inconsistenties | Overleggen welke waarde correct is |

### Stap 5: Na fixes

```bash
# Herindexeer om opgeloste issues te verwijderen
php artisan docs:detect --index
```

## Handmatige checks (optioneel)

Zie `docs/kb/runbooks/md-file-audit.md` voor:
- CLAUDE.md format standaard
- context.md format standaard
- Checklist per project
