---
title: Plan — de stackkeuze wordt een besluit, geen erfenis
type: reference
scope: havuncore
last_check: 2026-07-30
status: plan — wacht op "ga maar"
---

# De stackkeuze wordt een besluit, geen erfenis

**Aanleiding:** `Vusista2/BERICHT-HAVUNCORE.md` (30-07-2026) — Vusista is een lokale
desktop-fotomanager (76.797 bestanden, één gebruiker, geen netwerk) gebouwd op Laravel 12 +
Livewire + `php -S` in een Electron-schil. Die keuze is nooit gemaakt: het project begon als
Laravel-project omdat élk Havun-project zo begint. Vier maanden lang is er omheen gebouwd —
zes omwegen — in plaats van teruggekeerd naar het fundament.

**De les in één regel:** *kies de techniek pas als je weet wat het ding moet doen, en tel de
omwegen: bij twee is het fundament fout, niet de omweg.*

## Wat geverifieerd is (30-07-2026)

| Claim | Uitkomst |
|---|---|
| `project:scaffold` legt Laravel op | **Erger dan gemeld.** `--stack` weigert álles behalve laravel — `ProjectScaffoldCommand.php:47` aborteert met *"niet in MVP"*. De keuze is dichtgetimmerd, niet vergeten |
| Besluit 001 koos de schil, niet het fundament | **Klopt.** `Vusista/docs/besluiten/001-desktop-app-geen-webapp.md` kiest NativePHP/Electron; het woord "fundament" of "webserver" komt er niet in voor |
| De intake-antwoorden ontbraken | **Nee — ze stónden er al.** Besluit 001: *"Auth vervalt — lokale single-user app; er is geen andere gebruiker."* De informatie was er in juli; wat ontbrak was het doortrekken naar het fundament |
| "Havun-standaard" als kaal argument | **Deels — mijn eerste telling was fout.** Die kwam uit een OR-grep op `Havun-standaard\|Laravel 12`; vrijwel alle 8 treffers waren het tweede. In HavunCore's `projects/` staat de stack als **beschrijving** ("Type: Laravel 12 SaaS") en dat is correct. De kale bewering als *reden* staat in **Vusista's eigen** `docs/techniek/architectuur.md` (*"Framework: Laravel 12 — Havun-standaard"*). De maatregel verhuist daarom naar de KB-ingang, waar een nieuw project de norm leest |
| Er bestaat een beslisregel voor stackkeuze | **Nee.** 33 `decisions/` + 6 `standards/` doorzocht — geen enkele gaat over welke stack bij welk werktype past |
| Staging-server "had er nooit moeten staan" | **Te sterk.** `staging.vusista.havun.nl` is een bewuste browser-demo met dummydata (Vusista `CLAUDE.md`). Het gat is dat 13 dagen rode Actions niemand bereikten — monitoring, niet scaffold |

## De vijf maatregelen

### 1. Productintake vóór de stackkeuze — vijf vragen, bindend

Niet "welk framework", maar: **(1)** waar draait het · **(2)** hoeveel gebruikers tegelijk ·
**(3)** waar staat de data en hoeveel · **(4)** wat is de zwaarste operatie en hoe vaak ·
**(5)** waar merkt de gebruiker vertraging.

Voor Vusista: één gebruiker, 76.000 lokale bestanden, een grid vullen bij elke mapklik,
60 fps. **Dat sluit een webframework met een HTTP-server uit vóór je begint.**

**Kritiek — dit is waar het bij Vusista misging:** de antwoorden verzamelen is niet genoeg,
die stónden al in besluit 001. De intake moet **een artefact opleveren** (`docs/intake.md` in
het project) dat de antwoorden *doortrekt naar een stackconclusie*, en die conclusie is wat
`project:scaffold` leest. Een intake zonder conclusie is wat we al hadden.

### 2. `project:scaffold` vraagt eerst wat voor applicatie het is

De `--stack`-guard eruit; in plaats daarvan een verplichte `--type` (server-webapp ·
desktop · mobiel · library/CLI). Bij `desktop`: **geen** webserver, staging, deploy-pipeline
of nginx-template. De V&K-registratie en de werkwijze-bestanden (CLAUDE.md, commands, KB)
blijven voor élk type — dat deel deugt.

### 3. Omwegen-register per project

**Regel:** bouw je een **tweede** omweg om je eigen fundament heen, dan is dat geen commit
maar een architectuurreview.

**Kritiek:** "tel tot twee" telt niemand — daarom is het bij Vusista tot zes gelopen. Het
moet een register zijn: `docs/omwegen.md` in elk project, één regel per omweg met *wat er
omzeild wordt*. De tweede regel in dat bestand is het signaal. Herkenningspunten: code die
het framework bewust omzeilt, een tweede runtime naast de eerste, een proces op een eigen
poort, een vangnet voor iets wat de infrastructuur hoort te doen.

`Robuust boven simpel` werkte hier averechts: elke omweg was degelijk gebouwd, mét tests en
docs — maar steeds **bínnen** de stack, nooit **op** de stack. Elke pleister robuust, de
optelsom fragiel. Die nuance hoort bij de regel in de globale CLAUDE.md.

### 4. Elk architectuurbesluit noemt zijn aanname en zijn omkeerpunt

**(a)** de aanname waarop het rust · **(b)** de meting die het zou omkeren.

**Terminologie bijgesteld:** niet "vervaldatum" — een datum verloopt zonder dat er iets
veranderd is, en dan heb je een besluit dat "verlopen" is maar nog klopt. Het is een
**omkeerpunt**: een falsificatie-conditie. Wordt die meting gehaald, dan is het besluit toe
aan herziening — niet aan nog een omweg.

Besluit 001's stilzwijgende aanname was *"Laravel is een geschikt fundament voor lokale
bestandsverwerking op deze schaal"*. Die staat er niet, en is dus nooit getoetst toen de
metingen begonnen tegen te spreken.

### 5. "Havun-standaard" vervalt als argument

De zin *"Framework: Laravel 12 — Havun-standaard"* staat als kale bewering in acht
projectdocs. Dat is de mechanische oorzaak dat de vraag nooit gesteld is. Vervangen door de
werktype-onderbouwing uit de intake; waar Laravel de juiste keuze is (JudoToernooi, Veen,
Herdenkingsportaal, HavunAdmin) kost dat één regel.

## Wat behouden blijft

De **productkant** van Vusista klopte vanaf dag één en gaat ongewijzigd mee naar de herbouw:
foto's in-place, metadata het bestand in, database als afgeleide data, en `niet-doen.md`
heeft scope-creep effectief tegengehouden. Docs, besluiten en tests zijn van goede kwaliteit.
**Het probleem zat niet in de zorgvuldigheid, maar in de vraag die vooraf niet gesteld is.**

Dit plan raakt dus niet de werkwijze-uitrol, de V&K-registratie of de docs-first-regel — die
blijven onveranderd.

## Uitvoering

| # | Wat | Raakt | Status |
|---|---|---|---|
| 1 | `standards/stack-keuze.md` — de vijf vragen + beslisboom op werktype, bindend | nieuw doc | ✅ `85b4033` |
| 2 | `patterns/omwegen-tellen.md` — het register + het tweede-omweg-signaal | nieuw doc | ✅ `85b4033` |
| 3 | `project:scaffold`: `--stack`-guard eruit, `--type` erin; desktop krijgt geen webinfra | **code** + tests | ✅ `f964bfe` — 27 tests |
| 4 | `/mpc` fase 0 + `/arch`: intake verplicht vóór stackkeuze | 2 commands | ✅ `f964bfe` |
| 5 | Besluit-sjabloon met aanname + omkeerpunt | `standards/docs-first.md` | ✅ `85b4033` |
| 6 | "Havun-standaard" als argument → waarschuwing in `projects-index.md` | docs | ✅ `f964bfe` |
| 7 | **`vusista` ontbrak in `config/quality-safety.php`** — vier maanden nooit gescand | config | ✅ zie hieronder |
| 8 | Uitrol naar de actieve CLAUDE.md's | 15 docs | ✅ zie hieronder |
| 9 | **Apart:** rode Actions moeten iemand bereiken (13 dagen onopgemerkt) | health-alerts | open |
| 10 | **Overleg Henk:** `/var/www/vusista/{production,staging}` opruimen | serverconfig | open |
| 11 | **`vusista2` geregistreerd** in beide configs + KB-index (16 docs) | config | ✅ zie hieronder |
| 12 | `qv:scan` kent geen Cargo — een Rust-project scant schoon zonder gemeten te zijn | **code** | open |
| 13 | **Overleg Henk:** Vusista2 heeft géén GitHub-remote — code bestaat op één schijf | repo-opzet | open |

### Punt 11-13 — de fout herhaalde zich meteen (31-07)

Henk vroeg of `vusista2` wél geregistreerd stond. **Dat stond het niet** — in geen van beide
configs, terwijl het project al drie commits en werkende Rust-proeven had (66.844 thumbnails in
vijf minuten, 60 fps grid). Exact dezelfde faalmodus als Vusista 1, één dag na het vastleggen
ervan. Nu geregistreerd zonder `server_path`/`local_url`/`url` (desktop, geen HTTP-server).

Twee gaten die dit blootlegt, allebei groter dan de registratie zelf:

- **Punt 12 — `qv:scan` meet Rust niet.** De eerste scan geeft `critical 0 · high 0 · medium 0`.
  Dat leest als veilig, maar de checks zijn `composer` en `npm`: in een Cargo-project vinden die
  per definitie niets. `cargo audit` bestaat niet in de scanner. **Een schone scan op een
  niet-PHP/JS-project is "niet gemeten", geen "geen bevindingen"** — precies het soort valse
  geruststelling waar `standards/claims-verifieren.md` voor waarschuwt. Zolang dit open staat,
  hoort de scanner dat te zeggen in plaats van een nul te tonen.
- **Punt 13 — geen remote.** `git remote -v` is leeg: Vusista2 bestaat alleen op Henks schijf.
  Eén crash en de herbouw plus de proeven zijn weg. Aanmaken van een private repo is
  outward-facing en niet aan deze sessie toegewezen → Henks go.

### Punt 8 — uitrol (30-07)

HavunCore + **14 actieve projecten**. Geparkeerd overgeslagen (HavunClub, Demo, Havunity,
Infosyst, IDSee, Agorano, HavunVet) — die dragen de oude normen sowieso nog.

- **Blok bewust kort gehouden (11 regels).** De eerste versie was 15 regels en duwde zes
  projecten over de 120-regelnorm die dit plan zelf bevestigt. Het volledige verhaal staat in
  de KB; de CLAUDE.md verwijst.
- **Drie projecten stonden op een feature-branch.** JudoToernooi (`seo/sitemap-en-uitslagen`)
  en JudoScoreBoard (`marketing/aso-listing`) zijn met een cherry-pick óók op de hoofdbranch
  gezet. **Vusista niet:** zijn `main` loopt 335 commits achter op `staging`, dus de cherry-pick
  botste. De norm staat op `staging` — de branch die daar leeft. Vusista-scope.
- **`VeenLedenadministratie` heeft geen `CLAUDE.md`** — niets om aan toe te voegen. Eigen gat,
  hoort in een Veen-sessie (fase 3 wacht op Cees).
- **Vier CLAUDE.md's staan boven de 120 regels**: Vusista 138, Studieplanner-api 135,
  JudoScoreBoard 130, havuncore-webapp 125. Alle vier zaten daar al aan of overheen vóór deze
  uitrol; inkorten is projectwerk in een eigen sessie.

### Punt 7 — wat de eerste scan opleverde (30-07)

`vusista` stond in `havun-projects.php` maar **niet** in `quality-safety.php`, dus er draaide
nooit een `composer audit`, secrets-scan of session-check. Toegevoegd zonder `url` (desktop-app,
geen SSL/Observatory-check). De allereerste scan meteen daarna:

**critical 1 · high 2 · medium 4** — 25% form-validatie-dekking (0 FormRequests), `session.php`
secure-cookie-default niet `true`, recent verwijderde tests, en 4× guzzle-advisories.
Dat zijn **Vusista-bevindingen** → een Vusista-sessie, en grotendeels ingehaald door de herbouw.
De les voor HavunCore staat los: *een project dat geen webapp is, heeft nog steeds
dependencies en secrets.* Registratie mag daar niet van afhangen.

**Niet in dit plan:** de herbouw van Vusista zelf (`Vusista2/PLAN.md`, Rust + Tauri v2,
wacht op eigen "ga maar") — dat is een Vusista2-sessie, geen HavunCore-scope. Vusista2 richt
zichzelf handmatig in; dat is terecht, want `project:scaffold vusista2 --type=desktop` levert nu
wél een bruikbare basis, maar geen Rust-skelet (bewust — zie punt 3).
