# Test Command

> Run tests en fix failures direct.

## Uit te voeren

### 1. Detecteer test framework
```bash
# Laravel/PHP
php artisan test 2>/dev/null || ./vendor/bin/phpunit 2>/dev/null

# Node.js
npm test 2>/dev/null
```

### 2. Bij failures

**Direct fixen:**
- Lees de error message
- Zoek de failing test
- Fix de code OF de test (als test outdated is)
- Run opnieuw tot groen

### 3. Coverage check (optioneel)
```bash
# Laravel
php artisan test --coverage

# Node
npm run test:coverage
```

### 4. Rapporteer

```markdown
### Test Run: [DATUM]
- Tests: [passed]/[total]
- Failures gefixed: [aantal]
- Coverage: [percentage]%
```
