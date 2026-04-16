# CONTRACTS — HavunCore

> **Onveranderlijke regels van dit project.** Niemand mag deze regels overtreden — ook AI niet. Bij elke wijziging eerst raadplegen. Wijzigen mag alleen na schriftelijk akkoord van eigenaar.

## Wat is een contract?

Een contract is een gedragsregel die los staat van de implementatie. Code mag refactoren, tests mogen wijzigen — externe gedrag in dit document mag NIET wijzigen zonder bewuste beslissing.

Bij twijfel: STOP, raadpleeg eigenaar (CLAUDE.md regel 6, runbook `test-repair-anti-pattern.md`).

---

## C-01: AI-Proxy logt elke request voor traceerbaarheid

**Regel:** Elke `POST /api/ai/chat` (en aanvullende AI endpoints) registreert: tenant, modelnaam, token-aantal, response-tijd, fout/succes-status. Logging is verplicht — een AI-aanroep zonder log is een bug.

**Waarom:** Kostenbeheersing per tenant, audit-trail, performance-monitoring, KOR-onderbouwing.

**Bewijs:** observability metrics, app/Http/Controllers/Api/AI controllers.

---

## C-02: Vault-credentials komen nooit in logs of HTTP-responses

**Regel:** Waarden uit `Vault` (API-keys, wachtwoorden, tokens) mogen nooit in logregels, exception-traces, HTTP-responses, of debug-output verschijnen. Filtering via Laravel's log-context-redaction is verplicht.

**Waarom:** Eén lekkende key compromitteert alle klant-betalingen. Wettelijke meldplicht datalekken.

**Bewijs:** `app/Services/Vault*`, log-config, GitGuardian pre-commit hook.

---

## C-03: Doc-Intelligence indexeert alleen MD-bestanden, nooit code

**Regel:** De documentatie-indexer (`docs:index`) verwerkt alleen `*.md` bestanden binnen project-folders. Geen `*.php`, `*.env`, `*.json` met secrets, geen `vendor/`, geen `node_modules/`.

**Waarom:** Voorkomt dat secrets per ongeluk in de zoekbare KB belanden. Performance.

**Bewijs:** `DocIndexCommand`, glob-patterns, `tests/Feature/Commands/DocIndexCommandTest.php`.

---

## C-04: AutoFix gaat altijd via branch + Pull Request

**Regel:** AutoFix mag NOOIT direct naar `main`/`master` van een afhankelijk project pushen. Elke fix gaat naar een hotfix-branch + een Pull Request voor handmatige review door eigenaar. Geen uitzonderingen.

**Waarom:** Eigenaar behoudt volledige controle over productie-code. Eerdere SSH-incident november 2025 heeft dit incident laten zien.

**Bewijs:** `app/Services/AutoFixService.php`, `tests/Feature/AutoFix*`.

---

## C-05: Integrity-check moet groen zijn voordat een release-tag wordt gezet

**Regel:** `php artisan integrity:check` met exit-code 0 is voorwaarde voor het zetten van een release-tag of een productie-deploy van HavunCore zelf. CI faalt automatisch bij rode integrity-checks.

**Waarom:** `.integrity.json` bewaakt kritieke UI/route-elementen die niet stilletjes mogen verdwijnen door een refactor.

**Bewijs:** `IntegrityCheckCommand`, `.integrity.json`, `tests/Feature/Commands/IntegrityCheckCommandTest.php`.

---

## C-06: Centrale KB-wijzigingen worden gepubliceerd via commit-push, niet via runtime-API

**Regel:** Veranderingen in `docs/kb/` (runbooks, patterns, decisions) komen alleen via een git-commit + push in productie. Geen runtime API-endpoint dat KB-content kan aanpassen. Lezen mag wel, schrijven niet.

**Waarom:** KB is single-source-of-truth voor 9 projecten. Een runtime-aanpassing zonder git-historie is ondetecteerbaar en niet terug te draaien.

**Bewijs:** Geen schrijf-API geïmplementeerd, alleen `DocSearchCommand` + `DocIssuesCommand` (lees-acties).

---

## C-07: Tenant-isolation: één tenant kan nooit data van een andere tenant lezen

**Regel:** Alle multi-tenant queries (AI Proxy logs, Vault, observability) filteren altijd op `tenant_id` van de geauthenticeerde caller. Een API-aanroep met geldige credentials van tenant A mag nooit data van tenant B zien — ook niet bij bugs.

**Waarom:** Wettelijk (AVG/GDPR voor klantgegevens). Vertrouwensbasis voor het hele HavunCore-platform.

**Bewijs:** Policies + Eloquent global scopes + middleware per route-groep.

---

## C-08: KOR-grens (€20.000/jaar) wordt elk kwartaal geverifieerd

**Regel:** Per kalenderkwartaal (eind maart, juni, september, december) wordt de gecombineerde omzet van Mollie + Stripe + overig getotaliseerd. Bij ≥80% van de KOR-drempel (€16.000) genereert het systeem een waarschuwing voor de eigenaar.

**Waarom:** Belastingplicht-overgang. Geen verrassing achteraf.

**Bewijs:** `/end` command stap 11, `docs/audit/verbeterplan-q2-2026.md` VP-10.

---

## C-09: Configuratie- en credentials-bestanden zitten nooit in git

**Regel:** `.env`, `.claude/credentials.md`, USB-vault-content, en vergelijkbare gevoelige bestanden zijn `.gitignore`d. GitGuardian pre-commit hook moet actief zijn (`.git/hooks/pre-commit`).

**Waarom:** Eén lek = compromis van alle Havun-systemen.

**Bewijs:** `.gitignore`, GitGuardian setup runbook, `composer audit` in CI.

---

## C-10: Observability-metrics worden dagelijks geaggregeerd én opgeschoond

**Regel:** Schedule (`routes/console.php`) heeft altijd: `observability:aggregate hourly` + `observability:aggregate daily` + `observability:cleanup` + `observability:baseline`. Geen verlopen entries (>365 dagen) blijven in de tabel staan.

**Waarom:** Performance (anders groeit DB ongelimiteerd). Compliance (wij bewaren geen tenant-PII langer dan nodig).

**Bewijs:** `routes/console.php`, `CleanupMetricsCommand`, `AggregateMetricsCommand`.

---

## Wat NIET in dit document hoort

- Implementation choices ("we use Eloquent" — kan refactoren)
- Folder-structuur ("controllers in app/Http") — codestijl, geen contract
- Performance-doelen → uptime-monitoring runbook
- Naming-conventies → CLAUDE.md regels

## Wijzigingsprotocol

Een contract aanpassen vereist:
1. Eigenaar-akkoord (schriftelijk in commit-message)
2. Reden + datum in commit-message
3. Update bewakende tests
4. Heronderhoud van afhankelijke documenten

## Cross-references

- `CLAUDE.md` — projectregels en bescherming
- `docs/kb/patterns/contracts-md-template.md` — concept en uitleg
- `docs/kb/runbooks/test-repair-anti-pattern.md` — wat te doen bij conflict
- `docs/audit/verbeterplan-q2-2026.md` — VP-14 (oorsprong)
