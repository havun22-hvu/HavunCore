---
title: Security Overzicht
type: reference
scope: havuncore
last_check: 2026-05-02
---

# Security Overzicht

> Centrale security status voor alle Havun projecten.
> **Eisen** liggen vast in [`productie-deploy-eisen.md`](productie-deploy-eisen.md) (BINDING).

## GitHub Repositories (code)

| Repository | Status | Visibility |
|------------|--------|------------|
| HavunCore | ✅ Veilig | Private |
| HavunAdmin | ✅ Veilig | Private |
| Herdenkingsportaal | ✅ Veilig | Private |
| SafeHavun | ✅ Veilig | Private |
| Studieplanner | ✅ Veilig | Private |
| infosyst | ✅ Veilig | Private |
| HavunClub | ✅ Veilig | Private |

## Server-hardening status (per omgeving)

> **Eis**: zie `productie-deploy-eisen.md` sectie 8 — UFW actief, fail2ban,
> SSH pubkey-only, APP_DEBUG=false, SESSION_LIFETIME ≤ 120, .env perms 640.
> Status onderstaand **per server**, niet per project (één VPS host meerdere projecten).

| Server | UFW | fail2ban | SSH pubkey-only | Open ports OK | App-config OK | Laatste audit |
|--------|:---:|:--------:|:---------------:|:-------------:|:-------------:|---------------|
| `188.245.159.115` (Hetzner prod) | ✅ | ✅ | ✅ | ✅ | ✅ | 2026-05-02 |

**Actie**: server-hardening sweep 2026-05-02 afgerond — zie `security-findings.md` voor details.

## Credentials Opslag

| Locatie | Wat | Beveiliging |
|---------|-----|-------------|
| `.claude/context.md` | Server credentials, API keys | Gitignored, nooit op GitHub |
| `.env` files | Database, SMTP, API keys | Gitignored, nooit op GitHub |
| USB `credentials.vault` | Op reis: SSH keys, git, .env, context.md (geen code) | 7-Zip AES-256 encrypted |

## GitGuardian Status

- **Laatste scan:** 2025-12-25
- **Open incidents:** 25 (historisch, private repos)
- **Actie:** Accepteren - repos zijn private
- **Risico:** Laag zolang repos private blijven

## Security Maatregelen

### Credentials nooit in git
- ✅ `.gitignore` bevat `.env`, `.claude/context.md`
- ✅ Docs verwijzen naar context.md, bevatten geen echte wachtwoorden
- ✅ Cleanup uitgevoerd op 2025-12-25 (commits 88efb58, d8e0133)

### USB Beveiliging (op reis)
- ✅ `credentials.vault` - encrypted met 7-Zip AES-256 (SSH keys, git-credentials, context.md, .env, server-wachtwoorden)
- ✅ `ssh-keys.vault` - optioneel apart bestand met SSH keys (zelfde wachtwoord)
- ✅ **Geen projectcode op USB** — code via `git clone`/`git pull` op de reis-PC
- ✅ `start.bat` - unlockt vault, extraheert credentials naar juiste plekken (SSH, git, eventueel projectmap)
- ✅ Cleanup bij afsluiten - verwijdert SSH keys en git-credentials van de laptop

Zie **`docs/kb/runbooks/op-reis-workflow.md`** voor de volledige op-reis werkwijze.

### SSH Keys op USB
- SSH keys zitten in `credentials.vault` (id_*) en/of `ssh-keys.vault`
- Bij vault unlock worden ze naar `%USERPROFILE%\.ssh\` gekopieerd
- Beide vaults gebruiken hetzelfde wachtwoord
- Bij cleanup worden ze weer van de reis-PC verwijderd

### Login Systeem (SafeHavun standaard)
- ✅ PIN code (PC + smartphone)
- ✅ Biometrie/Passkeys (smartphone)
- ✅ QR code login (PC toont, smartphone scant)
- ✅ Device fingerprinting
- ✅ Rate limiting (5 pogingen/minuut)

## Deploy Keys (GitHub)

| Repository | Key naam | Fingerprint | Status |
|------------|----------|-------------|--------|
| havun22-hvu/HavunClub | server-deploy | SHA256:avC0cOwq1fLYgjl05d+i2vfAbNc6/5M01NgKxBQ7a+Y | Nodig - server heeft geen toegang via hoofdkey |

> De hoofdkey (`id_ed25519`) heeft geen toegang tot HavunClub. Deze deploy key is vereist.

## Security Incidenten

| Datum | Project | Wat | Status |
|-------|---------|-----|--------|
| 2025-11-23 | Alle | SSH key aangemaakt door Claude zonder toestemming (ADR-003) | Opgelost |
| 2026-02-18 | HavunClub | Echt wachtwoord in seeder gecommit (a4a19d0) | Wachtwoord moet gewijzigd worden |

## Mozilla Observatory — CSP Vereisten (VERPLICHT)

> Test URL: https://observatory.mozilla.org
> Elk webproject MOET deze tests halen bij elke deploy.

### CSP Test: Content Security Policy (-20 punten bij failure)

Mozilla checkt 6 afzonderlijke sub-tests. Elke FAIL = -20 punten (niet cumulatief).

#### 1. Blocks inline JavaScript (`unsafe-inline` in script-src)
- `unsafe-inline` in script-src → FAIL
- **Fix:** Gebruik nonces (`'nonce-{$nonce}'`), NOOIT `unsafe-inline`
- **Status alle projecten:** OK (geen unsafe-inline in script-src)

#### 2. Blocks eval() (`unsafe-eval` in script-src)
- `unsafe-eval` in script-src → FAIL
- **Oorzaak:** Alpine.js (gebundeld via Vite) gebruikt `new Function()` voor expressies
- **Structurele fix:** Migreer naar `@alpinejs/csp` package (geen eval nodig)
- **Tijdelijke workaround:** Accepteer -20 voor projecten met Alpine.js via Vite
- **Status:** HP + JT hebben unsafe-eval (Alpine via Vite). HA, IS, SH niet.

#### 3. Blocks inline styles (`unsafe-inline` in style-src)
- `unsafe-inline` in style-src → FAIL
- **Oorzaak:** Inline `style=""` attributen op HTML elementen
- **Structurele fix:** Refactor alle `style=""` naar CSS utility classes (Tailwind)
- **Aantallen:** HP=279, JT=182, HA=90, IS=20, SH=8 inline style attributen
- **Status:** ALLE projecten hebben unsafe-inline in style-src

#### 4. Deny by default (`default-src 'none'`)
- `default-src` is niet `'none'` → FAIL
- **Fix:** `default-src 'none'` + elk resource type expliciet toestaan
- **Status alle projecten:** OK

#### 5. Restricts `<base>` tag (`base-uri`)
- `base-uri` ontbreekt of is te breed → FAIL
- **Fix:** `base-uri 'self'`
- **Status alle projecten:** OK

#### 6. Restricts form submissions (`form-action`)
- `form-action` ontbreekt of is te breed → FAIL
- **Fix:** `form-action 'self'`
- **Status alle projecten:** OK

#### Extra checks (niet in sub-tests maar WEL in score):
- `object-src` niet op `'none'` → FAIL
- CDN domeinen zonder `https://` prefix → "overly broad"
- Brede bronnen (`https:`) in script-src → FAIL

**Verplichte CSP template voor elk project:**
```
default-src 'none'
script-src 'self' 'nonce-{$nonce}' https://specifieke-cdn.com
style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com
object-src 'none'
base-uri 'self'
form-action 'self'
frame-ancestors 'none'
manifest-src 'self'
```

**CDN domeinen altijd met `https://` prefix:**
```php
// FOUT:  cdn.jsdelivr.net
// GOED:  https://cdn.jsdelivr.net
```

### SRI Test: Subresource Integrity (-5 punten bij failure)

Mozilla scant de HTML en zoekt `integrity=` op alle `<script src="https://...">` tags.
Als er ook maar 1 extern script ZONDER integrity is → FAIL.

**Verplicht voor alle externe CDN scripts:**
```html
<script src="https://cdn.example.com/lib@1.2.3/lib.min.js"
        integrity="sha384-HASH"
        crossorigin="anonymous"
        @nonce></script>
```

**Checklist:**
- Exacte versie pinnen (NIET `@3.x.x` of ongepin)
- `integrity="sha384-..."` attribuut
- `crossorigin="anonymous"` attribuut
- `@nonce` attribuut

**Dynamische scripts (Google Analytics):**
- CDN versie kan geen SRI krijgen (Google update zonder versienummer)
- **Structurele fix:** Self-host het script + SRI (zie Herdenkingsportaal `gtag:refresh`)
- Tailwind CDN: geen SRI mogelijk, maar Tailwind CDN hoort niet in productie

### X-Content-Type-Options Test (-5 punten bij failure)

**Vereist:** Header exact `X-Content-Type-Options: nosniff` — precies 1x.

**Veelgemaakte fout:** Dubbele header door nginx EN Laravel middleware tegelijk.
- nginx `add_header X-Content-Type-Options "nosniff"` + Laravel middleware = 2x nosniff
- Mozilla leest dit als "cannot be recognized"

**Structurele regel:** Security headers staan in **Laravel middleware, NIET in nginx**.
Nginx mag alleen `Cache-Control` headers zetten op static assets.
Uitzondering: apps zonder Laravel middleware (havuncore, vpdupdate) gebruiken nginx headers.

### Openstaande verbeterpunten (structureel)

| Probleem | Projecten | Fix | Complexiteit |
|----------|-----------|-----|:------------:|
| `unsafe-eval` in script-src | HP, JT | Migreer naar `@alpinejs/csp` | HOOG |
| `unsafe-inline` in style-src | ALLE | Refactor `style=""` → CSS classes | HOOG |
| Tailwind CDN in productie | IS, SH | Bundel via Vite | MEDIUM |

### Status per Project (2026-04-14, gedeployed + geverifieerd)

| Project | default-src | unsafe-eval | unsafe-inline style | SRI | X-Content-Type |
|---------|:-----------:|:-----------:|:-------------------:|:---:|:--------------:|
| Herdenkingsportaal | 'none' | JA (Alpine) | VERWIJDERD | OK (self-hosted GA4) | OK |
| HavunAdmin | 'none' | nee | VERWIJDERD | OK | OK |
| Infosyst | 'none' | nee | VERWIJDERD | OK | OK |
| SafeHavun | 'none' | nee | VERWIJDERD | OK | OK |
| JudoToernooi | 'none' | JA (Alpine) | VERWIJDERD | OK | OK |

## Aandachtspunten

1. **Repos NOOIT public maken** - git history bevat oude credentials
2. **GitHub 2FA** - zorg dat dit aan staat
3. **SSH keys** - alleen via key auth, geen wachtwoorden

## Wekelijkse Security Audit

> Elke week uitvoeren (vraag: "doe security audit")

### Checklist

1. **GitGuardian controleren**
   - https://dashboard.gitguardian.com
   - Nieuwe incidents? → Beoordelen en oplossen
   - Status: open incidents noteren

2. **GitHub repos controleren**
   - Alle repos nog private?
   - Onbekende collaborators?
   - Recent pushed secrets?

3. **Credentials check**
   - `.env` files in .gitignore?
   - `.claude/context.md` in .gitignore?
   - Geen hardcoded credentials in code?

4. **Dependency vulnerabilities**
   - `composer audit` per project
   - `npm audit` per project
   - GitHub Dependabot alerts

### Audit Log

| Datum | Door | Resultaat | Acties |
|-------|------|-----------|--------|
| 2025-12-26 | Claude | ✅ OK | 25 historische incidents geaccepteerd, infosyst workflows verwijderd |

## Related

- [context.md](../../../.claude/context.md) - Actuele credentials
- [backup.md](../runbooks/backup.md) - Backup procedures
