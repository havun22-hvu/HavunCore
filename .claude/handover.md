# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 26 februari 2026 (USB laptop)

### Wat is gedaan:
- **AutoFix KB docs gecommit + gepusht:** `docs/kb/reference/autofix.md` — NOTIFY_ONLY, fix strategie, context improvements, protected files, excluded patterns
- **`toolsgit-credentials` verwijderd:** Bevatte plaintext GitHub token, was per ongeluk aangemaakt
- **Opruiming:** Working tree is clean, alles gepusht naar origin/master

### Openstaande items:
- [ ] JudoToernooi AutoFix vendor fix deployen op server (`git pull` in `/var/www/judotoernooi/laravel`)
- [ ] Admin auth middleware voor Vault admin routes
- [ ] Restore functionaliteit in LaravelAppBackupStrategy
- [ ] HavunCore workflow docs consolideren (5 → 1)
- [ ] 63 broken links in andere projecten
- [ ] JudoToernooi Sprint 1 (4 taken, zie `D:\GitHub\JudoToernooi\.claude\code-review-2026-02-14.md`)
- [ ] JudoToernooi Sprint 2-5 (tech debt, zie zelfde bestand)
- [ ] JudoToernooi `routes/api.php` is dode code (niet geladen) - verwijderen of correct laden
- [ ] HavunClub: Mollie API key nog niet geconfigureerd in .env
- [ ] HavunClub: SMTP email nog niet geconfigureerd
- [ ] HavunClub hoofdkey toegang: `id_ed25519` heeft geen toegang tot HavunClub repo
- [ ] Herdenkingsportaal KB project doc aanmaken in HavunCore (`docs/kb/projects/herdenkingsportaal.md`)

### Belangrijke context:
- **AutoFix actief op:** JudoToernooi production + Herdenkingsportaal production
- **AutoFix vendor logica:** Beide projecten hebben nu identieke vendor stack trace following
- **5 Beschermingslagen:** Alle 3 projecten hebben CLAUDE.md regels + DO NOT REMOVE comments
- **HavunCore AI Proxy config key:** `CLAUDE_API_KEY` (niet ANTHROPIC_API_KEY)
- **USB vault wachtwoord:** zie fysieke notitie / wachtwoordmanager
- **SSH keys:** Encrypted in `H:\ssh-keys.vault` (zelfde wachtwoord als credentials.vault)
- PWA frontend source: `D:\GitHub\havuncore-webapp` (geen remote)
- JudoToernooi login: `/organisator/login` (niet `/login`)
- Chrome integratie: UITGESCHAKELD (globale CLAUDE.md)
