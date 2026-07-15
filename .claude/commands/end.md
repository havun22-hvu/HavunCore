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
>
> **Ook niet in `context.md`.** Dat is projectkennis (architectuur, regels, valkuilen), geen
> tijdlijn. Een sessieverslag hoort daar net zomin. Levert een sessie een blijvend feit op
> (beslissing, valkuil, geverifieerde constatering)? → als kennis in de juiste sectie zetten,
> niet als "## Laatste Sessie: <datum>" eronder plakken.

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

## 2b. Server-hygiëne — ALTIJD opruimen (alleen prod-deploy vereist toestemming)

**Je ruimt op, elke sessie — niet alleen als je zelf iets aanraakte.** Wachten tot "de volgende
sessie het wel doet" is precies hoe het op 15-07 opliep tot 29 vergeten stashes en 12 vervuilde
checkouts. Je laat de server schoner achter dan je hem aantrof.

**De enige uitzondering: deployen naar productie.** Dat blijft Henks expliciete go. Kan de drift
alleen met een deploy verdwijnen (bestand staat al in origin, checkout loopt achter) → **niet
deployen**, maar in de handover zetten dat het bij de eerstvolgende deploy oplost.

```bash
ssh -o ConnectTimeout=15 root@188.245.159.115 '
for d in $(find /var/www -maxdepth 3 -name .git -type d 2>/dev/null | sed "s|/.git||"); do
  n=$(git -C "$d" status --porcelain 2>/dev/null | wc -l)
  s=$(git -C "$d" stash list 2>/dev/null | wc -l)
  [ "$n" -gt 0 ] || [ "$s" -gt 0 ] && echo "$d | $n dirty | $s stashes"
done'
```

### Volgorde — nooit blind wissen

1. **Uitzoeken wát het is.** Live content, deploy-output of echte rommel? Een stash-titel zegt niets;
   kijk in de inhoud (`git stash show -p`) en bewijs of het al in origin staat
   (`git log --all -S '<fragment>'`).
2. **Redden wat nergens anders bestaat.** Prod kan niet pushen, dus alles wat hier alleen bestaat is
   weg zodra je het wist. Via bundle/patch → naar git (desnoods een `rescue/`-branch) → **dan pas**
   verwijderen.
3. **Deploy-output/uploads → `.gitignore`**, niet wissen. De site heeft het nodig.
4. **Rommel weg** — maar backup eerst naar `/var/backups/`, ook als je zeker denkt te zijn.
5. **Stashes**: droppen mag pas als de inhoud aantoonbaar in origin zit of gered is. Backup de
   patches vóór `stash clear`.

### Wat je in de handover zet

- Wat je niet kon oplossen zonder deploy → met de reden.
- Wat je bewust liet staan (en waarom).
- Elke `rescue/`-branch die je maakte → die moet iemand beoordelen.

> **Waarom zo streng:** op 15-07 zat tussen de "rommel" 874 MB aan live APK's, 34 MB OTA-bundles, de
> gebouwde PWA, én vier bestanden die nergens anders bestonden (SafeHavuns landingstekst, Infosysts
> zip, havun.nl's PM2-config, Studieplanners favicon waar de layout naar verwees). `git clean -fd`
> was een outage geweest, geen opruiming. Regel: `docs/kb/standards/server-hygiene.md`.


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
