# MD File Audit Command

> Systematische analyse van codebase: obsolete code verwijderen, code vereenvoudigen en verbeteren.

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

### 3. Dead code detectie
- Zoek ongebruikte classes/methods
- Check ongebruikte imports
- Identificeer commented-out code blocks
- Zoek duplicate code patterns

### 4. Code vereenvoudiging
- Identificeer te complexe methods (>50 regels)
- Zoek nested if/else die vereenvoudigd kan worden
- Check op hardcoded values die config moeten zijn

### 5. Obsolete code opruimen
- Verwijder ongebruikte routes
- Verwijder ongebruikte views/components
- Verwijder oude migrations die niet meer nodig zijn
- Verwijder test fixtures die niet meer gebruikt worden

### 6. Rapporteer en FIX

**Niet alleen rapporteren - direct oplossen:**
- TODO/FIXME items: los op of verwijder als niet meer relevant
- Dead code: verwijder direct
- Duplicaten: refactor naar helpers/traits
- Complexe code: vereenvoudig

### 7. Update context.md

Voeg toe aan `.claude/context.md`:
```markdown
### Laatste Audit: [DATUM]
- TODOs opgelost: [aantal]
- Dead code verwijderd: [ja/nee]
- Code vereenvoudigd: [beschrijving]
- Volgende audit: [suggestie wanneer]
```
