---
title: Bewaking die niets meet ziet eruit als bewaking zonder bevindingen
type: pattern
scope: alle-projecten
last_updated: 2026-08-06
---

# Bewaking die niets meet

**Een check die omvalt en een check die niets vindt, geven hetzelfde beeld: nul.** Elke keer dat dat
in dit systeem misging, was de oorzaak dezelfde en het gevolg maandenlang stil. Gevonden 03/04-08-2026,
vier gevallen in één avond.

## Waar het misgaat — vier plekken, in volgorde van de meetketen

| Laag | Faalmodus | Voorbeeld (gemeten) |
|---|---|---|
| **Meten** | de check kan de bron niet bereiken en geeft `error` terug | `backup-coverage` en `serverHealth` gingen via SSH naar `root@<server>` — vanaf díé server, die geen sleutel naar zichzelf heeft. Elke nacht `errors=1, high=0` |
| **Waar het draait** | het pad/de tool klopt alleen op de machine van de ontwikkelaar | `composer`/`npm`/`cargo` gebruikten `D:/GitHub/...` op een Linux-server → 40 errors = nul gecontroleerde projecten. Composer was er bovendien 2.2.6 en kende `audit` niet |
| **Rapporteren** | het rapport leest maar een deel van wat er gemeten is | `qv:log` en `docs:handover` lazen één run-bestand; alle acht wekelijkse checks draaien ná dat moment en hadden **nooit** iets gerapporteerd |
| **Bereiken** | de melding gaat naar een kanaal zonder lezer | `actions:watch` schreef naar stdout, en de cron stuurt stdout naar `/dev/null` |

## De regels die eruit volgen

1. **Niet gemeten is een bevinding, geen leeg resultaat.** Een check die niets kon meten geeft een
   `critical` terug en *onderdrukt de rest* — alles wat eronder zou staan is verzonnen.
2. **Zet de ouderdom van de meting erbij.** Een uitkomst van zes dagen oud leest anders dan een van
   vanochtend, maar alleen als je het opschrijft.
3. **`errors > 0` mag nooit als groen langskomen.** Zet het getal in élk rapport dat een mens leest,
   niet alleen in het detailbestand.
4. **Meet op de machine waar het over gaat.** Een commando voor de machine waar je al op staat,
   draai je gewoon; SSH naar jezelf is een omweg die om een sleutel vraagt die er niet hoort te zijn.
5. **Scheid "hier niet van toepassing" van "kapot".** Vijf projecten die terecht niet op de server
   staan, elke nacht als error melden, leert je het getal negeren — en dan zegt het niets meer op de
   nacht dat er wél iets omvalt. Overgeslagen mag, maar met reden en zichtbaar.
6. **Een melding is pas een melding als hij een mens bereikt.** Cron-stdout is geen kanaal.

## De verraderlijkste variant

**Een beveiligingsmaatregel die de bewaking blind maakt.** De prod-checkouts gebruiken per repo een
eigen SSH-host-alias (`git@github-judotoernooi:…`) zodat één gelekte deploy-key één project opent.
Goede maatregel — maar de repo-detectie van `actions:watch` matchte alleen letterlijk `github.com`,
dus zes van de zeven checkouts vielen weg. Idem de read-only PAT: te smal ingesteld, en GitHub geeft
dan **404, niet 403**, dus die repo's verdwenen zonder een woord.

Dit soort interactie zie je niet in een test en niet in code review. **Je ziet het door de check te
draaien op de machine waar hij hoort te draaien, en de uitkomst te vergelijken met wat je verwacht.**

## Een filter dat te smal staat (06-08-2026)

`check_supervisor` in `havun-health-check.sh` alarmeerde op `FATAL|BACKOFF`. Van de acht statussen
die supervisor kent gingen **`STOPPED`, `EXITED` en `UNKNOWN` er stil doorheen** — een worker die
gestopt is of geëindigd zonder herstart, meldde niets. En gaf `supervisorctl` niets terug (supervisor
plat, of het laatste proces uit de config), dan was `bad` leeg en schreef de check
*"supervisor-workers draaien"*. **Nul gecontroleerd las als groen**, hetzelfde als bij `actions:watch`.

Nu: alles wat niet `RUNNING`, `STARTING` of `STOPPING` is telt als fout, en nul processen is een
`critical` in plaats van een groen vinkje. Alle acht statussen plus de lege uitvoer zijn droog
doorgerekend vóór de plaatsing — een bewakingsfout is stil, dus die zie je niet vanzelf.

**Randgeval bij het uitrollen:** het bestand ging via Windows over en kreeg CRLF mee, waarmee het
script op de server onbruikbaar werd (`/bin/bash^M: bad interpreter`). Herstel vanuit de backup,
daarna `sed -i 's/\r$//'` op de server vóór de installatie. **Een bewakingsscript dat niet meer
start, meldt ook niets.**

## Het laatste gat van deze soort (06-08-2026)

Een V&K-check die stopt met draaien verdween uit het rapport — één regel minder, en de totalen
bleven nul. `VerwachteChecks` leest nu de scheduler en meldt wat er ontbreekt. **De scheduler is de
bron, geen tweede lijst in config**: die zou uiteenlopen en dan de verkeerde verzameling bewaken.

En `assemble()` gaf `null` als er helemaal geen runs waren, waar elke aanroeper een leeg rapport van
maakte. **De ernstigste uitkomst was de stilste.** Nu meldt hij alle zestien.

Daarmee is dit patroon vier keer op rij dezelfde vorm gebleken: `actions:watch` (nul repo's),
`backup-coverage` (SSH naar zichzelf), `check_supervisor` (nul processen) en nu de scan zelf.
**Vraag bij elke bewaking: wat gebeurt er als er niets te meten valt?** Als het antwoord "dan is het
groen" is, meet je niets.

## Hoe je het vindt

- Draai elke check handmatig op de doelmachine en tel wat hij *gemeten* heeft, niet wat hij vond.
- Forceer een bevinding (drempel tijdelijk op 5%) om te bewijzen dat er data doorheen komt. Nul
  findings is geen bewijs van werken.
- Lees het runbestand, niet het rapport: `errors`, `skipped` en het aantal gecontroleerde items.

Zie ook: `plans/registry-drift-check-plan.md`, `plans/qv-rapportage-venster-plan.md`,
`patterns/test-rood-gezien.md`.
