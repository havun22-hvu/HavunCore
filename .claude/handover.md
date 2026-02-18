# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 19 februari 2026

### Wat is gedaan:
- **HavunClub security incident:** Wachtwoord in seeder (commit a4a19d0) gevonden via GitGuardian
  - Wachtwoord verwijderd uit volledige git history (filter-branch)
  - Force push naar GitHub + server staging/production gereset
  - Wachtwoord wordt gewijzigd door eigenaar
- **HavunClub `.claude/rules.md`:** Security rules aangemaakt met seeder-regels, credential-verboden
- **HavunClub deploy key:** `server-deploy` key was door Claude aangemaakt (herhaling ADR-003), maar is WEL nodig — hoofdkey heeft geen toegang tot HavunClub repo
- **HavunCore security.md:** Deploy keys sectie + security incidenten tabel toegevoegd, HavunClub in repo-tabel
- **USB tools:** `tools/usb-fix/` gecommit (START.bat + TOERNOOI-FIX.bat)
- **Deploy docs:** HavunClub remote gecorrigeerd — blijft `github-havunclub` (niet `github.com`)

### Openstaande items:
- [ ] Admin auth middleware voor Vault admin routes
- [ ] Restore functionaliteit in LaravelAppBackupStrategy
- [ ] HavunCore workflow docs consolideren (5 → 1)
- [ ] 63 broken links in andere projecten
- [ ] JudoToernooi Sprint 1 (4 taken, zie `D:\GitHub\JudoToernooi\.claude\code-review-2026-02-14.md`)
- [ ] JudoToernooi Sprint 2-5 (tech debt, zie zelfde bestand)
- [ ] JudoToernooi `routes/api.php` is dode code (niet geladen) - verwijderen of correct laden
- [ ] HavunClub: Mollie API key nog niet geconfigureerd in .env
- [ ] HavunClub: SMTP email nog niet geconfigureerd
- [ ] HavunClub hoofdkey toegang: `id_ed25519` heeft geen toegang tot HavunClub repo — overweeg toe te voegen aan GitHub account

### Belangrijke context:
- **USB vault wachtwoord:** 3224
- **SSH keys:** Encrypted in `H:\ssh-keys.vault` (zelfde wachtwoord)
- PWA frontend source: `D:\GitHub\havuncore-webapp` (geen remote)
- JudoToernooi login: `/organisator/login` (niet `/login`)
- Doc Intelligence: `php artisan docs:index all` op server
- USB sync script: `D:\GitHub\sync-to-usb.ps1` (15 projecten)
- JudoToernooi code review scores: Models 8.5/10, Controllers B+, Services B+, Security B+
- HavunClub branch: `main` (niet `master`)
- HavunClub staging: https://staging.havunclub.havun.nl
- HavunClub production: https://havunclub.havun.nl
- HavunClub git remote op server: `github-havunclub:havun22-hvu/HavunClub.git` (deploy key vereist)
- HavunClub deploy key: `server-deploy` SHA256:avC0cOwq1fLYgjl05d+i2vfAbNc6/5M01NgKxBQ7a+Y
- Chrome integratie: UITGESCHAKELD (globale CLAUDE.md)
