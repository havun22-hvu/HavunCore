---
title: Omwegen tellen — bij de tweede is het fundament fout
type: pattern
scope: alle-projecten
last_check: 2026-07-30
---

# Omwegen tellen — bij de tweede is het fundament fout, niet de omweg

**Regel: bouw je een tweede omweg om je eigen fundament heen, dan is dat geen commit maar een
architectuurreview.**

Een omweg is code die bestaat omdat de gekozen stack iets niet kan. Eén omweg is normaal —
elke stack heeft randen. Bij twee is de vraag niet meer *hoe los ik dit op*, maar *waarom moet
ik dit oplossen*.

## Waarom tellen — "robuust boven simpel" werkt hier averechts

Bij Vusista is die regel **netjes toegepast**: elke omweg is degelijk gebouwd, met tests en
documentatie. Maar steeds **bínnen** de stack, nooit **op** de stack. Elke pleister robuust,
de optelsom fragiel. Het liep tot zes:

| Wat er gebouwd is | Wat het in de kern is |
|---|---|
| `public/thumb.php` | PHP draaien **zonder** Laravel, want een boot per thumbnail was te duur |
| Node-mediakanaal op een tweede poort | Node in plaats van PHP, want `php -S` levert grote bestanden niet af |
| Compressie-middleware vanaf 32 KB | om de 64 KiB-grens van `php -S` heen |
| Asset-vangnet met klokbewaking | omdat `php -S` de eigen JS-bundel afgekapt aflevert |
| C++-sidecar voor gezichtsherkenning | omdat de gebundelde PHP geen FFI heeft |
| Halve-start-bug (30 juli) | **gevolg van dat vangnet** — de app zag er normaal uit en was volledig dood |

Elke omweg afzonderlijk was een goede oplossing voor het probleem dat hij oploste. Niemand
stelde de vraag waarom er zes nodig waren. Volledige post-mortem:
`patterns/fundament-versus-omweg.md`.

## Waarom een register, en niet "gewoon opletten"

"Tel tot twee" telt niemand — daarom is het bij Vusista tot zes gelopen. Omwegen komen weken
uit elkaar, vaak van verschillende sessies, en elk voelt op zichzelf redelijk. Het patroon is
alleen zichtbaar als het ergens **naast elkaar** staat.

Daarom: **`docs/omwegen.md` in elk project.** Eén regel per omweg. De tweede regel in dat
bestand is het signaal.

```markdown
# Omwegen — <project>

> Code die bestaat omdat de stack iets niet kan. Bij de tweede regel: architectuurreview.

| Datum | Wat | Wat wordt omzeild | Review |
|---|---|---|---|
| 2026-05-02 | `public/thumb.php` | Laravel-boot per thumbnail te duur | — |
| 2026-05-19 | Node op poort 8010 | `php -S` levert grote bestanden niet af | **← tweede: review** |
```

## Wat telt als omweg

- code die het framework **bewust omzeilt** om het snel genoeg te krijgen;
- een **tweede runtime** naast de eerste, voor één taak;
- een **eigen poort** voor een proces dat bij de app hoort;
- een **vangnet** voor iets wat de infrastructuur zelf hoort te doen;
- een **sidecar** omdat de taal iets niet kan (FFI, threads, langlevende processen).

**Wat níét telt:** een externe binary voor werk dat nergens in de taal thuishoort (exiftool,
ffmpeg), een cache, een queue-worker, of een bibliotheek van derden. Dat is normaal gebruik
van een stack, geen ontwijking ervan.

## Wat de review oplevert

De review is geen ritueel — hij eindigt in één van drie uitkomsten, en die schrijf je op:

1. **Fundament klopt, omweg mag** — met de reden waarom deze rand acceptabel is. De teller
   gaat niet op nul; de volgende omweg is de derde.
2. **Fundament klopt niet meer** — dan volgt een herbouwbesluit met een `decisions/`-doc, niet
   nog een omweg. Wat de app moet doen is dan bekend; de intake uit
   `standards/stack-keuze.md` is in dat geval tien minuten werk met de antwoorden al in huis.
3. **De aanname onder een eerder besluit is gesneuveld** — dan is dát besluit toe aan
   herziening. Elk architectuurbesluit hoort zijn omkeerpunt te noemen; is die meting
   gehaald, dan is de omweg het verkeerde antwoord.

## De les in één regel

> **Kies de techniek pas als je weet wat het ding moet doen, en tel de omwegen: bij twee is
> het fundament fout, niet de omweg.**
