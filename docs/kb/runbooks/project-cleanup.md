# Project Cleanup Runbook

> Periodieke audit om code schoon te houden.

## Wanneer uitvoeren

- Bij grote wijzigingen
- Minimaal 1x per maand per actief project
- Via `/audit` commando in elk project

## Checklist

### 1. Dead Code
```bash
# Ongebruikte PHP classes (Laravel)
php artisan route:list | grep -v "Closure"

# Ongebruikte imports (handmatig of IDE)
```

### 2. TODO/FIXME/HACK
```bash
grep -rn "TODO\|FIXME\|HACK" --include="*.php" --include="*.js" --include="*.vue" .
```

### 3. Duplicaten
- Zoek naar copy-paste code
- Refactor naar helpers/traits

### 4. Obsolete Dependencies
```bash
# Laravel
composer show --outdated

# NPM
npm outdated
```

### 5. Ongebruikte Assets
- Images in public/ die nergens gebruikt worden
- CSS/JS die niet meer geladen wordt

### 6. Database
- Ongebruikte tabellen/kolommen
- Missende indexes

## Na Cleanup

Update `.claude/context.md`:
```markdown
### Laatste Cleanup: [DATUM]
- [x] Dead code verwijderd
- [x] TODOs opgelost of gedocumenteerd
- [x] Dependencies bijgewerkt
```
