# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 15 februari 2026

### Wat is gedaan:
- **HavunClub commands:** Alle 9 Claude commands (/start, /end, /kb, /md, /audit, /test, /errors, /lint, /update) aangemaakt in HavunClub
- **HavunClub server setup:** Staging + production op Hetzner server
  - Directories: `/var/www/havunclub/staging` + `/production`
  - GitHub deploy key: `~/.ssh/deploy_havunclub` + SSH config
  - MySQL: `havunclub_staging` + `havunclub_production` (user: `havunclub`)
  - Nginx + SSL (Let's Encrypt, verloopt 15 mei 2026)
  - Laravel: composer, migrate, seed (inclusief sitebeheerder account)
- **HavunClub in ecosysteem:** Geregistreerd in projects-index, CLAUDE.md, context.md, server.md, deploy.md
- **docs:issues fix:** /start commands gebruikten `--project=X` maar het is een positional argument (`docs:issues havunclub`)
- **Chrome integratie uitgeschakeld:** Globale CLAUDE.md aangepast (werkt momenteel niet)

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
- HavunClub git remote op server: `github-havunclub:havun22-hvu/HavunClub.git`
- Chrome integratie: UITGESCHAKELD (globale CLAUDE.md)
