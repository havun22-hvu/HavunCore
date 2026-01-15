# MD File Audit Command

> Scan dit project op code kwaliteit issues.

## Uit te voeren

### 1. TODO/FIXME/HACK scan
```bash
grep -rn "TODO\|FIXME\|HACK" --include="*.php" --include="*.js" --include="*.vue" --include="*.ts" . 2>/dev/null | head -30
```

### 2. Outdated dependencies
```bash
# Als composer.json bestaat
composer show --outdated 2>/dev/null | head -20

# Als package.json bestaat  
npm outdated 2>/dev/null | head -20
```

### 3. Rapporteer bevindingen

Geef een korte samenvatting:
- Aantal TODO/FIXME/HACK gevonden
- Aantal outdated packages
- Aanbevelingen

### 4. Update context.md

Voeg toe aan `.claude/context.md`:
```markdown
### Laatste Audit: [DATUM]
- TODOs: [aantal]
- Outdated deps: [aantal]
- Actie nodig: [ja/nee]
```
