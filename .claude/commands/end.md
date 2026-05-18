---
title: End Session Command
type: claude
scope: havuncore
last_check: 2026-05-18
---

# End Session Command

> **VERPLICHT** bij elke sessie-afsluiting — laat het project netjes achter.

## ⛔ KRITIEKE GEDRAGSREGEL

MD bijwerken, committen, pushen, branches opruimen = **volledig automatisch, geen toestemming nodig**.
VERBODEN: "Mag ik de handover bijwerken?", "Zal ik committen?", "Weet je zeker dat ik moet pushen?"

---

## 1. Handover bijwerken (ALTIJD, EERST)

Schrijf of update `.claude/handover.md` + `.claude/smallwork.md`:

```markdown
## Sessie [YYYY-MM-DD]

### Gedaan:
[1-3 zinnen lopende tekst. Geen bullets.]

### Openstaande items:
[Lopende tekst of leeg.]

### Context voor volgende keer:
[Lopende tekst.]
```

Herbruikbare kennis → `HavunCore/docs/kb/patterns/`, `runbooks/`, of `decisions/`.

## 2. Linter-gate (bij code wijzigingen)

```bash
# Laravel:
php artisan test --no-coverage 2>&1

# Expo/RN:
npm test 2>&1

# Integrity (indien aanwezig):
node scripts/check-integrity.cjs 2>&1
```

Falende tests → eerst fixen, DAN committen.

## 3. Doc Intelligence bijwerken

```bash
cd D:\GitHub\HavunCore
php artisan docs:index [project]   # herindexeert + ruimt stale entries op
php artisan docs:detect [project]  # detecteert nieuwe issues
```

HIGH issues die ontstonden door sessie-wijzigingen → direct oplossen.

## 4. Git: commit + push (AUTOMATISCH — alles)

```bash
# Staged + unstaged tracked files committen
git add -u
git add .claude/ docs/ CLAUDE.md  # altijd docs meenemen

# Geen .env, credentials of untracked sensible files
git status  # check wat er in gaat

# Code-commit (indien code gewijzigd)
git commit -m "feat/fix/refactor: [beschrijving] ..."

# Docs-commit
git commit -m "docs: session handover $(date +%Y-%m-%d)"

# Push alles
git push
```

**Hard rule:** na /end is `git status` leeg (alleen bewust untracked).

## 5. Branch cleanup (AUTOMATISCH)

```bash
# Verwijder lokaal gemergede branches
git branch --merged | grep -Ev "master|main|develop" | xargs -r git branch -d

# Check remote merged branches (optioneel)
git fetch --prune
```

## 6. Urenregistratie (VERPLICHT — Henk vult in)

Eén zin, lopende tekst, geen bullets:
```
[YYYY-MM-DD]: [Project] [onderwerp], [Project] [onderwerp].
```

## 7. KOR-omzetcheck (ALLEEN bij kwartaaleinde)

Drempel: €20.000/jaar. Check Mollie + Stripe. Waarschuw bij >€16.000.

---

**Nooit eindigen met:** uncommitted wijzigingen · niet-gepushte commits · stale merged branches · open issues zonder status.
