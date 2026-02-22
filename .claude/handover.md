# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 22 februari 2026

### Wat is gedaan:
- **5 Beschermingslagen gedocumenteerd:** Nieuw runbook `docs/kb/runbooks/beschermingslagen.md`
  - MD docs → DO NOT REMOVE comments → Tests → CLAUDE.md regels → Memory
  - Escalatietabel: hoe vaker fout, hoe meer lagen
- **CLAUDE.md bescherming toegevoegd:** Alle 3 projecten (HavunCore, JudoToernooi, Herdenkingsportaal)
  - HavunCore + Herdenkingsportaal: sectie "Bescherming bestaande code" toegevoegd
  - JudoToernooi: had dit al (regels 117-136)
- **DO NOT REMOVE comments:** Toegevoegd aan kritieke views in beide projecten
  - JudoToernooi: 6 views (layouts/app, home, dashboards, auth)
  - Herdenkingsportaal: 5 views (navigation, footer, dashboard, memorial editor, payments)
- **JudoToernooi AutoFix vendor fix:** Vendor stack trace logica toegevoegd
  - Was al in Herdenkingsportaal, miste bij JudoToernooi
  - Vendor bestand als "NOT editable" referentie + eerste project bestand als "FIX TARGET"
  - System prompt: "Never modify vendor/ files - fix the PROJECT file"
- **AutoFix docs bijgewerkt:** `docs/kb/reference/autofix.md` + memory met vendor sectie

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
- **USB vault wachtwoord:** 3224
- **SSH keys:** Encrypted in `H:\ssh-keys.vault` (zelfde wachtwoord)
- PWA frontend source: `D:\GitHub\havuncore-webapp` (geen remote)
- JudoToernooi login: `/organisator/login` (niet `/login`)
- Chrome integratie: UITGESCHAKELD (globale CLAUDE.md)
