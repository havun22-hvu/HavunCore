---
title: Test Command
type: claude
scope: havuncore
last_check: 2026-04-22
---

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

### 3. Frontend E2E (Playwright) — ALS het project een browser-UI heeft

Bindend per `docs/kb/reference/test-quality-policy.md` §10: projecten met een eigen
UI dekken de kritieke gebruikersflows (auth, betalingen, kerntransacties) met
end-to-end browser-tests. **Pure-backend/orchestrator (HavunCore-Laravel) en native
apps zijn uitgezonderd — sla deze stap dan over.**

```bash
# Draai E2E als er een Playwright-setup is (config + @playwright/test aanwezig):
[ -f playwright.config.ts ] || [ -f playwright.config.js ] && npx playwright test || echo "geen Playwright-setup"
# Veel projecten hebben een script:
npm run test:e2e 2>/dev/null
```

- **Nog geen E2E maar wél een UI?** → dat is een gat t.o.v. §10. Meld het en gebruik de
  blauwdruk die bij de stack past:
  - SPA/PWA (React/Vue) → `docs/kb/runbooks/playwright-e2e-webapp.md` (API-mock).
  - Laravel + Blade → `docs/kb/runbooks/playwright-e2e-laravel.md` (draaiende app + test-DB).
- **E2E-failure?** VP-17: eerst oorzaak, dan fix. Nooit de assertie omdraaien.

### 4. Coverage check (optioneel)
```bash
# Laravel
php artisan test --coverage

# Node
npm run test:coverage
```

> Coverage is secundair (§7 van het beleid). Mutation-score op kritieke paden is
> de primaire metric — geen coverage-padding om een getal te halen.

### 5. Rapporteer

```markdown
### Test Run: [DATUM]
- Backend: [passed]/[total]
- E2E (Playwright): [passed]/[total] | n.v.t. (geen UI) | ONTBREEKT (gat §10)
- Failures gefixed: [aantal]
- Coverage: [percentage]%
```
