# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 17 maart 2026

### Wat is gedaan:
- **KB Search API — Remote Toegang**
  - Bearer token auth toegevoegd aan alle `/api/docs/*` endpoints (search, issues, stats, read)
  - Config key: `DOC_INTELLIGENCE_API_TOKEN` in `config/services.php`
  - Token gegenereerd en in server `.env` gezet
  - Cron job: `docs:index all --no-code` elke 6 uur op server
  - Getest: zonder token → 401, met token → resultaten OK
- **Docs bijgewerkt**
  - `docs/kb/reference/api-kb-search.md` — nieuwe API reference doc
  - `docs/kb/runbooks/op-reis-workflow.md` — KB remote access sectie + vault tabel
  - Globale `CLAUDE.md` — lokaal + remote KB fallback instructie

### Openstaande items:
- [ ] KB token verplaatsen van `H:/havuncore-kb-token.txt` naar credentials.vault (staat nu als los bestand op USB)
- [ ] **Monument versioning bouwen** — memorial_versions tabel + observer
- [ ] `/kb` command bijwerken in ALLE andere projecten
- [ ] Resend composer package verwijderen uit Herdenkingsportaal
- [ ] Stripe Connect testen op staging
- [ ] `account.updated` webhook
- [ ] Herdenkingsportaal: `excluded_message_patterns` toevoegen aan AutoFix
- [ ] HavunAdmin deployen (StripeService prefix fix)
- [ ] DocIndexer duplicate bug fixen (171 false positive issues)

### Belangrijke context:
- KB Search API is LIVE op `https://havuncore.havun.nl/api/docs/*`
- Token prefix: `hvn_kb_` — staat in server .env als `DOC_INTELLIGENCE_API_TOKEN`
- Token moet nog naar USB vault voor op-reis gebruik
- Cron draait elke 6 uur `docs:index all --no-code` (TF-IDF, geen Ollama op server)

---

## Vorige Sessie: 16 maart 2026

### Wat is gedaan:
- **Gemini Audit — AutoFix Hardening**
  - PHP syntax check (`php -l`) + auto-rollback na fix (JudoToernooi + Herdenkingsportaal)
  - Git commit+push na succesvolle fix (sluit kennis-drift gap)
  - Gestructureerde commit messages voor DocIndexer: `autofix(scope): analyse`
  - 24h rollback check toegevoegd aan AutoFixController
  - Decision record: `docs/kb/decisions/autofix-hardening-2026-03-15.md`
- **i18n Pattern** — JudoToernooi vertaalsysteem als herbruikbaar KB pattern + nieuw-project regel
- **Studieplanner Expo** — poort 8010 vastgepind, poorttabel bijgewerkt
- **/start command** — auto `git pull` + AutoFix commit detectie met review prompt
- **Backup Systeem Gefixt (KRITIEK)**
  - Nachtelijk script crashte na 1e DB door `awk` bug + `set -e` → alle DBs behalve havunadmin ontbraken
  - Hot backup kapot sinds 24 jan door `#\!` shebang fout → gefixt
  - Ontbrekende databases toegevoegd: havuncore, havunclub_production, havunvet_staging
  - Handmatige backup uitgevoerd: alle 8 prod + 4 staging DBs + storage + Hetzner offsite OK
- **Backup Monitoring** — `/health/backup` endpoint + Backups sectie in StatusView webapp

### Openstaande items:
- [ ] **Monument versioning bouwen** — memorial_versions tabel + observer (monument Nicolina template verloren, geen backup beschikbaar)
- [ ] `/kb` command bijwerken in ALLE andere projecten
- [ ] Resend composer package verwijderen uit Herdenkingsportaal
- [ ] Resend account/domein opruimen
- [ ] Stripe Connect testen op staging
- [ ] `account.updated` webhook
- [ ] Herdenkingsportaal: `excluded_message_patterns` toevoegen aan AutoFix
- [ ] Google Business Profile: wacht op goedkeuring
- [ ] HavunAdmin deployen (StripeService prefix fix)
- [ ] DocIndexer duplicate bug fixen (171 false positive issues)
- [ ] `isProjectFile()` deduplicatie in AutoFix (Service + Controller)

### Belangrijke context:
- Backup script was kapot — alleen havunadmin werd gebackupt. Nu gefixt, alle projecten draaien weer
- Monument #34 (Nicolina Sombeek) heeft template verloren, geen restore mogelijk (geen backup bestond)
- AutoFix pusht nu automatisch naar git na fix — /start detecteert dit en vraagt om KB review
- Gemini's SSH-tunnel/Docker suggesties waren onjuist — onze infra gebruikt gewone HTTPS API calls

---

## Vorige Sessie: 14 maart 2026

### Wat is gedaan:
- **Poorttabel definitief gestandaardiseerd over ALLE 7 projecten + kennisbank**
  - ~30 bestanden gefixed in 7 repos (HavunCore, HavunAdmin, Herdenkingsportaal, Studieplanner, SafeHavun, IDSee, havuncore-webapp)
  - Meeste fixes: `localhost:8000` → juiste project-poort (8001-8009)
  - havuncore-webapp backend: 5175/3001 → 8009 overal
  - IDSee: type gecorrigeerd (Node.js, niet Laravel), poort 3001 → 8006
  - HavunClub verwijderd uit poorttabel (obsoleet)
  - Master poorttabel: `docs/kb/reference/server.md` (single source of truth)
- **6 slash commands toegevoegd aan havuncore-webapp**
  - /md, /audit, /errors, /lint, /test, /update (aangepast voor Node.js/React)
  - Nu 9 commands, gelijk aan HavunCore

### Openstaande items (vorige sessies):
- [ ] Stripe Connect testen op staging (organisator onboarding flow)
- [ ] `account.updated` webhook voor automatische status updates
- [ ] Herdenkingsportaal: `excluded_message_patterns` toevoegen aan AutoFix
- [ ] Google Business Profile: wacht op goedkeuring
- [ ] HavunAdmin deployen (StripeService prefix fix)
- [ ] Indexer-roadmap uitvoeren: metadata file_type, health check API uitbreiden

### Belangrijke context:
- Definitieve poorttabel: 8000-8009 range, zie `docs/kb/reference/server.md`
- IDSee = Node.js (niet Laravel), backend poort 8006
- CLAUDE.md is geüpdatet: HavunClub en HavunVet verwijderd uit Quick Reference tabel

---

## Vorige Sessie: 12 maart 2026

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
