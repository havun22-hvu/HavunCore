---
title: ADR 006 — Backup-architectuur (status quo + encryption parity)
type: decision
scope: alle-projecten
status: PROPOSED
date: 2026-05-03
deciders: Henk (pending)
---

# ADR 006 — Backup-architectuur

## Status

**PROPOSED** — wacht op goedkeuring Henk voordat uitvoering start.

## Context

Drie backup-systemen bestaan naast elkaar in mei 2026:

| Systeem | Locatie | Status productie |
|---------|---------|------------------|
| **Bash-scripts** (`havun-backup.sh` + `havun-hotbackup.sh` + `studieplanner-backup.sh`) | `/usr/local/bin/` | ✅ Actief in cron, push naar Hetzner Storage Box, geen encryption |
| **HavunCore Orchestrator** (`Services/BackupOrchestrator`, `Strategies/LaravelAppBackupStrategy`, `Models/BackupLog`) | `havun/core` package, `src/` | ❌ Nooit gedeployed — `havuncore.backup_logs` tabel bestaat niet, geen cron entry, KB-doc bevestigt "artisan commands nog NIET geïmplementeerd" |
| **HP `havun:backup:run`** (`App\Services\BackupService` + Command + config) | Herdenkingsportaal alleen | ⚠️ Code feature-complete (encryption + offsite + 12 tests), niet in scheduler |

**Composer-deps `havun/core`:** alleen Herdenkingsportaal en HavunAdmin requiren het. Studieplanner-api, JudoToernooi, Infosyst, SafeHavun, HavunVet doen dat niet.

**Security-issue in status quo:** Hetzner Storage Box password staat plain in `havun-backup.sh` (`HETZNER_PASS="…"`). Beperkt door file-perms 0755 root, maar wel hardcoded credential.

**Functionele gap:** bash-scripts produceren onversleutelde dumps. HP artisan-pipeline doet AES-256 + offsite, maar werkt alleen voor HP.

## Opties

### Optie 1 — Status quo + encryption-parity in bash (LEAN)

- Voeg `openssl enc -aes-256-cbc -pbkdf2` toe aan `havun-backup.sh` en `havun-hotbackup.sh` vóór de SFTP-push.
- Verplaats Hetzner-password en encryption-password naar `/etc/havun/backup.env` (chmod 600 root) — script `source`'t het.
- Verwijder Orchestrator dode code uit `havun/core` (`src/Services/BackupOrchestrator.php`, `src/Strategies/`, `src/Commands/Backup*.php`, `src/Models/Backup*.php`, migrations) na confirm dat niemand ernaar verwijst.
- HP behoudt eigen `havun:backup:run` voor extra features (granular control, scheduler-integratie, /health/backup endpoint).
- Werk: ~2u.

**Voordeel:** geen breaking changes voor 5 projecten, werkende oplossing krijgt encryption-parity, dode code weg.
**Nadeel:** twee patronen blijven (bash + HP-only artisan). Geen package-onderhoudsvoordeel.

### Optie 2 — havun/core uitbreiden + alle projecten requiren (FRAMEWORK)

- Extract HP `BackupService` + `HavunBackupRun` + config naar `havun/core` (`src/Backup/`).
- Vervang Orchestrator + Strategies door HP-pattern (project-level i.p.v. centraal).
- Studieplanner / JudoToernooi / Infosyst / SafeHavun / HavunVet `composer require havun/core` (path-repo).
- Per project: scheduler entry, env vars, eerste backup-run, cron uitschakelen voor dat project.
- Werk: ~5-7u (4 nieuwe deps + per-project deploy + tests).

**Voordeel:** één pattern, één plek voor onderhoud, full Laravel-integratie (events, /health, monitoring).
**Nadeel:** 4 projecten krijgen nieuwe shared dependency (breaking change, composer update + deploy + risico). Bash-scripts uitfaseren = oude werkende oplossing weghalen.

### Optie 3 — Hybride: HP-pattern via havun/core voor twee projecten, bash voor rest (TUSSENWEG)

- Extract HP-pattern naar `havun/core` (zoals Optie 2, maar **niet** uitrollen naar projecten zonder havun/core).
- HavunAdmin gaat van bash naar artisan-pipeline (heeft havun/core al).
- Studieplanner / JudoToernooi / Infosyst / SafeHavun blijven op bash-scripts + krijgen encryption-parity (Optie 1's bash-fix).
- Werk: ~3-4u.

**Voordeel:** twee critical projecten (HP + HavunAdmin met financial data) krijgen volledige Laravel-pipeline; rest blijft simpel.
**Nadeel:** twee patronen blijven, alleen verdeling anders dan nu. Cognitieve overhead.

## Aanbeveling

**Optie 1 (status quo + encryption-parity in bash).**

Reden:
- Bash-scripts draaien al stabiel sinds maart 2026 (na de awk/set -e fix). "Don't fix what works."
- HP's nieuwe pipeline lost één probleem op (encryption + offsite via Laravel) — dat probleem is op bash-niveau ook in 30 min op te lossen, zonder 4 projecten breaking changes te geven.
- Orchestrator-framework is dode code; weghalen levert directe codebase-besparing op.
- Past bij `feedback_just_do_it.md` en lean-mindset: minste werk, grootste security-winst (encryption + password uit script).
- HP-pipeline blijft bestaan voor wie scheduler-integratie wil — geen verlies.

**Verwerp Optie 2** want 4 projecten een nieuwe shared composer-dependency geven om iets dat al werkt anders te doen = scope-creep zonder rendement.

**Overweeg Optie 3 alleen** als HavunAdmin's bash-backup ook ooit features mist die alleen via Laravel-pipeline op te lossen zijn (geen voorbeeld nu bekend).

## Consequences

Bij goedkeuring Optie 1:
1. `/etc/havun/backup.env` aanmaken met `HETZNER_PASS=…` en `BACKUP_ENCRYPTION_PASSWORD=…` (beide chmod 600 root).
2. `havun-backup.sh` + `havun-hotbackup.sh` aanpassen: source env-file, openssl encrypt vóór gzip-output, suffix `.sql.gz.enc`.
3. Eerste run loggen + verifiëren dat decrypt-roundtrip werkt vanaf Hetzner.
4. Restore-runbook (`docs/kb/runbooks/backup.md` §"Restore van encrypted offsite-backup") al klaar voor HP-pattern; aanvullen met bash-equivalent.
5. Orchestrator code-removal in `havun/core` als aparte PR (verwijdert ~7 files + 3 migrations). Eerst grep cross-project op gebruik.
6. KB `backup-system.md` bijwerken: "artisan commands nog niet geïmplementeerd"-disclaimer wordt waar (alleen HP heeft het, rest blijft bash).

Bij verwerp / Optie 2 of 3: andere uitvoering, ander ADR-vervolg.
