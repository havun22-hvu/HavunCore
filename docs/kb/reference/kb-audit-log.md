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
