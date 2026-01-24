# Lint Command

> Code quality en style checks.

## Uit te voeren

### 1. PHP (Laravel Pint)
```bash
./vendor/bin/pint --test 2>/dev/null
```

### 2. PHP Static Analysis
```bash
./vendor/bin/phpstan analyse 2>/dev/null | head -30
```

### 3. JavaScript/TypeScript
```bash
npm run lint 2>/dev/null
```

### 4. Bij issues

**Direct fixen:**
```bash
# Auto-fix PHP style
./vendor/bin/pint

# Auto-fix JS/TS
npm run lint:fix
```

### 5. Rapporteer

```markdown
### Lint Check: [DATUM]
- PHP style issues: [aantal] (auto-fixed)
- PHPStan errors: [aantal]
- JS/TS issues: [aantal]
```
