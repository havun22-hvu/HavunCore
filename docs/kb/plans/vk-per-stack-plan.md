---
title: Plan — V&K kiest zijn checks op de stack van het project
type: reference
scope: havuncore
last_check: 2026-07-31
status: af — gebouwd en gemeten 31-07 (11 tests in EcosystemDetectionTest)
---

# V&K kiest zijn checks op de stack, en zwijgt nooit over wat het niet meet

**Henk (31-07):** *"HavunCore moet van elk project weten hoe het gebouwd is en het juiste
pakket V&K/testen gebruiken."*

**Het probleem, gemeten:** `qv:scan --project=vusista2` geeft `critical 0 · high 0 · medium 0`.
Dat leest als veilig. In werkelijkheid draaien de dependency-checks `composer` en `npm`, en die
vinden in een Cargo-project per definitie niets — er is geen enkele Rust-crate gecontroleerd.
**De nul is geen meting, maar de afwezigheid ervan.** Precies de valse geruststelling waar
`standards/claims-verifieren.md` voor waarschuwt, en gevaarlijker dan het project niet
registreren: toen wist niemand het, nu staat er een groen cijfer.

## De regel

> **Een check die niet van toepassing is, meldt dat. Een ecosysteem dat we niet kunnen meten,
> is een bevinding — geen nul.**

Drie uitkomsten per check, en ze zijn alle drie zichtbaar:

| Uitkomst | Betekenis | Hoe het toont |
|---|---|---|
| **Gemeten, schoon** | De check draaide en vond niets | telt als 0 |
| **N.v.t.** | Dit projecttype heeft dit niet (SSL op een desktop-app) | `skipped`, met reden |
| **Niet gemeten** | Het ecosysteem is er, maar we auditen het niet | **`high` finding** |

Het verschil tussen rij 2 en 3 is de hele fix. Nu vallen ze allebei stil weg.

## Hoe HavunCore weet hoe een project gebouwd is

**Detectie boven registratie.** Een `stack`-veld in de config zou een tweede waarheid worden die
uit de pas loopt zodra iemand een `package.json` toevoegt — dezelfde fout als de hardcoded
projectlijst die `DocIndexer` jarenlang naast `havun-projects.php` had. De scanner **detecteert
de ecosystemen uit de manifesten** en gebruikt het `type` uit de registry alleen voor wat je
niet kunt zien aan een bestand (heeft dit een publieke URL, hoort hier een server bij).

| Ecosysteem | Manifest | Audit |
|---|---|---|
| PHP | `composer.json` | `composer audit` ✅ |
| JS/TS | `package.json` | `npm audit` ✅ |
| **Rust** | `Cargo.lock` | **`cargo audit`** ← nieuw |
| Go | `go.mod` | ✗ niet gemeten → finding |
| Python | `requirements.txt`, `pyproject.toml` | ✗ niet gemeten → finding |
| .NET | `*.csproj` | ✗ niet gemeten → finding |
| Dart/Flutter | `pubspec.yaml` | ✗ niet gemeten → finding |

**Meerdere manifesten per repo is normaal, niet uitzonderlijk.** Vusista2 heeft géén
`Cargo.toml` in de root maar **vier** `Cargo.lock`-bestanden in submappen (`proef-index`,
`proef-thumbs`, en twee `src-tauri/`). Een check die alleen de root bekijkt, meet daar niets en
meldt netjes nul. De detectie loopt dus de boom in, met `target/`, `node_modules/` en `vendor/`
overgeslagen.

## Wat er gebouwd wordt

| # | Wat | Status |
|---|---|---|
| 1 | `cargo`-check: alle `Cargo.lock` buiten `target/`, `cargo audit --json` per stuk | ✅ |
| 2 | `EcosystemDetector`: manifesten → ecosystemen, met dieptelimiet en skip-dirs | ✅ |
| 3 | `deps-coverage`-check: ecosysteem zonder audit → **`high` "niet gemeten"** | ✅ |
| 4 | Elke scan toont per project hoe het gebouwd is, met `(NIET gemeten)` erachter | ✅ |
| 5 | Overgeslagen checks melden hun reden i.p.v. door te gaan voor schoon | ✅ |
| 6 | Testgereedschap per stack — eigen doc, policy verwijst ernaar | ✅ |

### Punt 5 — het was niet het `type`, het was de stilte

Oorspronkelijk stond hier *"het `type` uit de registry moet de web-only checks sturen"*. **Dat
was de verkeerde diagnose.** De checks kiezen zichzelf al op detectie: `sslExpiry` op de
aanwezigheid van `url`, en `formsCoverage`/`rateLimit`/`session-cookies`/`debug-mode` op
`laravelRootOrNull()` (`artisan` + `routes/`). Dat is precies zo robuust als de
`EcosystemDetector` en had geen `type`-veld nodig.

Het echte gat zat een laag dieper: die skips waren **stil**. `['findings' => []]` van een check
die niet kón draaien, is niet te onderscheiden van dezelfde return van een check die draaide en
niets vond. Een project met een fout pad in de config — geen `artisan` te vinden — meldde nul
over álle Laravel-checks en las als volledig doorgemeten.

Nu geeft elke overgeslagen check een reden terug, verzamelt de scan die, en dragen de totalen
een `overgeslagen`-teller:

```
  vusista2: rust
Niet gedraaid (n.v.t.):
  - vusista2/session-cookies: geen Laravel-root (artisan + routes/) op dit pad
Totals — critical: 0 | ... | errors: 0 | overgeslagen: 1
```

### Punt 6 — `reference/testgereedschap-per-stack.md`

De policy was 229 regels (al over de 200-norm) en veronderstelde stilzwijgend PHP/Laravel.
Splitsen in plaats van uitbreiden: een eigen doc met de gereedschapstabel per ecosysteem, en
drie verwijzingen terug vanuit de policy. **De norm verandert niet — alleen het gereedschap.**

Drie dingen die Rust echt anders maken, staan er expliciet in: unit-tests leven in
`#[cfg(test)]`-modules binnen `src/` (dus de `test-erosion`-check ziet ze **niet**),
`cargo clippy -- -D warnings` is daar de goedkoopste kritieke-padbewaking, en Playwright kan
niet bij een desktop-app — daar is de handmatige gate vóór release geen gat maar een gate.

## Wat de meting opleverde (31-07)

**De cargo-check vond eerst nul — en dat was mijn eigen bug.** De eerste versie las alleen
`vulnerabilities.list`. Handmatig `cargo audit` op de Tauri-crate gaf `vulnerabilities: 0` maar
**17 `warnings`** (16 `unmaintained`, 1 `unsound`) in een apart veld. Dezelfde stilte als het
probleem dat dit plan oplost, één niveau dieper. Na de fix over alle vijf de lockfiles:

| | vóór | ná |
|---|---|---|
| Vusista2 findings | **0** | **34** (2 medium `unsound`, 32 low `unmaintained`) |

`unmaintained` is `low` en geen `medium`: de crate werkt, maar krijgt geen security-fixes meer —
dat is een houdbaarheidsprobleem, geen kwetsbaarheid. `unsound` is `medium`, want dat is een
correctheidsfout in de crate zelf (`glib` 0.18.5, `VariantStrIter`).

**Wat de scan nu toont:** `havuncore: js, php` · `vusista2: rust` — en bij een ongemeten
ecosysteem `go (NIET gemeten)`. 11 tests in `EcosystemDetectionTest`, 1342 groen in totaal.

## Wat dit expliciet niet is

- **Geen poging elke taal te ondersteunen.** Go, Python en .NET blijven ongemeten — maar dan
  *zichtbaar* ongemeten. Dat is het punt: de scanner mag niet doen alsof.
- **Geen `cargo install` vanuit de scanner.** Ontbreekt `cargo-audit`, dan is dat een
  `error` in het rapport, geen stille nul. (Op Henks machine staat `cargo-audit 0.22.1` al.)
- **Geen vervanging van de test-normen.** Zinvolheid boven percentage blijft; alleen het
  *gereedschap* verschilt per stack.
