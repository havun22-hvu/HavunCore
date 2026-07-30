---
title: Fundament versus omweg — de post-mortem van Vusista
type: pattern
scope: alle-projecten
last_check: 2026-07-30
---

# Fundament versus omweg — wat er in Vusista fout ging

> **Overgenomen uit `Vusista2/BERICHT-HAVUNCORE.md` (30-07-2026), ongewijzigd op de kop na.**
> De maatregelen die hieruit volgen — inclusief twee bijstellingen en een vijfde punt — staan in
> [`plans/stackkeuze-fundament-plan.md`](../plans/stackkeuze-fundament-plan.md), met per claim
> wat er geverifieerd is.

**In één zin:** de techniek is gekozen voordat iemand de vraag stelde waar de app eigenlijk
voor is — en daarna is er vier maanden omheen gebouwd in plaats van teruggekeerd.

## Wat er gebeurde

Vusista is een **lokale desktop-fotomanager**: 76.797 bestanden op de schijf van één
gebruiker, geen netwerk, geen server, geen tweede gebruiker. Het is gebouwd op **Laravel 12
met Blade, Livewire en een lokale webserver** (`php -S`) in een Electron-schil.

Die keuze is nooit gemaakt. Het project begon als Laravel-project omdat elk Havun-project zo
begint, en toen duidelijk werd dat het een desktop-app moest zijn, is er een schil omheen
gezet (besluit 001, "Desktop-app, geen server-webapp"). Dat besluit koos **de schil**, niet
het fundament. De vraag *"is een webframework met een HTTP-server ertussen het juiste
fundament om 76.000 lokale bestanden te doorzoeken en te tonen?"* staat nergens — dus is hij
ook nooit beantwoord.

## Het signaal dat maandenlang zichtbaar was

Elke prestatiefix bleek een manier om het eigen fundament te ontwijken:

| Wat er gebouwd is | Wat het in de kern is |
|---|---|
| `public/thumb.php` | PHP draaien **zonder** Laravel, want een boot per thumbnail was te duur |
| Node-mediakanaal op een tweede poort | Node in plaats van PHP, want `php -S` levert grote bestanden niet af |
| Compressie-middleware vanaf 32 KB | om de 64 KiB-grens van `php -S` heen |
| Asset-vangnet met klokbewaking | omdat `php -S` de eigen JS-bundel afgekapt aflevert |
| C++-sidecar voor gezichtsherkenning | omdat de gebundelde PHP geen FFI heeft |
| Een halve-start-bug (30 juli) | **een gevolg van dat vangnet** — de app zag er normaal uit en was volledig dood |

Zes omwegen, en de zesde veroorzaakte een bug die de app onbruikbaar maakte zonder dat er
iets zichtbaar stuk was. Elke omweg afzonderlijk was een goede oplossing voor het probleem
dat hij oploste. **Niemand stelde de vraag waarom er zes nodig waren.**

## Vier dingen die HavunCore moet veranderen

### 1. Een productintake vóór de stackkeuze — vijf vragen, tien minuten

Niet "welk framework", maar:

1. **Waar draait het?** Server, desktop, mobiel, hybride.
2. **Hoeveel gebruikers tegelijk?** Eén gebruiker betekent: geen webstack nodig, geen auth,
   geen sessies, geen HTTP.
3. **Waar staat de data en hoeveel is het?** Lokale bestanden betekent: geen netwerklaag
   tussen de UI en de data.
4. **Wat is de zwaarste operatie, en hoe vaak gebeurt die?** Voor Vusista: een grid vullen
   met duizenden miniaturen, bij elke mapklik.
5. **Waar merkt de gebruiker vertraging?** Scrollen op 60 fps is een andere eis dan "een
   pagina binnen 300 ms".

Voor Vusista waren de antwoorden: één gebruiker, 76.000 lokale bestanden, grid vullen,
60 fps. **Dat sluit een webframework met een HTTP-server uit voordat je begint.** De vragen
kosten tien minuten; het niet stellen kostte vier maanden.

### 2. `project:scaffold` mag geen stack meer opleggen

Nu krijgt elk project automatisch Laravel plus een staging- en productieomgeving met
deploy-pipeline. Dat is een **impliciete architectuurkeuze die niemand bewust maakt**, en
voor een desktop-app is hij simpelweg fout.

Zichtbaar gevolg: Vusista's staging-deploy faalde **dertien dagen** zonder dat het opviel —
elke push een rode Action, een publieke URL met een foutpagina. Niemand merkte het, want die
server had er nooit moeten staan.

**Regel:** `project:scaffold` vraagt eerst wat voor soort applicatie het is. Bij "desktop"
geen webserver, geen staging, geen deploy-pipeline.

### 3. Een omwegen-teller als hard signaal

De regel *"robuust boven simpel"* werkte hier averechts. Hij is netjes toegepast — élke
omweg is degelijk gebouwd, met tests en documentatie — maar steeds **bínnen** de gekozen
stack, nooit **op** de stack. Elke pleister was robuust; de optelsom was fragiel.

**Regel:** bouw je een **tweede** omweg om je eigen fundament heen, dan is dat geen commit
maar een architectuurreview. Concreet signaal om op te letten: code die het framework
bewust omzeilt, een tweede runtime naast de eerste, een proces op een eigen poort, een
vangnet voor iets wat de infrastructuur hoort te doen.

### 4. Besluiten krijgen een aanname en een vervaldatum

De besluiten-docs (001 t/m 011) leggen keuzes goed vast, maar alleen de **conclusie**. Wat
ontbreekt is: *onder welke aanname geldt dit, en wat zou het omkeren?*

Besluit 001 koos NativePHP omdat het een desktop-app is. De onderliggende aanname — "Laravel
is een geschikt fundament voor lokale bestandsverwerking op deze schaal" — staat er niet, en
is dus nooit getoetst toen de metingen begonnen tegen te spreken.

**Regel:** elk architectuurbesluit noemt (a) de aanname waarop het rust en (b) de meting die
het zou omkeren. Wordt die meting gehaald, dan is het besluit toe aan herziening — niet aan
nog een omweg.

## Wat er goed ging, en behouden moet blijven

Het is niet allemaal fout. De **productkant** klopte van begin af aan en gaat ongewijzigd mee
naar de herbouw: foto's blijven in-place staan, metadata gaat het bestand in, de database is
afgeleide data, en `niet-doen.md` heeft scope-creep effectief tegengehouden. De docs,
besluiten en tests zijn van goede kwaliteit — het probleem zat niet in de zorgvuldigheid,
maar in de vraag die vooraf niet gesteld is.

## De les in één regel

> **Kies de techniek pas als je weet wat het ding moet doen, en tel de omwegen: bij twee is
> het fundament fout, niet de omweg.**
