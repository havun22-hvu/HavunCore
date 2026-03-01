# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 1 maart 2026

### Wat is gedaan:
- **Workflow docs geconsolideerd (5 → 1):** `docs/kb/runbooks/claude-werkwijze.md` (309 regels, was ~748)
  - Samengevoegd: `claude-werkwijze.md`, `docs-first-workflow.md`, `beschermingslagen.md`, `PKM-SYSTEEM.md`
  - 3 oude bestanden verwijderd, 7 referenties bijgewerkt
  - Commit: `e9e286b`
- **Eerdere sessie: Auth docs geconsolideerd** (6 → 1): commit `cc46514`

### Openstaande items:
- [ ] Admin auth middleware voor Vault admin routes
- [ ] Restore functionaliteit in LaravelAppBackupStrategy
- [ ] 63 broken links in andere projecten
- [ ] JudoToernooi Sprint 1 (4 taken, zie `D:\GitHub\JudoToernooi\.claude\code-review-2026-02-14.md`)
- [ ] JudoToernooi Sprint 2-5 (tech debt, zie zelfde bestand)
- [ ] JudoToernooi `routes/api.php` is dode code (niet geladen) - verwijderen of correct laden
- [ ] Herdenkingsportaal KB project doc aanmaken in HavunCore (`docs/kb/projects/herdenkingsportaal.md`)
- [ ] Studieplanner operationeel maken

### Geparkeerd (HavunClub):
- [ ] Mollie API key configureren
- [ ] SMTP email configureren
- [ ] Hoofdkey toegang: `id_ed25519` heeft geen toegang tot HavunClub repo

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
