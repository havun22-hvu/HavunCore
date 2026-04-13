# Handover

> Laatste sessie info voor volgende Claude.

## Sessie: 13 april 2026 — Veel gedaan, les geleerd

### Wat goed ging:
- HavunCore CI: timeout opgelost (PHPUnit 11 auto-coverage + doc-intelligence group exclude) → 34 seconden
- phpseclib CVE fix
- 891 coverage tests (83.4% → 85.9%)
- 13 chaos experimenten (was 5)
- SRI hashes op alle externe scripts
- /mpc command in alle 16 projecten
- Deploy key voor webapp repo werkt
- Infrastructuur doc geschreven
- Geen-polling beslissing bijgewerkt

### Wat FOUT ging (LESSEN):
- Login page, QR scanner, nginx routing: code direct op production getest zonder lokaal testen
- Geen tests geschreven voor webapp (Node.js + React)
- Service worker update flow kapot
- Meerdere hotfixes nodig op production
- **Les: ALTIJD /mpc volgen. NOOIT losse code schrijven en deployen.**

### Openstaande items — VOLGENDE SESSIE:

#### 1. Webapp login page GOED doen (via /mpc)
- Docs staan klaar: `webapp/docs/LOGIN-REDESIGN.md` + `webapp/docs/LOGIN-IMPLEMENTATION-PLAN.md`
- Code is geschreven maar NIET lokaal getest
- Service worker update flow werkt niet
- QR scanner op smartphone: camera opent niet in PWA
- **Eerst:** VPDUpdate docs/code lezen als referentie
- **Eerst:** Test framework opzetten (Vitest + Jest)
- **Eerst:** Lokaal testen voordat er iets gedeployed wordt

#### 2. Webapp test setup
- 0 tests nu — moet opgezet worden
- Vitest voor React frontend
- Jest of Vitest voor Node.js backend
- CI workflow aanmaken

#### 3. Service Worker / PWA update
- Update wordt niet geïnstalleerd
- Referentie: VPDUpdate heeft werkende SW update flow
- Documenteren + fixen via /mpc

#### 4. Nginx routing documentatie
- Gedocumenteerd in `webapp/docs/INFRASTRUCTURE.md`
- Elke wijziging EERST in docs, dan pas nginx

#### 5. Overige items (uit vorige sessie)
- Coverage 85.9% → 90% (Herdenkingsportaal)
- HavunAdmin Observability UI (chaos resultaten)
- JudoToernooi Alpine CSP build migratie
- doc-intelligence tests in CI (306 tests lokaal-only)
- firebase/php-jwt v6→v7

### KRITIEKE WERKWIJZE
- **ALTIJD /mpc:** MD docs → Plan → Code
- **NOOIT code op production testen**
- **NOOIT deployen zonder lokaal testen**
- **NOOIT code schrijven zonder tests**
