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

## AI Werkwijze — Gemini + Claude + Dynamic Workflows
Zie `docs/kb/runbooks/gemini-claude-workflow.md` voor de volledige pipeline.
- **Gemini** = architect, brainstorm, blauwdrukken (groot contextvenster + tweede mening)
- **Claude normaal** = kleine fixes, directe uitvoering (< 5 bestanden, afgebakend)
- **Claude dynamic workflow** = grote taken — roept Gemini aan, implementeert parallel, test, commit — alles automatisch
- Starten: gewoon de opdracht typen (ultracode mode aan) — Claude beslist zelf of een workflow nodig is

## Tests
```bash
php artisan test --no-coverage
```

## Verboden zonder overleg
SSH keys, credentials, `.env`, composer/npm installs, prod migrations, systemd/cron.

## MD-docs schrijven — hou ze leesbaar voor Claude

Een te lang doc wordt niet gelezen: het verdringt andere docs uit de context, en de KB indexeert
alleen het **begin** van een bestand (~2000-8000 tekens) — alles daarna is onvindbaar via `docs:search`.

- **Max:** KB-doc/runbook 200 regels · CLAUDE.md 120 · plan/blueprint 300 · handover 15-30 regels per sessie
- **Hiërarchie:** conclusie + status bovenaan, tabel in het midden, onderbouwing onderaan
- **Te groot?** Splitsen in index + deeldocs. Niet persen tot telegramstijl — onleesbaar is niet kort
- **Handover:** er is er **één** en die werk je **bij** — nooit een sessieblok toevoegen.
  Afgeronde taken eruit, nieuwe erbij. Levende status, geen logboek (git bewaart de historie)

Volledig: `HavunCore/docs/kb/standards/md-doc-grootte.md`
