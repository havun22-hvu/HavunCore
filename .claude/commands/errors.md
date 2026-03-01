# Errors Command

> Analyseer en fix console/log errors.

## Uit te voeren

### 1. Laravel logs
```bash
tail -100 storage/logs/laravel.log 2>/dev/null | grep -i "error\|exception\|fatal" | tail -20
```

### 2. NPM/Build errors
```bash
npm run build 2>&1 | grep -i "error\|failed"
```

### 4. Bij errors gevonden

**Direct fixen:**
- Identificeer root cause
- Fix de code
- Verify fix (run opnieuw)
- Clear caches indien nodig:
```bash
php artisan config:clear && php artisan cache:clear
```

### 5. Rapporteer

```markdown
### Error Check: [DATUM]
- Errors gevonden: [aantal]
- Errors gefixed: [aantal]
- Openstaand: [beschrijving of "geen"]
```
