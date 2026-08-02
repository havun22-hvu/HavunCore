---
title: KB audit — havuncore
type: reference
scope: havuncore
last_check: 2026-08-02
---

# KB audit — havuncore

> Auto-gegenereerd door `php artisan docs:audit`. Overschrijft bij elke run.

## Samenvatting

- Files gescand: **277**
- 🔴 Critical: **3**
- 🟠 High: **27**
- 🟡 Medium: **0**
- 🔵 Low: **9**
- ⚪ Info: **0**

## 🔴 Critical findings

### `docs/kb/reference/authentication-methods.md` _(detector: link)_

**Probleem:** Broken link: [HP SPEC.md sectie 3](../../../../Herdenkingsportaal/SPEC.md)

**Voorstel:** Corrigeer of verwijder link

### `docs/kb/reference/authentication-methods.md` _(detector: link)_

**Probleem:** Broken link: [HP LOGIN-METHODS.md](../../../../Herdenkingsportaal/docs/2-FEATURES/LOGIN-METHODS.md)

**Voorstel:** Corrigeer of verwijder link

### `docs/kb/runbooks/doc-intelligence-setup.md` _(detector: link)_

**Probleem:** Broken link: [x](./README.md)

**Voorstel:** Corrigeer of verwijder link

## 🟠 High findings

### `.claude/commands/wu.md` _(detector: structure)_

**Probleem:** Ontbrekende frontmatter

**Voorstel:** Voeg `---` block toe met title/type/scope

### `docs/kb/contracts/havunclub-koppelingen.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `Invoice`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/contracts/havunclub-koppelingen.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan club:token-create`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/decisions/007-mcp-removal-2026-05-09.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `Havun\Core\Services\MCPService`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/decisions/007-mcp-removal-2026-05-09.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `Havun\Core\Services\TaskOrchestrator`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/decisions/007-mcp-removal-2026-05-09.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `Havun\Core\Services\APIContractRegistry`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/decisions/007-mcp-removal-2026-05-09.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `Havun\Core\Models\MCPMessage`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/decisions/007-mcp-removal-2026-05-09.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan havun:orchestrate`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/decisions/007-mcp-removal-2026-05-09.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan havun:status`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/decisions/judotoernooi-prod-deploy-repo-prod-2026-06-08.md` _(detector: structure)_

**Probleem:** Ontbrekende frontmatter

**Voorstel:** Voeg `---` block toe met title/type/scope

### `docs/kb/decisions/repo-hygiene-2026-05-09.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan qv:scan-residu`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/decisions/reverb-safeguards-2026-04-05.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan reverb:health`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/patterns/iban-validatie.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `TransactionCategorizationService`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/patterns/reverb-laravel.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan reverb:health`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/patterns/tauri-rust-reproducible-build.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `SystemTime`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/patterns/zinvolle-tests.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `MagicLinkToken`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/patterns/zinvolle-tests.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `TvKoppeling`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/patterns/zinvolle-tests.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `SyncConflict`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/patterns/zinvolle-tests.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `Vrijwilliger`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/patterns/zinvolle-tests.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `ClubUitnodiging`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/projects/havunadmin.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `App\Rules\Iban`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/projects/judotoernooi.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan reverb:health`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/projects/studieplanner.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan xrp:check`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/reference/critical-paths-judotoernooi.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `Wedstrijd`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/reference/security-findings.md` _(detector: zombie)_

**Probleem:** Class-ref bestaat niet: `BackupService`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/runbooks/reverb-troubleshoot.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan reverb:health`

**Voorstel:** Update doc of herstel class/command

### `docs/kb/runbooks/security-headers-check.md` _(detector: zombie)_

**Probleem:** Artisan command bestaat niet: `php artisan gtag:refresh`

**Voorstel:** Update doc of herstel class/command

## 🔵 Low findings

### `docs/audit/archief/werkwijze-v1.0-2026-03-29.md` _(detector: structure)_

**Probleem:** File is 699 regels (> 500)

**Voorstel:** Overweeg splitsing

### `docs/audit/verbeterplan-q2-2026.md` _(detector: structure)_

**Probleem:** File is 507 regels (> 500)

**Voorstel:** Overweeg splitsing

### `docs/audit/werkwijze-beoordeling-derden.md` _(detector: structure)_

**Probleem:** File is 905 regels (> 500)

**Voorstel:** Overweeg splitsing

### `docs/kb/claude-workflow-enforcement.md` _(detector: structure)_

**Probleem:** Oneven aantal ```-fences

**Voorstel:** Controleer of dit bewust is (demo); sluit anders code-block(s)

### `docs/kb/decisions/004-vision-orchestration.md` _(detector: structure)_

**Probleem:** File is 1433 regels (> 500)

**Voorstel:** Overweeg splitsing

### `docs/kb/reference/api-kb-search.md` _(detector: structure)_

**Probleem:** Lege section: Status: ACTIEF

**Voorstel:** Vul aan of verwijder

### `docs/kb/reference/havun-workflow-flowchart.md` _(detector: structure)_

**Probleem:** File is 1194 regels (> 500)

**Voorstel:** Overweeg splitsing

### `docs/kb/reference/productie-deploy-eisen.md` _(detector: structure)_

**Probleem:** File is 844 regels (> 500)

**Voorstel:** Overweeg splitsing

### `docs/kb/reference/unified-login-system.md` _(detector: structure)_

**Probleem:** File is 606 regels (> 500)

**Voorstel:** Overweeg splitsing

## Batch-approval commands

> Deze commands zijn **kandidaten voor verwijdering** (obsolete + zombie).
> Scan de lijst, controleer, en voer uit met **"Uitvoeren"** als akkoord.
>
> **SAFETY-GUARD:** het blok begint met `git status` — als de working
> tree niet clean is, stopt het. Dat voorkomt dat een `rm` onbedoeld
> samen met andere wijzigingen gecommit wordt.

```bash
git status --porcelain | grep -q . && { echo "Working tree not clean — abort"; exit 1; }
# Class-ref bestaat niet: Invoice
rm "docs/kb/contracts/havunclub-koppelingen.md"
# Artisan command bestaat niet: php artisan club:token-create
rm "docs/kb/contracts/havunclub-koppelingen.md"
# Class-ref bestaat niet: Havun\Core\Services\MCPService
rm "docs/kb/decisions/007-mcp-removal-2026-05-09.md"
# Class-ref bestaat niet: Havun\Core\Services\TaskOrchestrator
rm "docs/kb/decisions/007-mcp-removal-2026-05-09.md"
# Class-ref bestaat niet: Havun\Core\Services\APIContractRegistry
rm "docs/kb/decisions/007-mcp-removal-2026-05-09.md"
# Class-ref bestaat niet: Havun\Core\Models\MCPMessage
rm "docs/kb/decisions/007-mcp-removal-2026-05-09.md"
# Artisan command bestaat niet: php artisan havun:orchestrate
rm "docs/kb/decisions/007-mcp-removal-2026-05-09.md"
# Artisan command bestaat niet: php artisan havun:status
rm "docs/kb/decisions/007-mcp-removal-2026-05-09.md"
# Artisan command bestaat niet: php artisan qv:scan-residu
rm "docs/kb/decisions/repo-hygiene-2026-05-09.md"
# Artisan command bestaat niet: php artisan reverb:health
rm "docs/kb/decisions/reverb-safeguards-2026-04-05.md"
# Class-ref bestaat niet: TransactionCategorizationService
rm "docs/kb/patterns/iban-validatie.md"
# Artisan command bestaat niet: php artisan reverb:health
rm "docs/kb/patterns/reverb-laravel.md"
# Class-ref bestaat niet: SystemTime
rm "docs/kb/patterns/tauri-rust-reproducible-build.md"
# Class-ref bestaat niet: MagicLinkToken
rm "docs/kb/patterns/zinvolle-tests.md"
# Class-ref bestaat niet: TvKoppeling
rm "docs/kb/patterns/zinvolle-tests.md"
# Class-ref bestaat niet: SyncConflict
rm "docs/kb/patterns/zinvolle-tests.md"
# Class-ref bestaat niet: Vrijwilliger
rm "docs/kb/patterns/zinvolle-tests.md"
# Class-ref bestaat niet: ClubUitnodiging
rm "docs/kb/patterns/zinvolle-tests.md"
# Class-ref bestaat niet: App\Rules\Iban
rm "docs/kb/projects/havunadmin.md"
# Artisan command bestaat niet: php artisan reverb:health
rm "docs/kb/projects/judotoernooi.md"
# Artisan command bestaat niet: php artisan xrp:check
rm "docs/kb/projects/studieplanner.md"
# Class-ref bestaat niet: Wedstrijd
rm "docs/kb/reference/critical-paths-judotoernooi.md"
# Class-ref bestaat niet: BackupService
rm "docs/kb/reference/security-findings.md"
# Artisan command bestaat niet: php artisan reverb:health
rm "docs/kb/runbooks/reverb-troubleshoot.md"
# Artisan command bestaat niet: php artisan gtag:refresh
rm "docs/kb/runbooks/security-headers-check.md"
```

