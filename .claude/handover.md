# Handover

> Laatste sessie info voor volgende Claude.

## Sessie: 13 april 2026 — Doc Intelligence cleanup

### Resultaten deze sessie:

**Doc Intelligence issues: 1035 → 200 opgelost:**
- 1002 worktree-artefacten bulk-resolved (lege `.claude/worktrees/` dirs van oude agents)
- 2 Mermaid flowchart false positives resolved (havun-workflow-flowchart.md)
- `werkwijze-v2.0-2026-03-29.md` verwijderd (exacte kopie van `werkwijze-beoordeling-derden.md`)
- `webapp/FIXES-APPLIED.md` geconsolideerd in `webapp/CODE-REVIEW.md` (Chat Reliability Fix sectie behouden)
- 3 passkey/token/qr-login issues resolved (bestanden bestaan niet meer)
- 1 crypto/mollie payments false positive resolved (aparte patterns, correct zo)
- Cross-link toegevoegd: reverb decision → runbook

### Openstaande items — VOLGENDE SESSIE:

#### 1. Resterende 200 doc issues (voornamelijk "verouderd")
- Herdenkingsportaal: 42 issues (5 HIGH: 1 prijsinconsistentie, 4 verouderde docs)
- HavunAdmin: 45 verouderde docs
- HavunCore: 23 verouderde docs
- Overige projecten: ~90 verouderde docs
- Per project oppakken bij volgende sessie in dat project

#### 2. HavunAdmin Observability UI
- Chaos resultaten toevoegen aan observability pagina
- "Project Status" sectie fixen (data ophalen werkt niet, ververs knop doet niets)

#### 3. Coverage 85.9% → 90% (Herdenkingsportaal)
- 691 statements nodig — zit in exception handlers/catch blocks

#### 4. JudoToernooi Alpine CSP build migratie
- unsafe-eval nodig door Alpine.js standaard build
- Migratie naar @alpinejs/csp = apart project

#### 5. Overig
- [ ] firebase/php-jwt v6→v7 (blocked door laravel/socialite ^6.4)
- [ ] Arweave testnet werkt niet (geen test tokens beschikbaar)
- [ ] doc-intelligence tests in CI (306 tests lokaal-only, future: aparte CI job)

### VP-02 deadline: 31 mei 2026 — Coverage 85.9%, doel 90%
