---
title: Webapp chat-feature verwijderd
type: decision
date: 2026-05-08
status: accepted
project: havuncore-webapp
---

# ADR — havuncore-webapp chat-feature verwijderd

## Status

Geaccepteerd, geïmplementeerd op 2026-05-08 in branch `chore/remove-chat-feature` (4 commits, 1 PR).

## Context

De `havuncore-webapp` was opgezet als **mobile-first voice + type dashboard** met chat-interface, RAG-pijplijn, hybride AI (Ollama lokaal + Claude API) en een Code Agent met Approve/Reject flow. Alles zat geïntegreerd in één PWA op `havuncore.havun.nl`.

Sinds Henk **Claude Code CLI in VS Code** als primaire interface gebruikt voor alle interacties (lokaal én remote control op productie), is de chat-flow van de webapp dood gewicht geworden:

- Claude Code CLI heeft volle projectcontext (alle files), niet alleen RAG-snippets
- CLI heeft direct Bash, git, SSH en deploy in dezelfde flow
- CLI heeft persistente memory cross-session
- CLI gebruikt dezelfde Ollama/embeddings via `php artisan docs:search` op HavunCore Laravel

De webapp PWA blijft alleen nuttig als **mobile status-dashboard** voor onderweg: server-health, project-overzicht, Tasks, KB-statistieken.

## Beslissing

Verwijder uit `havuncore-webapp`:

- **Frontend:** ChatInterface (+ Code Agent UI: FileExplorer, FileViewer), MessagesView, KBSearchView, SettingsView, VoiceSettings, EmergencyConfirm, HelpView; voice hooks (`useSpeechSynthesis`, `whisperApi`); operation-mode lib + isLocalMode helper.
- **Backend routes:** `aiConfig.js`, `chat.js`, `orchestrate.js`, `files.js`, `vault.js` (op productie route Nginx `/api/vault` naar Laravel; webapp-handler was dood). `ai.js` gestript tot proxy van `/api/ai/kb-stats` → Laravel `/api/docs/stats`. `projects.js` zonder `ssh-status`/`ssh-toggle`. **Follow-up 2026-05-09:** `ai.js` is volledig verwijderd — Laravel's `/api/docs/stats` zit achter een Bearer-token; opnieuw vault-token-management opzetten voor een nicety-statistiek was niet de moeite waard. KB-info via remote control (toekomstplan) of SSH wanneer écht nodig.
- **Backend services:** aiOrchestrator, claudeWithTools, ragService, ollamaService, sshService, tunnelService, projectDelegation, polling, havuncore (CLI wrapper).
- **DB-tabellen:** `chat_history`, `pending_chat_tasks`, `code_proposals`, `ai_sessions`, `ai_config`, `orchestrations`, `tasks`, `command_history` (idempotent dropped via `runMigrations`).
- **Env-vars:** `HAVUNCORE_PATH`, `PHP_PATH`, `OLLAMA_*`, `RAG_*`, `ALLOW_SSH_REMOTE`, `SSH_*`, `AUTOFIX_EMAIL`.
- **Dependencies:** `ssh2`. (`@anthropic-ai/sdk` blijft — AutoFix gebruikt het.)
- **SSH reverse tunnel** (Hetzner → lokale PC:8009) — was alleen voor het proxyen van AI calls.

Behouden:

- Status / Projects / Tasks / Vault views in frontend.
- Auth-flow (biometric, QR, magic-link, wachtwoord, step-up) volledig.
- PWA infra (service worker, install prompt, update prompt — die laatste vooraf gefixt naar `prompt`-based update, voorheen conflicteerde `autoUpdate` met UpdatePrompt).
- AutoFix Node.js — **mail-pad eruit**, registreert outcome in `autofix_proposals` + console; consistent met JudoToernooi/Herdenkingsportaal "AutoFix mailt nooit" policy (zie memory `feedback_no_autofix_email.md`).
- `routes/workspace.js` — DO NOT REMOVE per oude project-CLAUDE.md.
- `services/projectStatusService.js` — voedt Projects/Status (SSH remote-head check eruit; productie *is* de remote, geen apart sync-doel).

## Gevolgen

**Bundle size:** frontend precache van ~727 KB → ~491 KB (-32 %).

**Maintenance overhead:**
- Eén AI-codepath verdwijnt (Anthropic SDK direct in Node.js); HavunCore Laravel blijft enige Anthropic-tenant via `/api/ai/chat`.
- Eén dependency (`ssh2`) + 4 transitive packages weg.
- Geen lokale Ollama meer nodig op de Hetzner server (was overigens niet beschikbaar — webapp deed AI calls via tunnel naar Henks PC; tunnel is nu ook weg).

**Mobile gebruik:**
- Henk gebruikt webapp PWA alleen nog voor statuscheck. Bij issue start hij Claude Code CLI op zijn PC.
- Thiemo/Mawin (noodcontacten) volgen het bestaande pad: WhatsApp-link → Henk start CLI.

**Risico's & mitigaties:**
- *Smartphone PWA blijft op oude bundle hangen* → vooraf de service-worker update flow gefixt naar `registerType: 'prompt'` (commit `eb50d30`); UpdatePrompt-banner toont na deploy.
- *Verloren chat-historie* → bewust geaccepteerd; data was niet productie-kritiek en gebruiker had de chat al niet meer in gebruik.
- *AutoFix push-notificatie* → push-infra (`routes/push.js`) is nu een no-op stub. AutoFix-meldingen surface'n via `autofix_proposals` tabel en PM2 logs. Werkende push als latere feature; consistent met andere AutoFix-tenants die ook geen actieve push hebben.

## Implementatie

Branch: `chore/remove-chat-feature`. Vier commits in volgorde:

1. `refactor(webapp): remove chat frontend components`
2. `refactor(webapp): remove chat backend routes`
3. `refactor(webapp): drop chat services, DB tables, env vars + slim AutoFix`
4. `docs(webapp): chat removal docs + ADR + memory updates`

Pre-fix op `main`: `fix(pwa): switch service worker to prompt-based updates` (commit `eb50d30`).

## Productie deploy stappen

1. Stop `pm2 stop havuncore-backend` op Hetzner
2. `git pull` op `/var/www/havuncore/webapp`
3. `npm install --production` (verwijdert ssh2)
4. `.env.production` opschonen — env vars per scope verwijderen, `HAVUNCORE_API_URL=https://havuncore.havun.nl` toevoegen indien nog niet aanwezig
5. Migrations draaien automatisch bij PM2 start (drop-tables zijn idempotent)
6. Frontend lokaal builden, `dist/*` naar `/var/www/havuncore/webapp/public/` uploaden
7. Nginx config updaten — chat-routes uit Node.js-block halen (zie `webapp/docs/INFRASTRUCTURE.md` routing tabel)
8. `nginx -t && systemctl reload nginx`
9. SSH reverse tunnel script + eventuele systemd unit verwijderen op lokale PC + server
10. `pm2 start havuncore-backend`

Verificatie na deploy: smartphone PWA toont "Update beschikbaar" → klik → nieuwe bundle laadt → Status/Projects/Tasks werken zonder 404's.

## Referenties

- Scope-document: `havuncore-webapp/docs/CHAT-REMOVAL-PLAN.md`
- Execution plan: `havuncore-webapp/docs/CHAT-REMOVAL-EXECUTION.md`
- AutoFix policy: memory `feedback_no_autofix_email.md`
- PWA fix referentie: `webapp/docs/INFRASTRUCTURE.md` Service Worker / PWA sectie
