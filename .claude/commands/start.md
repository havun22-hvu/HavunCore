---
title: Start Session Command
type: claude
scope: havuncore
last_check: 2026-05-18
---

# Start Session Command

> **Sessie-policy:** Henk bepaalt wanneer de sessie stopt — Claude stelt **nooit** voor om af te sluiten of te pauzeren, en blijft altijd klaar voor de volgende taak. Volledige policy: `HavunCore/docs/kb/reference/session-flow-policy.md`.

> **VERPLICHT** bij elke nieuwe Claude sessie

## ⛔ KRITIEKE GEDRAGSREGELS

### Rolverdeling (ABSOLUUT)
| Rol | Wie | Wat |
|-----|-----|-----|
| **Architect** | Henk | Richting, plan goedkeuren, "ga maar" zeggen |
| **Tester** | Henk | Praktische browser/app tests — op zijn eigen moment |
| **Implementer** | Claude | Alles: code, docs, tests, commits, deploys, branches |

### Vraagdiscipline
- **NOOIT:** "Mag ik X?", "Zal ik Y doen?", "Wat moet ik als volgende doen?"
- **ALLEEN vragen bij:** iets te testen (Henk), iets vergeten in de planning, business-beslissing
- Technische beslissingen → Claude beslist zelf, meldt kort wat er gedaan is

### Toon & feedback (ELK antwoord)
- **Geen complimenten / geen bevestigend meepraten.** Geen "scherp", "goed idee", "terechte vraag". Gewoon antwoorden.
- **Corrigeer actief** als Henk een verkeerde afslag neemt of een aanname niet klopt — *"Klopt, maar..."* / *"Nee, want..."* + reden. Niet meebewegen om aardig te zijn.
- **Straight-forward**: conclusie eerst, dan kort de onderbouwing. Geen omslachtige inleidingen of opvulzinnen.

### Deploy-discipline (elke sessie herhalen aan Henk)
> **Deploy-discipline:** Codeer lokaal, tenzij de feature een externe afhankelijkheid heeft
> die lokaal niet na te bootsen is (QR-scanner, WebAuthn/biometrie, push-notificaties,
> WebSockets op prod-infra, camera/NFC/GPS op mobiel) — dan is staging de eerste testplek.
> **Één atomaire feature/fix = één staging-test = één production-deploy — zelfde moment.**
> Zodra staging groen is: direct production-knop klikken, niet uitstellen naar een volgende sessie.

### Per-agendapunt cyclus (na elk punt verplicht)
1. Geautomatiseerde tests draaien + V&K check
2. `/simplify` uitvoeren
3. MD docs + planning + handover bijwerken
4. Commit + push → deploy staging → Henk test → deploy production
5. Volgende punt

---

## Stap 0: Memory opfrissen (VERPLICHT — EERST)

Voer `/mem` uit **vóór alles**:
1. Lees `C:/Users/henkv/.claude/projects/D--GitHub-HavunCore/memory/MEMORY.md`
2. Lees elk geheugenbestand dat daarin gelinkt is
3. Toon een korte samenvatting van actieve feedback + projectcontext

> Dit voorkomt dat eerder gemaakte fouten herhaald worden.

---

## Stap 1: Git sync + AutoFix detectie (VERPLICHT)

```bash
cd [project directory] && git pull
git log --oneline --since="3 days ago" --grep="autofix("
```

Als er AutoFix commits zijn: meld ze, ga daarna door.

## Stap 1b: Dependency Security Audit (VERPLICHT)

```bash
composer audit 2>/dev/null && echo "✓ PHP OK" || echo "⚠️ PHP kwetsbaarheden!"
npm audit --omit=dev 2>/dev/null && echo "✓ NPM OK" || echo "⚠️ NPM kwetsbaarheden!"
```

Kritieke kwetsbaarheden → eerst oplossen. Low/medium → melden, doorgaan.

## Stap 1c: Server-hygiëne (VERPLICHT — als dit project op de server draait)

Zoek **alle** checkouts — gok geen pad. Niet elk project staat op `/var/www/<naam>/production`:
JudoToernooi draait op `repo-prod`, VPDUpdate op `/var/www/vpdupdate`, de webapp op
`/var/www/havuncore/webapp`. Op een pad-patroon scannen miste op 15-07 de helft.

```bash
ssh -o ConnectTimeout=15 root@188.245.159.115 '
for d in $(find /var/www -maxdepth 3 -name .git -type d 2>/dev/null | sed "s|/.git||"); do
  n=$(git -C "$d" status --porcelain 2>/dev/null | wc -l)
  s=$(git -C "$d" stash list 2>/dev/null | wc -l)
  [ "$n" -gt 0 ] || [ "$s" -gt 0 ] && printf "%-45s %4s dirty %2s stashes\n" "$d" "$n" "$s"
done'
```

**Verwachting: 0 dirty, 0 stashes.** Anders → melden en oplossen:

- **Dirty** → uitzoeken wát het is vóór je iets doet. Deploy-output/uploads → `.gitignore`
  (niet wissen — de site heeft het nodig). Een asset die de app nodig heeft → juist in git.
  Content die alleen op de server bestaat → **eerst via bundle naar git**.
- **Stash** → dezelfde sessie oplossen: toepassen of droppen met reden. Prod kan niet pushen,
  dus een blijvende stash is werk dat nergens anders bestaat.

> **Nooit blind `git clean -fd` of `stash drop` op prod.** Op 15-07 stond er 874 MB aan live APK's,
> 34 MB OTA-bundles, de gebouwde PWA en een alleen-op-de-server aangepaste landingspagina tussen de
> "rommel". Wissen = outage. Regel: `docs/kb/standards/server-hygiene.md`.

## Stap 2: Lees project documentatie (VERPLICHT)

```
1. CLAUDE.md
2. .claude/rules.md (indien aanwezig)
3. .claude/handover.md (indien aanwezig)
4. .claude/blueprint.md (indien aanwezig) → zie hieronder
5. ls .claude/ — lees wat er verder ligt, gok niet op deze lijst
```

> **Geen `context.md` in HavunCore.** Bewust verwijderd 20-05-2026 (`5d98a77`, One Project One
> Session) — de inhoud zit in `CLAUDE.md`. Andere projecten hebben er wél een. Stap 5 staat er
> omdat een vaste lijst afvinken je juist laat missen wat er ligt.

### Blueprint check

Als `.claude/blueprint.md` bestaat:
- Toon de timestamp uit de blockquote header bovenaan
- Meld: "📋 Blueprint aanwezig van [timestamp] — implementeren? Typ `/mpc` + 'ga maar'."
- Ga NIET zelf implementeren zonder "ga maar"

## Stap 3: Doc Intelligence — auto-cleanup + issues oplossen

```bash
cd D:\GitHub\HavunCore

# Herindex + ruim stale entries op (cleanupOrphaned is nu ingebouwd in docs:index)
php artisan docs:index [huidig project]

# Detecteer nieuwe issues
php artisan docs:detect [huidig project]

# Check open issues
php artisan docs:issues [huidig project] --summary
```

### Auto-actie per severity:

| Severity | Actie |
|----------|-------|
| 🔴 HIGH | Claude lost het OP vóór verder te gaan — altijd |
| 🟡 MEDIUM | Claude evalueert: echt probleem → fixen; false positive → ignoren met reden |
| 🔵 LOW | Auto-ignoren (`bulk-review-[datum]`) |

**Doel: 0 open issues na /start.** Issues hopen nooit op.

```bash
# LOW issues bulk-ignoren:
php artisan tinker --execute="
\DB::connection('doc_intelligence')->table('doc_issues')
    ->where('project', '[project]')->where('status', 'open')->where('severity', 'low')
    ->update(['status' => 'ignored', 'resolved_by' => 'auto-start-[datum]', 'resolved_at' => now(), 'updated_at' => now()]);
"
```

## Stap 4: Havun Kwaliteitsnormen (bij code wijzigingen)

Bij ELKE code wijziging:
- Coverage >80%, Form Requests, Rate limiting, Custom exceptions, Circuit breaker
- Policies, Audit log, CSRF + Security headers, CSP nonce op inline scripts
- Docs-first — plan in MD voor code
- **UI in dit project?** → E2E (Playwright) op kritieke flows verplicht — `test-quality-policy.md` §10. Pure-backend/native uitgezonderd. Ontbreekt het terwijl er een UI is → gat melden.

```bash
php artisan docs:search "havun quality standards"
```

## Na alle stappen: Korte bevestiging + doorpakken

```
✓ Memory: [N bestanden geladen — actieve feedback: ...]
✓ Gelezen: CLAUDE.md[, rules.md][, handover.md][, overige .claude/*.md]
✓ Issues: [X opgelost / 0 open]
✓ Security: [OK / kwetsbaarheden opgelost]

[project]: [korte beschrijving]
Klaar.
```

> **Open items uit handover?** → Direct beginnen met het eerste open punt. NOOIT vragen "wil je daarmee beginnen?" of "zal ik X oppakken?". Gewoon doen.
