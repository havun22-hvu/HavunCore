---
title: Mail-credentials uit plain .env naar Vault
type: runbook
scope: havuncore
last_check: 2026-04-28
status: TODO — migratie-plan, nog niet uitgevoerd
---

# Mail-credentials uit plain `.env` naar Vault

> **Probleem:** SMTP-passwords staan in plain text in `.env`-bestanden op productie.
> **Risico:** server-compromise → directe lekkage van mail-API-keys + verzendcapaciteit (relais-spam, brand-damage).
> **Doel:** centraal beheerd vault-pattern (consistent met andere Havun-keys), zelfde model als Groq/Anthropic-keys.

## Audit (28-04-2026)

| Project | Mailer | SMTP-key in plain `.env` |
|---------|--------|--------------------------|
| Herdenkingsportaal staging | Brevo SMTP | ✗ — 91 bytes plain |
| Herdenkingsportaal production | Brevo SMTP | ✗ — 91 bytes plain |
| Studieplanner production | Brevo SMTP | ✗ — 91 bytes plain |
| HavunCore production | SendGrid | ✗ plain |
| HavunAdmin production | smtp.gmail.com | ✗ — 22 bytes plain |
| JudoToernooi `/laravel` | log (maar legacy SMTP key staat er nog) | ✗ — duplicate MAIL_PASSWORD-line, 96 bytes legacy |
| HavunVet, HavunClub, Infosyst, JudoToernooi staging, SafeHavun | log of leeg | — geen risico |

**Bonus risico:** HP staging + production gebruiken **dezelfde** Brevo SMTP-key (zelfde lengte, zelfde prefix `xsmtpsib-114de5be77b13d1f8c9f6bcb01526fab3b76b2c4153c928fe0e1b1f6ee6293ec-...`). Compromise van staging = compromise van production-mail.

## Quick wins (laag risico, kan eerst)

### 1. JudoToernooi legacy SMTP-key opruimen

`/var/www/judotoernooi/laravel/.env` heeft 2 `MAIL_PASSWORD`-regels:

```
54:MAIL_PASSWORD=null
103:MAIL_PASSWORD=xsmtpsib-...   ← legacy Brevo secret, niet gebruikt (MAIL_MAILER=log)
```

Verwijder regel 103 — JT gebruikt mail-log dus de SMTP-key is dood gewicht maar wel exposed.

```bash
ssh root@188.245.159.115 "sed -i '/^MAIL_PASSWORD=xsmtpsib-/d' /var/www/judotoernooi/laravel/.env"
```

### 2. HP staging + productie SMTP-keys ontkoppelen

Genereer een **aparte Brevo subaccount** of API-key voor staging (Brevo-portal → SMTP & API → "+ Generate new key"). Vervang staging `.env` met de nieuwe key. Production-key blijft alleen op production.

### 3. Brevo + SendGrid quota's monitoren

Magic-link login eet quota; AutoFix-mails zijn al uit (zie `feedback_no_autofix_email.md`). Maandelijks checken via Brevo-dashboard.

## Hoofdmigratie — Vault-pattern (consistent met Havun-aanpak)

### Doel

`.env` op server heeft **alleen** een verwijzing of een symbolisch aliasje. De daadwerkelijke geheime waarde zit in een centrale Vault (Bitwarden, HashiCorp Vault, of HavunCore's eigen Vault-pattern).

### Optie A — Laravel `env:encrypt` (snelst, lokaal beheerbaar)

```bash
# Op productie:
cd /var/www/herdenkingsportaal/production
php artisan env:encrypt --key=<32-char-key>
# Genereert .env.encrypted; .env zelf kan weg uit git én van disk
# Bij deploy: php artisan env:decrypt --key=<key> --env=production
```

**Voor:** out-of-the-box Laravel feature, geen externe dependency.
**Tegen:** decryptie-key moet ergens (env var bij webserver-start, of secret manager). Verschuift het probleem.

### Optie B — HavunCore Vault (consistent met bestaande secrets)

HavunCore heeft al een vault-pattern voor andere API-keys (Groq, Anthropic, etc — zie `.claude/credentials.md`). Brengt mail-secrets onder hetzelfde dak:

1. Mail-key in HavunCore Vault, met logical naam `mail.brevo.herdenkingsportaal.production`
2. Deploy-script (`docs/kb/runbooks/deploy.md` per project) haalt key op en zet hem in runtime-env vóór `php artisan` start
3. `.env` bevat alleen `MAIL_PASSWORD=${BREVO_PROD_KEY}` (placeholder), of helemaal niet meer dat veld
4. Server SystemD/PM2 unit injecteert env-var bij service-start

**Voor:** centraal, audit-trail, key-rotatie via 1 plek, consistent met andere Havun-secrets.
**Tegen:** initiële setup-tijd, deploy-script wijziging per project.

### Optie C — Bitwarden CLI bij deploy

```bash
# Bij deploy:
export MAIL_PASSWORD=$(bw get password "Brevo HP Production")
php artisan ...
```

**Voor:** geen extra infra, Bitwarden bestaat al.
**Tegen:** Bitwarden-session-token moet op server (als die expired = deploy faalt), niet ideaal voor onbemande deploys.

## Aanbevolen volgorde

1. **Week 1** — quick wins 1+2+3 doen (low risk, immediate value)
2. **Week 2** — kies tussen optie A / B / C in overleg met Henk; pilot op 1 project (Studieplanner is het simpelst — alleen productie, 1 key)
3. **Week 3** — rollout HP staging + production, dan rest
4. **Continu** — quartaal-rotatie van alle SMTP-keys (vault-pattern maakt dit triviaal)

## Vóór elke verandering

- [ ] Backup van `.env` op server (`cp .env .env.bak.YYYYMMDD`)
- [ ] Test op staging eerst — verstuur een magic-link na wijziging
- [ ] Rollback-procedure klaar (terug naar `.env.bak`)
- [ ] Henk's go-akkoord per project

## Niet doen

- ❌ `.env` in git (al beschermd door `.gitignore`, niet ondermijnen)
- ❌ Mail-key in front-end build (`.env.production` met VITE_/NEXT_PUBLIC_ — exposes via JS-bundle)
- ❌ Key roteren zonder downstream waarschuwen (oude versie blijft werken tot decommission)

## Bron

- Audit 2026-04-28 — `runbooks/mail-credentials-vault.md`
- Memory: `feedback_no_autofix_email.md` (mail-quota voor magic-link beschermen)
- Auth-standaard v5.1 (mail = primair voor magic-link login)
