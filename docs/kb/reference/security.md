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
| USB `credentials.vault` | Backup van bovenstaande | 7-Zip AES-256 encrypted |

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

### USB Beveiliging
- ✅ `credentials.vault` - encrypted met 7-Zip AES-256
- ✅ `START.bat` - unlockt vault bij sessie start
- ✅ `STOP.bat` - lockt vault en verwijdert plaintext credentials
- ✅ `.claude/context.md` files in vault opgenomen

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
