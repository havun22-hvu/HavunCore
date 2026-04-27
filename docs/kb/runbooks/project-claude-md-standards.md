---
title: CLAUDE.md Standaarden per Project
type: runbook
scope: havuncore
last_check: 2026-04-27
---

# CLAUDE.md Standaarden per Project

> **Doel:** elke project-CLAUDE.md verwijst naar dezelfde Havun-normen.
> Eén canonieke template — alle projecten linken (niet dupliceren).

## Achtergrond

Op 27-04-2026 bleek dat 0/13 projecten `qv:scan`, productie-deploy-eisen of test-quality-policy noemde. Centrale standaarden bestonden, maar werden in de praktijk overgeslagen omdat project-CLAUDE.md er niet naar verwees.

Deze doc legt vast welk blok élke project-CLAUDE.md moet bevatten.

## Verplicht blok per project-CLAUDE.md

Plak dit aan het einde van elke project-CLAUDE.md (vóór de project-specifieke runbooks-tabel):

```markdown
## Havun Standaarden (verplicht — zie HavunCore KB)

Bij elke code-wijziging gelden de centrale Havun-normen. Lees bij twijfel de relevante doc:

| Norm | Centrale doc |
|------|-------------|
| 6 Onschendbare Regels | `HavunCore/CLAUDE.md` |
| Auth-standaard (magic + bio/QR + wachtwoord-optin) | `HavunCore/docs/kb/reference/authentication-methods.md` |
| Test-quality policy (kritieke paden 100 %, MSI ≥ 80 %) | `HavunCore/docs/kb/reference/test-quality-policy.md` |
| Quality standards (>80 % coverage nieuwe code, form requests, rate-limit) | `HavunCore/docs/kb/reference/havun-quality-standards.md` |
| Productie-deploy eisen (SSL/SecHeaders/Mozilla/Hardenize/Internet.nl) | `HavunCore/docs/kb/reference/productie-deploy-eisen.md` |
| V&K-systeem (qv:scan + qv:log) | `HavunCore/docs/kb/reference/qv-scan-latest.md` |
| Test-repair anti-pattern (VP-17) | `HavunCore/docs/kb/runbooks/test-repair-anti-pattern.md` |
| Universal login screen | `HavunCore/docs/kb/patterns/universal-login-screen.md` |
| Werkwijze + beschermingslagen + DO NOT REMOVE | `HavunCore/docs/kb/runbooks/claude-werkwijze.md` |

> **Bij twijfel:** `cd D:/GitHub/HavunCore && php artisan docs:search "<onderwerp>"`
```

## Rollout-procedure

1. **Pilot:** template eerst in 1 actief project plaatsen, controleren dat alle KB-paden kloppen.
2. **Cross-project rollout:** voor elk actief project 1 commit `docs(claude): add Havun standards block` met het identieke blok.
3. **IDSee:** krijgt een nieuwe `CLAUDE.md` (had er geen) met basis-template + dit blok.
4. **Geparkeerd (HavunClub, Havunity):** lager prioriteit — toevoegen bij re-activatie.
5. **Scaffold-update:** `project:scaffold` template moet dit blok auto-toevoegen voor nieuwe projecten.

## Wat als een KB-doc niet bestaat?

Bij introductie van deze rollout (27-04-2026) bestonden alle docs behalve `runbooks/beschermingslagen.md`. Die is gerefactored naar `runbooks/claude-werkwijze.md` §4. Houd dit blok in lijn met de werkelijke KB-paden — als een doc verhuist, update dan dit canonieke blok én rol opnieuw uit.

## Auditcheck

```bash
# Periodiek (handmatig of via qv:scan): check of elk actief project het blok heeft
for d in HavunAdmin Herdenkingsportaal JudoToernooi Infosyst SafeHavun \
         HavunVet Studieplanner JudoScoreBoard Munus havuncore-webapp IDSee; do
  if grep -q "Havun Standaarden" "/d/GitHub/$d/CLAUDE.md" 2>/dev/null; then
    echo "✓ $d"
  else
    echo "✗ $d — ONTBREEKT"
  fi
done
```

Resultaat moet zijn: 11x ✓ voor actieve projecten.
