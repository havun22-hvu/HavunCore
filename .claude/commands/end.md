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

> **Er is één handover: `.claude/handover.md`. Die WERK JE BIJ — je plakt er nooit een sessie
> onderaan of bovenaan.** De handover is een **levende status** ("hoe staat dit project ervoor"),
> geen logboek ("wat deed ik wanneer"). Git bewaart de historie al; een tweede kopie in het doc
> maakt hem alleen onbetrouwbaar.

Wat je bij elke `/end` doet:

1. **Afgeronde taken WEGHALEN.** Niet doorstrepen, niet naar "Afgerond" verplaatsen — weg.
   Klaar is klaar. Zit er waarde in voor later? → KB (`patterns/`, `runbooks/`, `decisions/`).
2. **Nieuwe open punten TOEVOEGEN** aan de bestaande lijst.
3. **Bestaande punten BIJWERKEN** als er iets veranderd is (status, oorzaak, volgende stap).
4. **Achterhaalde tekst SCHRAPPEN.** Klopt een ⚠️ of "wacht op deploy" niet meer? Weg ermee —
   verifieer het desnoods (`git log`, `composer.json`, server) in plaats van te laten staan.

Structuur van `.claude/handover.md` — hou het hierbij, geen extra sessie-koppen:

```markdown
---
title: <Project> Handover
last_updated: YYYY-MM-DD
---

# <Project> — Handover

**Branch:** <branch> · **Status:** <1 zin: draait het, wat is er gaande>

## Open — wacht op Henk
| Wat | Waar |
|-----|------|
| ... | ... |

## Open — te doen
- ...

## Recent afgerond (max ~10 regels, alleen wat de volgende sessie nog nodig heeft)
- ...

## Vaste context voor dit project
- ...
```

**Grootte:** max ~120 regels. Groeit hij daarboven, dan staat er te veel afgeronde geschiedenis in —
weghalen, niet splitsen. Regel: `HavunCore/docs/kb/standards/md-doc-grootte.md`.

**Waarom dit hard is:** JudoToernooi's handover groeide zo naar **842 regels** met 20+ sessieblokken,
waarin "(Afgerond) Laravel 12 — GEDEPLOYED" pal boven "⚠️ Laravel 12 — NOG NIET gedeployed" stond,
plus taken die al weken klaar waren. Zo'n doc kost context én liegt. Bovendien indexeert de KB alleen
het begin van een bestand — de onderkant van een lange handover is onvindbaar.


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
