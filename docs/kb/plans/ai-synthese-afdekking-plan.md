---
title: "Plan: AI-synthese-risico's structureel afdekken + uitrol naar alle projecten"
type: plan
scope: alle-projecten
status: uitgevoerd 24-07-2026
date: 2026-07-24
---

# Plan — AI-synthese-risico's afdekken

> **Uitgevoerd 24-07-2026.** 14 CLAUDE.md's + 4 `start.md`'s dragen de regel (geverifieerd met
> grep per project). Munus verwijderd incl. alle registraties. HavunCore-suite: **1312 passed,
> 3658 assertions, 301s**. `docs:detect` schoon; beide nieuwe docs vindbaar via `docs:search`.
> Niet gedaan: `start.md` in 12 projecten zonder bruikbaar ankerpunt — zie de afwijking bij punt 3.

**Doel:** de drie mechanismen die AI-gegenereerde code voorspelbaar kwetsbaar maken
(behaagzucht, mainstream-bias, randgevallen) borgen in de werkwijze — niet als goed voornemen
maar als stap in de cyclus. Aanleiding: de update-banner-meting van 24-07, waar een groene test
bijna een niet-bestaande bug als "opgelost" had gerapporteerd.

**Besloten:** uitrol naar **alle projecten** (niet alleen de KB), en de kernregel wordt een
**verplichte stap met meldplicht** (niet alleen een norm).

## Kernregels die uitgerold worden

1. **Rood gezien, of het telt niet** — bij een bugfix eerst de test zien falen tegen de oude code;
   niet gedaan = expliciet melden dat het niet bewezen is.
2. **Reproduceer vóór je fixt** — een doc/handover/issue die zegt dat iets stuk is, is een claim,
   geen meting.
3. **Afwijkende architectuur = tweede mening** — `/arch` (Gemini) + ADR bij multi-tenancy,
   betalingen, auth/crypto, datamigratie.

## Agendapunten

### 1. KB-docs vastleggen (klaar in fase 1, moet nog gecommit)

| Bestand | Wat |
|---|---|
| `docs/kb/standards/ai-synthese-risicos.md` | Nieuw — de drie mechanismen, tegenmaatregelen, casus 24-07 |
| `docs/kb/patterns/test-rood-gezien.md` | Nieuw — de operationele regel + de valkuil met hergebruikte servers/builds |

**Verificatie:** `docs:index havuncore` + `docs:detect havuncore` schoon; beide docs vindbaar via
`docs:search`.

### 2. HavunCore zelf: regel in de cyclus

| Bestand | Wijziging |
|---|---|
| `CLAUDE.md` | Blok van ~6 regels onder de bestaande "claim verifieer je hélemaal"-sectie, met verwijzing naar de twee nieuwe docs |
| `.claude/commands/start.md` | Per-agendapunt-cyclus: stap 1 wordt "tests draaien **+ bij een bugfix: rood gezien tegen de oude code**" |
| `.claude/commands/mpc.md` | Zelfde toevoeging in de fase-3-cyclus |

Afhankelijkheid: punt 1 eerst (de docs waarnaar verwezen wordt moeten bestaan).

### 3. Uitrol naar de andere projecten

Zelfde blok in `CLAUDE.md` + `.claude/commands/start.md` van elk project — de vorm die bij
`start2` (19-07) werkte: één kort blok, verwijzing naar de KB voor het volledige verhaal.

**Afwijking van het plan (gemeten 24-07, tijdens uitvoering):** de `start.md`-bestanden hebben
géén gemeenschappelijke structuur. Slechts 1 van de 15 heeft het anker "Bij rode tests: STOP",
2 hebben een per-agendapunt-cyclus. Blind een regel inplakken zou in 12 projecten neerkomen op
gokken waar hij hoort — precies het forceren dat het risico-blok verbiedt.

**Daarom:** `CLAUDE.md` is de drager (die leest elke sessie sowieso). `start.md` krijgt de regel
alleen waar een duidelijk ankerpunt bestaat. Waar geen `CLAUDE.md` is (VeenLedenadministratie,
JS-Blocker-Extension) gaat de regel naar `start.md`, of wordt het project gemeld als niet-gedaan.

**15 projecten**, af te handelen in batches van ~5 (parallel per batch, elk project is
onafhankelijk):

- Aeterna · Havun · HavunAdmin · havuncore-webapp · HavunMarketing
- Herdenkingsportaal · JudoScoreBoard · JudoToernooi · LastMatch · SafeHavun
- Studieplanner · Studieplanner-api · VeenLedenadministratie · VPDUpdate · Vusista
- JS-Blocker-Extension¹

¹ JS-Blocker-Extension is **geen git-repo** — alleen het bestand aanpassen, niets te committen.

**Geparkeerd — niet uitrollen (besluit Henk 24-07):** HavunVet, Havunity, Infosyst, IDSee,
HavunClub, Demo, Agorano. Ze worden in de eindmelding genoemd, niet stilzwijgend overgeslagen.

**Gemeten op de server (24-07):** van deze zeven heeft alleen Demo nog een nginx-vhost —
`demo.havun.nl`, met `root /var/www/demo/staging/public`, en **dat pad bestaat niet**. De vhost
is dus dood en serveert een foutpagina. Opruimen raakt server-config → apart met Henk overleggen,
niet in dit plan.

### 3b. Munus verwijderen

`D:\GitHub\Munus` wordt verwijderd. **Zonder archief** — expliciet besluit van Henk (24-07), na
de melding dat de map geen remote heeft en verwijderen dus onomkeerbaar is: de historie bestaat
daarna nergens meer. Geen server-sporen om op te ruimen (geen `/var/www`-map, geen vhost).

Wat er nog in stond: 553 KB, laatste drie commits waren werkwijze-docs (deploy-discipline,
KB-audit, docs-first) — geen applicatiewerk. Eén ongecommitte wijziging (`.claude/commands/start.md`)
verdwijnt mee.

**Verwijzingen opruimen** — anders blijft de KB verwijzen naar een pad dat niet meer bestaat,
precies wat de nieuwe standaard afkeurt:

| Waar | Actie |
|---|---|
| `config/havun-projects.php:58` (`'munus' => ['path' => 'D:/GitHub/Munus']`) | Regel eruit |
| `docs/kb/reference/poort-register.md:123` | Regel eruit |
| `memory/project_active_priorities.md` | "Munus geparkeerd" → verwijderd 24-07 |
| `deploy-keys-inventory.md` · `playwright-rollout-plan.md` · `qv-scan-latest.md` · `test-quality-compliance.md` · `project-claude-md-standards.md` · `deploy-key-management.md` · `deploy-keys-github-actions.md` | Per stuk beoordelen: is het een **levende inventaris** → regel eruit; is het een **momentopname** (afgeronde scan/plan) → laten staan, die beschrijft terecht het verleden |
| `grote-schoonmaak-2026-07-15.md` | Laten staan — afgerond plan, historisch |

Daarna `docs:index` opnieuw (ruimt stale entries op) en `docs:detect` om te controleren dat er
geen dode links achterblijven.

**Verificatie per batch:** `grep` op de regel in beide bestanden van elk project, en `git log -1`
per repo om te bevestigen dat de commit er staat.

### 4. Afronden

- `docs:index` + `docs:detect` opnieuw, 0 open issues
- HavunCore-handover bijwerken (nieuwe standaard + wat er uitgerold is)
- Testsuite HavunCore draaien (`php artisan test --no-coverage`) — er verandert geen code, maar
  de norm is: na elk punt meten. Uitslag wordt gemeld, ook als er al bestaande failures zijn.

## Risico's

| Risico | Aanpak |
|---|---|
| **Uitrol raakt 23 repo's** — veel commits buiten HavunCore | Alleen MD-bestanden, geen code, geen deploys. Werkwijze-uitrol is expliciet HavunCore-scope (zie `feedback-scope-waarschuwen`); de *inhoud* van die projecten wordt niet aangeraakt |
| **Afwijkende structuur per project** — niet elke `start.md` heeft dezelfde secties | Per project de juiste plek zoeken in plaats van blind een patroon toepassen. Wijkt een project te veel af → apart melden, niet forceren |
| **Regel wordt ruis** — nog een blok in elke CLAUDE.md | Kort houden (max ~6 regels), inhoud in de KB. CLAUDE.md-limiet is 120 regels; bij overschrijding eerst iets verouderds eruit |
| **De regel is niet afdwingbaar** — een model dat vergeet te melden, meldt ook niet dát het vergeet | Erkend en genoteerd in de standaard zelf. Dit verlaagt de kans, het sluit niets uit. Automatisering (hook/CI) is bewust *niet* gekozen — bewerkelijk en snel omzeild |
| **Pushen faalt** op repo's zonder auth/remote | Vooraf geïnventariseerd: 2 zonder remote, 1 zonder repo. Rest heeft `origin`; falende push wordt per project gemeld, niet genegeerd |

## Wat er niet in zit

- Geen pre-commit hooks of CI-checks (bewust, zie hierboven).
- Geen wijziging aan de vraagdiscipline — technische keuzes blijven bij Claude.
- Geen herziening van bestaande tests in andere projecten. De regel geldt vanaf nu; oude suites
  worden niet met terugwerkende kracht op "rood gezien" gecontroleerd.
