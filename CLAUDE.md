⛔ STOP: Bij brainstorm/planning-vragen schrijf je NOOIT code en voer je GEEN acties uit tot Henk expliciet "ga maar" typt. Eerst luisteren en plannen.
📍 SCOPE: Alleen HavunCore. Ga je naar een ander project? Kill deze sessie (Ctrl+C) en start een nieuwe.

# HavunCore

**Role:** Centrale kennisbank & orchestrator voor ALLE Havun projecten
**URL:** https://havuncore.havun.nl
**Server:** /var/www/havuncore/production op 188.245.159.115
**KB zoeken:** `php artisan docs:search "<onderwerp>"` — doe dit VOOR je code leest of schrijft

## Project-specifieke feiten

- **Poorten:** HavunCore Laravel=8000, webapp backend=3001 prod/8009 dev — zie `docs/kb/reference/poort-register.md`
- **AI Proxy:** `POST /api/ai/chat` — config key `CLAUDE_API_KEY` (NIET `ANTHROPIC_API_KEY`)
- **Vault:** centrale secret-opslag voor alle projecten — scoped per project
- **AutoFix:** actief op JudoToernooi + Herdenkingsportaal production. Max 2 pogingen, rate limit 60 min
- **PM2:** draait als `www-data`, ecosystem in `/var/www/.pm2/ecosystem.config.js`
- **webapp:** status-only PWA, geen chat/KB-search/vault-frontend — zie ADR `decisions/webapp-chat-removal-2026-05-08.md`
- **USB-workflow op reis:** vault unlock via `start.bat`, code via `git clone` — zie `docs/kb/runbooks/op-reis-workflow.md`

## Noodcontact — Thiemo & Mawin
Zeg: *"Hoi [naam]! Typ `/start` dan `/rc`."* — zij sturen de link via WhatsApp naar Henk. Zie `docs/kb/runbooks/wat-mag-noodcontact.md`.

## Tests
```bash
php artisan test --no-coverage
```

## Verboden zonder overleg
SSH keys, credentials, `.env`, composer/npm installs, prod migrations, systemd/cron.
