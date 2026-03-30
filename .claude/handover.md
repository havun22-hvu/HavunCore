# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 30 maart 2026

### Wat is gedaan:
- **JT AutoFix scanner exclusion** — hackpoging URLs (`admin_phpinfo.php4`, `.php` extensies) uitgesloten van AutoFix analyse
  - 3 nieuwe patterns in `config/autofix.php`: RouteFileRegistrar errors, admin_phpinfo, .php URLs
  - Gedeployed naar productie
- **Foutpreventie-analyse** uitgevoerd — complete inventarisatie van alle beschermingsmechanismen
  - Conclusie: ~7-8 FTE equivalent productie-output (niet 4 zoals eerder geschat)
  - AutoFix, CI, integrity checks, guard tests, doc intelligence = geautomatiseerde QA/DevOps
- **Docker evaluatie** — niet nodig voor huidige setup, GitHub Actions CI is de juiste stap
- **Havun.nl** — start/end commands ook gesynchroniseerd

### Vorige sessie: 29 maart 2026

#### Wat is gedaan (avond):
- **KB-first workflow** — `/start` command omgebouwd voor alle 8 projecten
  - Laadt NIET meer de volledige werkwijze-doc (342 regels bespaard per sessie)
  - Gebruikt `docs:search` on-demand i.p.v. alles laden
  - `/end` command met Linter-Gate ook naar alle projecten gesynchroniseerd
- **VP-03 DONE:** Context-injectie geoptimaliseerd (KB-first i.p.v. alles laden)
- **Development workflow diagram** aangemaakt (`docs/kb/reference/development-workflow.md`)
  - Compleet schema van cursor → code → test → CI → productie → AutoFix

### Wat is gedaan (overdag — andere sessie):
- **Kwartaal-audit ingericht:** beoordelingsdocument v1.0 → v2.0, verbeterplan met 10 VP's
- **Externe beoordelingen:** Gemini (9/10) + Claude Sonnet (8.5/10)
- **VP-01 DONE:** AutoFix branch-model + dry-run + GitHub REST API (JT + HP, deployed)
- **VP-04 DONE:** Dependency audit in `/start`, security headers runbook, OWASP ZAP jan 2027
- **VP-05 DONE:** `.integrity.json` + `check-integrity.cjs` op 5 projecten (HP, JT, HA, SH, IN)
- **VP-06 DONE:** 5 onschendbare regels + sessielimiet in werkwijze + `/update`
- **VP-07 DONE:** Emergency runbook + noodprotocol voor Thiemo (zoon)
- **VP-08 DONE:** Staging verplicht in deploy-procedure + `/end`
- **VP-09 DONE:** Health check cron (6 apps, elke 5 min, e-mail alerts via Laravel mail)
- **VP-10 DONE:** KOR-omzetcheck in `/end` command
- **CI/CD:** GitHub Actions met coverage-check op alle 6 Laravel-projecten
- **GitGuardian:** Pre-commit hook op alle 8 projecten (JT + JSB waren nog niet actief)
- **GITHUB_TOKEN:** Op server gezet voor JT + HP (AutoFix PR-aanmaak)
- **Server deployed:** JT + HP AutoFix branch-model live

### Openstaande items:
- [ ] **VP-02** Test coverage verhogen — JT: 15.4% (doel 75%), HP: 2.9% (doel 60%)
- [x] **VP-03** Context-injectie geoptimaliseerd (KB-first workflow)
- [ ] Morgen: streven naar 9.5/10 score bij volgende review
- [ ] Secret rotation protocol gedocumenteerd maar nog niet uitgevoerd
- [ ] OWASP ZAP eerste scan: januari 2027
- [ ] Droogtest noodprotocol met Thiemo plannen

### Nog open van vorige sessies:
- [ ] **iDEAL → iDEAL | Wero** teksten aanpassen in HP + JT checkout/terms/emails
- [ ] **GitGuardian incident resolven** via dashboard
- [ ] Test suite uitrollen naar andere projecten
- [ ] Fase 2-4 testing plan
- [ ] Composer vulnerability fixen (1 critical)
- [ ] JudoScoreBoard end-to-end test
- [ ] Resend composer package verwijderen uit HP
- [ ] JT server structuur wijziging verwerken in KB

### Belangrijke context:
- iDEAL | Wero is VERPLICHT sinds 22-01-2026 — wij zijn te laat, moet zsm aangepast
- Resend = dood, Brevo = enige email provider
- Android Studio is nu beschikbaar voor lokale APK builds + emulator
- Zakelijk emailadres: havun22@gmail.com
- GitHub Copilot opt-out: Settings → Copilot → data training uit (voor 24 april 2026)

---

## Vorige Sessie: 24 maart 2026

### Wat is gedaan:
- **Cursor abonnement evaluatie** — conclusie: opzeggen, geen meerwaarde boven Claude Code
- **`/simplify` hook** — globale PostToolUse hook die na elke `git commit` herinnert om /simplify te draaien
  - Staat in `~/.claude/settings.json` → werkt in ALLE projecten
  - Gebruikt `node` (geen jq beschikbaar op Windows)
- **PHPUnit test suite** voor HavunCore (19 tests, allemaal groen):
  - `tests/Feature/RouteSmokeTest.php` — health, version, docs endpoints
  - `tests/Feature/ApiEndpointTest.php` — task queue, vault, AI proxy, auth
  - `tests/Feature/SecurityAuditTest.php` — stack traces, app env
  - `tests/TestCase.php` — met `assertRouteAccessible()` helper
  - `phpunit.xml` + `.env.testing` (gitignored)
- **GitHub Actions CI** — `.github/workflows/tests.yml` draait bij push/PR op master
  - Composer cache, composer audit, PHPUnit
- **Testing plan** — `docs/kb/runbooks/github-testing-plan.md` met 4 fases

### Openstaande items:
- [x] GitGuardian credential leak fixen (24 maart avond)
- [ ] Test suite uitrollen naar andere projecten
- [ ] Fase 2-4 testing plan
- [ ] Composer vulnerability fixen
- [ ] JudoScoreBoard end-to-end test
- [x] Doc issues #7837-7839 — QR login runbooks overlap (opgelost 28-03-2026)

### Belangrijke context:
- `/simplify` hook staat GLOBAAL in `~/.claude/settings.json` — niet per project
- `.env.testing` staat in `.gitignore` (via `.env.*` pattern) — moet lokaal aangemaakt worden
- GitHub Actions genereert eigen `.env.testing` via `php artisan key:generate`
- `BROADCAST_CONNECTION=null` is nodig in test env (Reverb/Pusher crasht anders)

---

## Vorige Sessie: 22 maart 2026

### Wat is gedaan:
- **JudoScoreBoard audit** — volledige codebase + backend analyse
  - App (Expo RN): alle schermen, scoring, timer, osaekomi, WebSocket — KLAAR
  - Backend (JudoToernooi): alle API endpoints, Reverb channels, middleware — KLAAR
  - Koppeling is aan BEIDE kanten gebouwd, moet alleen end-to-end getest worden
- **KB Scheduled Tasks** — Windows Task Scheduler aangemaakt
  - 08:03 + 20:07: `docs:index all --force` + `docs:detect`
  - Script: `scripts/kb-update.bat`, log: `storage/logs/kb-update.log`
- **Claude Code features onderzocht** — Remote Control, Channels, Scheduled Tasks
  - Remote Control: geparkeerd (GitHub/Claude account mismatch)
  - Channels: geparkeerd (niet prioriteit)
  - CronCreate: session-only, niet persistent — Windows Task Scheduler gekozen
- **Expo Android app procedure** — Studieplanner als template voor JudoScoreBoard
  - Runbook: `docs/kb/runbooks/expo-android-app-setup.md`

### Openstaande items:
- [ ] **JudoScoreBoard end-to-end test** — DeviceToegang aanmaken, login, wedstrijd toewijzen, resultaat terugsturen
- [ ] JudoScoreBoard: pending results retry bij reconnect
- [ ] JudoScoreBoard: audio bestanden (buzzer) toevoegen
- [ ] JudoScoreBoard: match duration per toernooi (nu hardcoded 240s)
- [ ] `/f` command testen in de praktijk
- [x] Doc issues #7837-7839 — QR login runbooks overlap (opgelost 28-03-2026)
- [x] Doc issue #7836 — crypto/mollie payments (false positive, ignored 28-03-2026)

### Belangrijke context:
- JudoScoreBoard backend endpoints zijn KLAAR in JudoToernooi (ScoreboardController, CheckScoreboardToken middleware, Reverb channels)
- KB update draait nu 2x per dag automatisch via Windows Task Scheduler
- Remote Control werkt niet soepel: GitHub=havun22, Claude=henkvu

---

## Vorige Sessie: 20 maart 2026

### Wat is gedaan:
- **`/f` (Focus) command aangemaakt** — nieuw slash command in alle 12 projecten

### Openstaande items:
- [x] `/f` command aanmaken
- [ ] Gemini system prompt overwegen voor pre-sessie briefings (optioneel)
- [ ] 80x stale doc issues — bulk ignore of review

### Belangrijke context:
- `/f` is bewust simpel gehouden: geen hooks, geen subagents, alleen een verplichte leesronde
- Bestanden gekopieerd naar: Demo, Havun, HavunAdmin, HavunVet, Herdenkingsportaal, IDSee, Infosyst, JudoToernooi, SafeHavun, Studieplanner, VPDUpdate

---

## Vorige Sessie: 19 maart 2026 (avond)

### Wat is gedaan:
- **DocIntelligence issues review** — 88 open issues doorgelopen:
  - #7923 (HIGH, inconsistente prijzen): false positive — genegeerd
  - #7922 (broken link havunclub.md): HavunClub geparkeerd — genegeerd
  - #7840 + #7841 (duplicate code): opgelost door consolidatie
- **DocIntelligence code refactoring** — duplicaten verwijderd:
  - `DocIndexer`: nieuwe public methodes `getProjectPath()`, `getProjectPaths()`, `calculateSimilarity()`
  - `IssueDetector`: eigen `calculateSimilarity()` + `getProjectPath()` verwijderd → gebruikt DocIndexer
  - `StructureIndexer`: eigen `$localPaths`/`$serverPaths` verwijderd → haalt paden via DocIndexer

### Openstaande items:
- [ ] Doc issues #7837/#7838/#7839 — QR login runbooks overlap
- [x] Doc issue #7836 — crypto/mollie payments (false positive, ignored 28-03-2026)
- [ ] 80x stale doc issues — bulk ignore of review

---

## Vorige Sessie: 19 maart 2026

### Wat is gedaan:
- **EU Compliance — alle 3 projecten (HP, JT, SP)**
  - KB compliance checklist aangemaakt: `docs/kb/runbooks/eu-compliance-checklist.md`
  - Favicons gegenereerd voor HP (hplogo.png) en havun.nl (logo.png)
  - HP + JT + SP: adres (Jacques Bloemhof 57, 1628 VN Hoorn) + email in footer
  - SP: privacy, voorwaarden, cookies, disclaimer, herroepingsformulier aangemaakt
  - JT: herroepingsformulier pagina + terms sectie bijgewerkt + footer link
  - HP + JT: herroepingscheckbox bij checkout (betaalknop geblokkeerd zonder vinkje)
  - SP: bunq iDEAL als betaalmethode vastgelegd in legal pagina's
  - company.php config aangemaakt voor SP en JT (was al in HP)

### Openstaande items:
- [ ] Bereikbaarheidsuren toevoegen aan footer alle projecten
- [ ] Herroepingsknop bouwen voor SP (deadline: 19 juni 2026)
- [ ] KB token verplaatsen naar credentials.vault
- [ ] Monument versioning bouwen
- [ ] Resend composer package verwijderen uit HP
- [ ] Stripe Connect testen op staging
- [ ] Herdenkingsportaal: `excluded_message_patterns` AutoFix
- [ ] HavunAdmin deployen (StripeService prefix fix)

### Belangrijke context:
- Telefoonnummer NIET in footers — alleen email (privacykeuze eigenaar)
- Vestigingsadres: Jacques Bloemhof 57, 1628 VN Hoorn (staat in company.php config)
- HP + JT: herroeping uitgesloten via checkout checkbox (directe dienst)
- SP: herroeping verplicht (jaarabonnement), formulier op /herroeping
- SP betaalmethode: bunq iDEAL (niet Mollie)

---

## Laatste Sessie: 18 maart 2026 (nacht)

### Wat is gedaan:
- **Server directory herstructurering** — 5 verhuizingen voor consistent `/var/www/{project}/{omgeving}` patroon:
  1. `/var/www/studieplanner-api` → `/var/www/studieplanner/production`
  2. `/var/www/staging.judotoernooi/laravel` → `/var/www/judotoernooi/staging`
  3. `/var/www/development/HavunCore` → `/var/www/havuncore/production`
  4. `/var/www/havuncore.havun.nl` → `/var/www/havuncore/webapp`
  5. Lokaal: `D:\GitHub\havuncore-webapp` → `D:\GitHub\HavunCore\webapp`
- **Alle configs bijgewerkt:** nginx, systemd, cron, PM2, supervisor
- **Alle docs/code bijgewerkt:** 12 projecten, ~50+ bestanden (alle oude paden gefixt)
- **Studieplanner KB volledig herschreven** (was zwaar verouderd)
- **Runbook geschreven:** `docs/kb/runbooks/server-verhuizingen-2026-03-18.md`
- **Oude restanten opgeruimd:** backup nginx configs, lege mappen

### Openstaande items:
- [ ] KB token verplaatsen van `H:/havuncore-kb-token.txt` naar credentials.vault
- [ ] Monument versioning bouwen — memorial_versions tabel + observer
- [ ] Resend composer package verwijderen uit Herdenkingsportaal
- [ ] Stripe Connect testen op staging
- [ ] Herdenkingsportaal: `excluded_message_patterns` toevoegen aan AutoFix
- [ ] HavunAdmin deployen (StripeService prefix fix)
- [ ] PIN backend code opschonen (fase 3 — niet urgent)
- [ ] SafeHavun/HavunAdmin/Infosyst: ook magic link toevoegen (volgende projecten)

### Belangrijke context:
- havuncore-webapp staat nu als aparte git repo BINNEN HavunCore (`/webapp/`, in .gitignore)
- Runbook `server-verhuizingen-2026-03-18.md` bevat troubleshooting per verhuizing
- Oude studieplanner.havun.nl URL toonde gecachte oude pagina — browser cache probleem, server was al correct

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
- havuncore-webapp staat in aparte repo: `D:/GitHub/HavunCore/webapp` (remote: havuncore-webapp op GitHub)
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
