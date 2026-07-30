---
title: Stackkeuze — vijf vragen vóór het eerste bestand
type: standard
scope: alle-projecten
last_check: 2026-07-30
---

# Stackkeuze — BINDEND voor alle Havun-projecten

**Regel: de techniek kies je pas als je weet wat het ding moet doen.**
Vóór er één bestand wordt aangemaakt — vóór `project:scaffold`, vóór de eerste commit —
beantwoord je vijf vragen en trek je ze door naar een stackconclusie. Tien minuten werk.

**"Havun-standaard" is geen argument.** Dat Laravel bij de meeste projecten past, zegt niets
over dít project. Wie Laravel kiest, kiest hem op de antwoorden hieronder — of motiveert de
afwijking. Een stack die je erft in plaats van kiest, kost je later maanden:
`patterns/fundament-versus-omweg.md`.

## De vijf vragen

| # | Vraag | Wat het uitsluit |
|---|---|---|
| 1 | **Waar draait het?** Server · desktop · mobiel · hybride | Desktop/mobiel sluit een serverstack uit als fundament — een schil eromheen is géén antwoord |
| 2 | **Hoeveel gebruikers tegelijk?** | Eén gebruiker = geen auth, geen sessies, geen HTTP, geen multi-tenancy |
| 3 | **Waar staat de data, en hoeveel is het?** | Lokale bestanden = geen netwerklaag tussen UI en data |
| 4 | **Wat is de zwaarste operatie, en hoe vaak gebeurt die?** | Een dure operatie die vaak gebeurt, bepaalt het fundament — niet de gemiddelde request |
| 5 | **Waar merkt de gebruiker vertraging?** | "60 fps bij scrollen" is een andere eis dan "pagina binnen 300 ms" — de tweede haalt een webstack, de eerste niet |

**De antwoorden verzamelen is niet genoeg.** Bij Vusista stónden ze al in besluit 001
(*"Auth vervalt — lokale single-user app; er is geen andere gebruiker"*) en toch werd het een
Laravel-app. Wat ontbrak was het **doortrekken**: één gebruiker + lokale bestanden + 60 fps
sluit een HTTP-server als fundament uit. Die conclusie is het punt van deze standaard.

## Het artefact: `docs/intake.md`

De intake levert een bestand op in het project, met de vijf antwoorden **en** de conclusie
die eruit volgt. Zonder dat bestand mag `project:scaffold` niet draaien.

```markdown
# Intake — <project>
1. Draait op: desktop (Windows)
2. Gebruikers tegelijk: 1
3. Data: 76.000 lokale bestanden op de schijf van de gebruiker
4. Zwaarste operatie: grid vullen met duizenden miniaturen, bij elke mapklik
5. Vertraging voelbaar bij: scrollen — 60 fps

**Conclusie:** geen HTTP-server, geen webframework als fundament, geen staging/deploy.
Native desktop met echte threads. → type: desktop
```

## Beslisboom op werktype

| Antwoorden | Type | Fundament |
|---|---|---|
| Server · meerdere gebruikers · data in een database · vertraging per request | `server-webapp` | **Laravel** — JudoToernooi, Veen, Herdenkingsportaal, HavunAdmin, HavunCore |
| Desktop · één gebruiker · lokale bestanden · vertraging bij interactie | `desktop` | Native met echte threads en zonder GC-pauzes (Rust/Tauri, C#/WinUI, C++/Qt). **Geen** webframework, ook niet in een schil |
| Mobiel · één gebruiker · data via een API | `mobile` | React Native/Expo — Studieplanner, JudoScoreBoard |
| Geen UI · draait op verzoek | `library-cli` | Wat de taal van de aanroeper is; geen webinfra |

**Hybride telt als twee projecten:** een mobiele app met een backend is `mobile` + `server-webapp`,
elk met een eigen fundament (Studieplanner + Studieplanner-api). Niet één stack die beide doet.

## Rode vlaggen — je hebt de verkeerde stack

Deze verschijnen ná de keuze, en zijn geen bugs maar symptomen:

- je draait het framework **zonder** het framework om het snel genoeg te krijgen;
- er komt een **tweede runtime** naast de eerste, voor één taak;
- een **eigen poort** voor een proces dat bij de app hoort;
- een **vangnet** voor iets wat de infrastructuur zelf hoort te doen;
- de taal kan iets niet (FFI, threads, langlevende processen) en er komt een **sidecar**.

Bij de tweede van deze vlaggen: architectuurreview, geen commit. Tellen doe je in
`docs/omwegen.md` — `patterns/omwegen-tellen.md`.

## Een besluit noemt zijn aanname en zijn omkeerpunt

Elk architectuurbesluit legt vast: **(a)** de aanname waarop het rust, **(b)** de meting die
het zou omkeren. Geen vervaldatum — een datum verloopt zonder dat er iets veranderd is, en
dan heb je een besluit dat "verlopen" is maar nog klopt.

Besluit 001 van Vusista rustte op *"Laravel is een geschikt fundament voor lokale
bestandsverwerking op deze schaal"*. Die aanname stond er niet, dus is hij nooit getoetst
toen de metingen begonnen tegen te spreken. Sjabloon: `standards/docs-first.md`.

## Wat deze standaard níét is

- **Geen reden om bestaande projecten om te bouwen.** De zeven Laravel-projecten die op een
  server draaien voor meerdere gebruikers, staan waar ze horen. Deze standaard geldt bij een
  **nieuw** project of een **herbouwbesluit**.
- **Geen vrijbrief voor een nieuwe taal per project.** Een stack buiten Laravel/React Native
  is een besluit met een `decisions/`-doc erbij: wat het oplost, wat het kost aan onderhoud,
  en wie het over twee jaar nog kan lezen.
- **Geen vervanging van `robuust boven simpel`.** Die regel blijft — maar hij geldt óók *op*
  de stack, niet alleen *binnen* de stack. Zie `patterns/omwegen-tellen.md`.
