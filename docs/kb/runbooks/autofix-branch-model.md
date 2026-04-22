---
title: Runbook: AutoFix Branch-Model + Dry-Run
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# Runbook: AutoFix Branch-Model + Dry-Run

> **Bron:** VP-01 (verbeterplan-q2-2026.md)
> **Centrale config:** `config/autofix.php` (HavunCore)
> **Doel:** AutoFix pusht NOOIT direct naar main/master.

## Wat is er veranderd

Vroeger: AutoFix → fix → direct commit op main → push.
Nu: AutoFix → proposal met `delivery_mode` → executor honoreert.

## Delivery modes

De centrale `AutoFixService::resolveDeliveryMode()` bepaalt per proposal:

| Risk | Delivery mode | Wat doet de project-executor? |
|------|---------------|-------------------------------|
| `low` | `branch_pr` | `git checkout -b hotfix/autofix-{project}-{ts}` → fix toepassen → push → `gh pr create` |
| `medium` | `dry_run` | Alleen notificatie naar eigenaar. **Geen** wijziging op disk |
| `high` | `dry_run` | Alleen notificatie naar eigenaar. **Geen** wijziging op disk |
| (config off) | `direct` | Legacy gedrag (uitsluitend voor backwards-compat) |

De mode staat in `proposal.context['delivery_mode']` en de status start als
`branch_pending` of `dry_run` (niet meer `pending`).

## Project-executor verplichtingen

1. Lees `proposal.context['delivery_mode']`
2. Bij `dry_run` → log + email, **stop**
3. Bij `branch_pr`:
   - `git checkout -b {proposal.context.branch_name}`
   - Indien `proposal.context.snapshot_required` → log relevante DB-rijen (max 50/tabel)
   - Apply fix
   - `git commit -m "autofix({class}): {summary} (#{proposal.id})"`
   - `git push -u origin {branch_name}`
   - `gh pr create --base main --title "AutoFix: {class}" --body "Proposal #{id}"`
   - `reportResult($id, 'pr_created', $pr_url)`

## Acceptatiecriteria (VP-01)

- [x] AutoFix pusht NOOIT meer direct naar main/master (config + service)
- [x] Elke fix resulteert in branch + PR (executor-contract gedocumenteerd)
- [x] RISK medium/high → dry_run (afgedwongen in service + test)
- [x] RISK low → branch + PR (delivery_mode in proposal context)
- [x] Database-state wordt gelogd vóór fix (snapshot_required flag)
- [x] Review-URL pattern in config (`autofix.review_url_pattern`)

## Configuratie

```env
AUTOFIX_BRANCH_MODEL=true
AUTOFIX_BRANCH_PREFIX=hotfix/autofix-
AUTOFIX_AUTO_PR=true
AUTOFIX_SNAPSHOT_ENABLED=true
AUTOFIX_SNAPSHOT_MAX_ROWS=50
```

## Tests

`tests/Feature/AutoFixDeliveryModeTest.php` — 5 tests, dekt alle delivery modes.
