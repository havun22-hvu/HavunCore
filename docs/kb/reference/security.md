# Security Overzicht

> Centrale security status voor alle Havun projecten

## GitHub Repositories

| Repository | Status | Visibility |
|------------|--------|------------|
| HavunCore | ✅ Veilig | Private |
| HavunAdmin | ✅ Veilig | Private |
| Herdenkingsportaal | ✅ Veilig | Private |
| SafeHavun | ✅ Veilig | Private |
| Studieplanner | ✅ Veilig | Private |
| infosyst | ✅ Veilig | Private |
| HavunClub | ✅ Veilig | Private |

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

**Wat Mozilla checkt:**
- `unsafe-inline` of `data:` in `script-src` → FAIL
- Brede bronnen zoals `https:` in `object-src` of `script-src` → FAIL
- `object-src` of `script-src` niet gezet → FAIL
- CDN domeinen zonder `https://` prefix → FAIL (telt als "overly broad")
- `unsafe-eval` in `script-src` (tenzij route-specifiek) → FAIL

**Verplichte CSP regels voor elk project:**
```
default-src 'none'                          # Deny by default
script-src 'self' 'nonce-{$nonce}'         # Alleen nonce, NOOIT unsafe-inline
style-src 'self' 'nonce-{$nonce}' 'unsafe-inline'  # unsafe-inline alleen voor style="" attributen
object-src 'none'                           # Blokkeer plugins
base-uri 'self'                             # Voorkom base tag injection
form-action 'self'                          # Formulieren alleen naar eigen domein
frame-ancestors 'none'                      # Geen iframes
manifest-src 'self'                         # PWA manifest
```

**CDN domeinen altijd met `https://` prefix:**
```php
// FOUT:  cdn.jsdelivr.net
// GOED:  https://cdn.jsdelivr.net
```

**`unsafe-eval` alleen route-specifiek:**
```php
// FOUT:  altijd 'unsafe-eval' in script-src
// GOED:  alleen op routes die het echt nodig hebben (bijv. Fabric.js)
$unsafeEval = $this->routeNeedsUnsafeEval($request) ? " 'unsafe-eval'" : '';
```

### SRI Test: Subresource Integrity (-5 punten bij failure)

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
- Dynamische scripts (Google Analytics, Tailwind CDN): alleen `@nonce`, geen SRI

### X-Content-Type-Options Test (-5 punten bij failure)

**Vereist:** Header exact `X-Content-Type-Options: nosniff` — precies 1x.

**Veelgemaakte fout:** Dubbele header door nginx EN Laravel middleware tegelijk.
- nginx `add_header X-Content-Type-Options "nosniff"` + Laravel `$response->headers->set(...)` = 2x nosniff
- Mozilla leest dit als "cannot be recognized"

**Structurele regel:** Security headers staan in **Laravel middleware, NIET in nginx**.
Nginx mag alleen `Cache-Control` headers zetten op static assets.
Uitzondering: apps zonder Laravel middleware (havuncore, vpdupdate) gebruiken nginx headers.

### SRI Test: Extra (-5 punten bij ontbrekende SRI)

Mozilla scant de HTML response en zoekt `integrity=` op alle `<script src="https://...">` tags.
Als er ook maar 1 externe script ZONDER integrity is → FAIL.

**Let op Google Analytics:** `<script async src="https://www.googletagmanager.com/...">` kan geen SRI krijgen (dynamisch). Mozilla telt dit WEL mee als extern script. Enige oplossing: GA via server-side of accepteer de -5.

### Status per Project (2026-04-13, gedeployed)

| Project | default-src | script-src | object-src | SRI | X-Content-Type | Status |
|---------|:-----------:|:----------:|:----------:|:---:|:--------------:|:------:|
| Herdenkingsportaal | 'none' | nonce-only | 'none' | OK* | 1x nosniff | OK |
| HavunAdmin | 'none' | nonce-only | 'none' | OK | 1x nosniff | OK |
| Infosyst | 'none' | nonce-only | 'none' | OK | 1x nosniff | OK |
| SafeHavun | 'none' | nonce-only | 'none' | OK | 1x nosniff | OK |
| JudoToernooi | 'none' | unsafe-eval** | 'none' | OK | 1x nosniff | Deels |

\* GA4 script kan geen SRI krijgen (dynamisch)
\** Alpine.js via Vite vereist unsafe-eval — migratie naar @alpinejs/csp nodig

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

- [context.md](/.claude/context.md) - Actuele credentials
- [backup.md](/docs/kb/runbooks/backup.md) - Backup procedures
