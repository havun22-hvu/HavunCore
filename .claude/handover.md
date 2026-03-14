# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 12 maart 2026

### Wat is gedaan:
- **havuncore-webapp volledig operationeel gemaakt**
  - Auth middleware: remote Laravel-call vervangen door lokale JWT verificatie
  - Poorten gecorrigeerd overal: frontend=8000, backend=8009 (was 5174/3001)
  - Alle 30+ bestanden (docs + code) gescand en gefixed op verkeerde poorten
  - Model hardcodes vervangen door `process.env.CLAUDE_MODEL`

- **RAG Neural Search geïmplementeerd**
  - `DocEmbedding.$fillable` miste `embedding_model` → nu gefixed
  - 514 records herindexeerd met `nomic-embed-text` (768-dim float arrays)
  - `ragService.js` herschreven: per-rij type detectie (neural vs TF-IDF)
  - Alle `rag.search()` calls geawait (was sync, nu async)
  - `_fetchCandidates()` geïsoleerd voor toekomstige Qdrant-migratie

- **Settings & Persona-systeem**
  - Anti-puppy system prompt ingesteld in `ai_config` SQLite tabel
  - `SettingsView.jsx`: model-lock (geen Thinking-modellen), RAG limit, system_prompt
  - WAL mode voor PHP DocIndexer (lezen + schrijven tegelijk mogelijk)
  - `/api/ai/kb-stats` endpoint: kennisbank stats (975 docs, 14 projecten)
  - `StatusView.jsx`: kennisbank-sectie toegevoegd

- **Plannen opgeslagen**
  - `docs/kb/decisions/qdrant-migration-plan.md` — migreer bij >5000 bestanden
  - `docs/kb/decisions/indexer-roadmap.md` — WAL, metadata, health check, watcher

### Openstaande items (vorige sessie):
- [ ] Stripe Connect testen op staging (organisator onboarding flow)
- [ ] `account.updated` webhook voor automatische status updates
- [ ] Herdenkingsportaal: `excluded_message_patterns` toevoegen aan AutoFix
- [ ] Google Business Profile: wacht op goedkeuring
- [ ] HavunAdmin deployen (StripeService prefix fix)

### Openstaande items (deze sessie):
- [ ] Indexer-roadmap uitvoeren: metadata file_type, health check API uitbreiden
- [ ] havuncore-webapp: nog niet gecommit (grote set wijzigingen — zie git status)
- [ ] `docs:index havuncore-webapp` draaien na commit

### Belangrijke context:
- havuncore-webapp staat in aparte repo: `D:/GitHub/havuncore-webapp` (geen remote ingesteld)
- Backend draait op 8009, frontend op 8000
- System persona staat in SQLite `ai_config` tabel (key=system_prompt) — aanpasbaar via Settings UI
- 459 oude embeddings hebben nog null model (TF-IDF) — ragService handelt dit per-rij af

---

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
- **AutoFix EADDRINUSE fix** (JudoToernooi)
  - `excluded_message_patterns` in `config/autofix.php` — filtert server/infra errors
  - `shouldProcess()` checkt message patterns VOOR stack trace analyse
- **HavunAdmin StripeService fix**
  - Fallback prefix `ST` vervangen door werkelijke project code uit DB
- **Invoice numbering KB** bijgewerkt met JT slug+sequence format

### Nog te doen:
- [ ] Stripe Connect testen op staging (organisator onboarding flow)
- [ ] `account.updated` webhook voor automatische status updates
- [ ] Herdenkingsportaal: `excluded_message_patterns` toevoegen aan AutoFix
- [ ] Google Business Profile: aanvraag ingediend, wacht op goedkeuring
- [ ] HavunAdmin deployen (StripeService prefix fix)

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
