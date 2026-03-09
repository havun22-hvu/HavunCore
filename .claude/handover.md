# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 9 maart 2026

### Wat is gedaan:
- **Stripe Connect: OAuth → Account Links**
  - StripePaymentProvider: OAuth flow vervangen door Account Links onboarding
  - StripeController: callback verwerkt nu onboarding status (charges_enabled check)
  - Toernooi edit view: 3 onboarding statussen (geen account / pending / gekoppeld)
  - Afrekenen view: dynamische knoptekst per provider (iDEAL vs Stripe)
  - `STRIPE_CLIENT_ID` verwijderd uit config + .env.example (niet meer nodig)
  - BETALINGEN.md volledig bijgewerkt met Account Links flow + statussen
  - HavunCore KB judotoernooi.md bijgewerkt

### Nog te doen:
- [ ] Stripe Connect testen op staging (organisator onboarding flow)
- [ ] `account.updated` webhook voor automatische status updates
- [ ] Google Business Profile: aanvraag ingediend, wacht op goedkeuring

---

## Vorige Sessie: 3 maart 2026

### Wat is gedaan:
- **Doc cleanup Herdenkingsportaal KB:**
  - Oude pricing/packages verwijderd (premium_upgrade, memorial_website, memorial_monument)
  - Nieuwe pakketten: basis (€9,95), standaard (€24,95), compleet (€49,95)
  - XRP + EPC QR betalingen verwijderd (obsoleet), Stripe als gepland toegevoegd
  - "Premium" rol → "Betaald" hernoemd
  - crypto-payments.md: Herdenkingsportaal referentie verwijderd
- **ARCHITECTURE.md verwijderd** — verouderd (98 dagen), overlapt met CLAUDE.md/context.md
  - Referenties opgeruimd in INDEX.md en README.md
- **Doc Intelligence issues [3191] en [3192] resolved**
- **Herdenkingsportaal AutoFix errors onderzocht** — eenmalig filesystem issue, geen fix nodig

### Openstaande items:
- [ ] Admin auth middleware voor Vault admin routes
- [ ] Restore functionaliteit in LaravelAppBackupStrategy
- [ ] 63 broken links in andere projecten
- [ ] JudoToernooi Sprint 1 (4 taken, zie `D:\GitHub\JudoToernooi\.claude\code-review-2026-02-14.md`)
- [ ] JudoToernooi Sprint 2-5 (tech debt, zie zelfde bestand)
- [ ] JudoToernooi `routes/api.php` is dode code (niet geladen) - verwijderen of correct laden
- [x] Herdenkingsportaal KB project doc bijgewerkt (3 maart 2026)
- [ ] Studieplanner operationeel maken

### Geparkeerd (HavunClub):
- [ ] Mollie API key configureren
- [ ] SMTP email configureren
- [ ] Hoofdkey toegang: `id_ed25519` heeft geen toegang tot HavunClub repo

### Belangrijke context:
- **Server = Nginx** (NIET Apache) — alle projecten opgeschoond (2 maart 2026)
- **Password hashing:** Gebruik NOOIT `Hash::make()` als model `'hashed'` cast heeft (zie `docs/kb/patterns/password-hashing.md`)
- **Bestaande users met corrupte hashes** moeten wachtwoord resetten (HavunAdmin/Herdenkingsportaal)
- **AutoFix actief op:** JudoToernooi production + Herdenkingsportaal production
- **AutoFix vendor logica:** Beide projecten hebben nu identieke vendor stack trace following
- **5 Beschermingslagen:** Alle 3 projecten hebben CLAUDE.md regels + DO NOT REMOVE comments
- **HavunCore AI Proxy config key:** `CLAUDE_API_KEY` (niet ANTHROPIC_API_KEY)
- **USB vault wachtwoord:** zie fysieke notitie / wachtwoordmanager
- **SSH keys:** Encrypted in `H:\ssh-keys.vault` (zelfde wachtwoord als credentials.vault)
- PWA frontend source: `D:\GitHub\havuncore-webapp` (geen remote)
- JudoToernooi login: `/organisator/login` (niet `/login`)
- Chrome integratie: UITGESCHAKELD (globale CLAUDE.md)
