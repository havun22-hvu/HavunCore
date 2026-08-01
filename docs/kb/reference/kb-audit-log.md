---
title: KB-audit — historie van rondes met HIGH/CRITICAL
type: reference
scope: havuncore
last_check: 2026-08-01
---

# KB-audit — logboek

> `kb-audit-latest.md` wordt bij elke run overschreven. Hier staat wat er ná een ronde met
> HIGH- of CRITICAL-bevindingen besloten is, zodat de volgende ronde niet opnieuw begint.
> Nieuwste bovenaan; niets uit dit bestand weghalen.

## 01-08-2026 — 39 findings, waarvan er 30 vals waren

**Uitslag: 282 docs, van 1 critical + 29 high naar 0 en 0.** Geen enkele van de dertig verdwenen
findings is opgelost door een doc te wijzigen — ze kwamen uit vijf bugs in de auditor zelf.

Dat is de eigenlijke uitkomst: een rapport met 29 high's die niet kloppen wordt niet gelezen, en
dan mist het ook de echte.

| Bug | Effect |
|---|---|
| `hasFrontmatter()` matchte `/^---\n/`, niet CRLF | **20 valse high's** — elk op Windows opgeslagen doc "miste frontmatter" |
| Dezelfde fout in `ObsoleteChecker::parseFrontmatter()` | **Stille nul:** gaf `[null, null]` en sloeg het doc over. De verouderd-check deed op Windows-bestanden nooit iets, en meldde dat niet |
| `/decisions/` werd op zombies gecontroleerd | **7 valse high's** — een ADR over verwijdering noemt per definitie verwijderde klassen. Nu uitgesloten naast `/audit/` en `/plans/` |
| LinkChecker las links binnen backticks | **De enige critical** — het voorbeeld `[x](./README.md#sectie)` in de regel die uitlegt hóé de checker false-positives voorkomt |
| Bareword `Foo::bar()` gold altijd als PHP-facade | **1 valse high** — `SystemTime::now()` in een Rust-runbook |
| `EXCLUDED_PATH_SEGMENTS` kende alleen `archive/` | `docs/audit/archief/` werd meegescand sinds maart |

Vastgelegd in `tests/Unit/Services/DocsAudit/RuisFilterTest.php`, met een test die bewaakt dat een
échte zombie (dode link, niet-bestaande class, niet-bestaand commando) nog gewoon afgaat. Rood
gezien tegen de oude checkers: vier van de vijf faalden, de echte-zombie-test bleef groen.

**Handmatig gecorrigeerd:** frontmatter toegevoegd aan `.claude/commands/wu.md` en
`decisions/judotoernooi-prod-deploy-repo-prod-2026-06-08.md`.

**Semantische review:** één dubbele `title:` (`werkwijze-beoordeling-derden.md` versus zijn eigen
gearchiveerde v1.0 — bewuste versie-archivering). Poorten in `CLAUDE.md` komen overeen met
`poort-register.md`; geen doc spreekt `composer.json` tegen.

**De acht resterende low's blijven bewust staan.** Zes zijn "> 500 regels" op plannen en
naslagwerken — de 200-regelnorm uit `standards/md-doc-grootte.md` geldt voor runbooks en KB-docs
die tijdens een sessie gelezen worden, niet voor deze. De andere twee: een bewust oneven aantal
```-fences in demo-content, en een lege sectie in `reference/api-kb-search.md`.

**Gebrek in de `/kb-audit`-skill zelf, gecorrigeerd:** hij verwees naar "6 Onschendbare Regels in
`CLAUDE.md` regels 13-18" (daar staan projectfeiten) en naar `.claude/context.md` als canonical
voor paden (bestaat hier niet — bewust verwijderd 20-05-2026, `5d98a77`).

### Vervolg: de Onschendbare Regels hadden geen bron meer

Uit dat laatste punt kwam een grotere scheefstand. De set stond in **zeven** docs met een eigen
kopie, en die liepen uiteen:

- **Vier zeiden vijf regels, drie zeiden zes.** De zesde (VP-17 — nooit een falende test "fixen"
  door de assertion te wijzigen) is toegevoegd op **25-04-2026**, nadat AI in februari vier
  JudoToernooi-tests had verwijderd in plaats van gerepareerd (teruggezet in PR #2, 119 tests).
  Alle vier de achterblijvers stonden op `last_check: 2026-04-22` — drie dagen vóór die
  toevoeging, en sindsdien niet meer aangeraakt.
- **De vindplaats klopte nergens meer.** Meerdere docs verwezen naar "`CLAUDE.md`", maar die zijn
  sindsdien herschreven; van de zes projecten die zijn nagekeken had alleen JudoScoreBoard de set
  nog, met vijf regels en een eigen formulering van regel 4 en 5.

**Nu:** `runbooks/claude-werkwijze.md` §0 is de canonieke set — zes regels, mét herkomst en met
waar ze verder in doorwerken. Alle levende docs verwijzen ernaar in plaats van te kopiëren:
`havun-workflow-flowchart.md` en `kwaliteit-veiligheid-systeem.md` hebben hun kopie ingeruild voor
een verwijzing, `update.md`, `kb-audit.md`, `cv-havuncore.md` en de webapp-varianten zijn
bijgewerkt, en JudoScoreBoard heeft de zesde regel gekregen in `CLAUDE.md`, `start.md` en
`update.md`.

**Twee docs houden bewust vijf regels:** `audit/werkwijze-beoordeling-derden.md` en het
K&V-verslag van 22-04 zijn momentopnames. Een auditdocument corrigeren naar wat er later gold, is
geen opruiming maar geschiedvervalsing — ze hebben een noot gekregen die naar de canonieke set
wijst.

**Nog een zelfvangst:** dit logboek citeert `Foo::bar()` en `SystemTime::now()` als voorbeeld van
de valse positieven, waarop de zombiechecker ze als ontbrekende classes meldde.
`kb-audit-log.md` staat nu naast `kb-audit-latest.md` in de zelf-uitsluitingen — een rapport over
bevindingen citeert per definitie de refs uit die bevindingen.
