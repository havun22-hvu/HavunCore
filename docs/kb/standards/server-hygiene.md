---
title: Server-hygiëne — prod-checkouts blijven schoon
type: standard
scope: alle-projecten
last_check: 2026-07-15
---

# Server-hygiëne — BINDEND

**Doel:** `git status` op een prod-checkout is **leeg**. Is hij dat niet, dan is dat een **signaal**,
geen achtergrondruis. Zodra "dirty" normaal wordt, ziet niemand meer wat er écht mis is.

## Hoe het misging (15-07-2026)

Acht van de acht prod-checkouts waren vervuild, met **17 stashes** verspreid. Wat daar stond:
874 MB APK's, 34 MB OTA-bundles, de gebouwde PWA, een lettertype, en een **landingspagina die op de
server was aangepast en nergens anders bestond**. Alles door elkaar — live content en echte rommel
waren niet meer te onderscheiden. `git clean -fd` zou een outage hebben veroorzaakt.

## De drie regels

### 1. Wat een deploy of upload produceert, hoort in `.gitignore`

Build-output (`public/build`, `dist`, de gersyncte PWA), gedeployde APK's/OTA-bundles en
user-uploads horen **niet** in git en mogen **nooit** als "dirty" verschijnen. Staan ze er wel in,
dan wordt de status betekenisloos.

> Uitzondering: een asset die de app **nodig heeft** om te draaien (een lettertype, een favicon)
> hoort juist **wél** in git. Vuistregel: **kan een verse `git clone` + deploy dit reproduceren?**
> Ja → gitignore. Nee → in git.

### 2. Wijzig nooit content direct op productie

Prod-checkouts kunnen **niet pushen** (read-only deploy keys — dat is bewust). Een wijziging die je
daar maakt, bestaat dus nergens anders en gaat bij de eerstvolgende `reset --hard` verloren.

Moet het toch (hotfix, statische pagina)? Haal hem **in dezelfde sessie** terug naar git:

```bash
# op de server
git -C <pad> stash            # of: diff > /tmp/fix.patch
git -C <pad> bundle create /tmp/fix.bundle <range>
# lokaal
scp root@188.245.159.115:/tmp/fix.bundle . && git fetch /tmp/fix.bundle && git push
```

Laat het **niet** tot de volgende sessie liggen.

### 3. Een stash is tijdelijk — ruim hem op in dezelfde sessie

Deploy-scripts stashen bij drift. Dat is prima als vangnet, maar:

- Los elke stash **dezelfde sessie** op: toepassen, of droppen met een reden in de handover.
- Een stash die blijft liggen is **onvindbaar** — hij staat in geen enkele status, en niemand weet
  nog wat erin zit. Ná een paar maanden kost het meer om uit te zoeken dan het waard is.
- Moet er tóch een blijven staan: noteer in de handover **wat** en **waarom**, met een vervaldatum.

## Bewaking

`/start` controleert de prod-checkouts (dirty + stashes) van de projecten die op de server draaien
en meldt afwijkingen. Zo blijft het een signaal in plaats van een verrassing na drie maanden.

## Prod bijwerken — `/end` vraagt er actief om

**Henk werkt prod uit zichzelf te weinig bij** (zijn eigen constatering, 15-07). Een notitie in de
handover werkt niet: die leest hij niet. Daarom **vraagt `/end` het elke sessie actief**.

Stand op 15-07 — tien checkouts liepen achter:

| Checkout | Achter |
|---|---|
| vpdupdate | **49 commits** |
| havuncore/production | 24 |
| infosyst/production | 21 |
| havun.nl | 17 |
| herdenkingsportaal, safehavun, studieplanner | 10 elk |
| havuncore/webapp | 8 |

Zo'n achterstand is niet alleen "niet up-to-date": hij maakt élke deploy riskanter (grotere batch,
meer migraties tegelijk, moeilijker terug te draaien), en gefixte bugs staan maanden niet live.

**Regels:**
- Vragen doe je alleen als er **code** klaarstaat. Puur docs/`.claude`/KB → niet vragen, dat lift
  mee met de volgende deploy.
- Benoem **wat** het oplost en **wat het risico is** (migraties? build? breaking?) — Henk moet ja/nee
  kunnen zeggen zonder zelf te graven.
- Zit er een **security-fix** bij → dat expliciet zeggen. Dat is een reden om nu te gaan.
- **Nooit deployen zonder go.** Vragen is verplicht, doorduwen niet.

## Bij het opruimen: nooit blind wissen

Volgorde, altijd:
1. **Uitzoeken wát het is** — live content, deploy-output of echte rommel?
2. **Live content veiligstellen** (naar git) vóór er iets verdwijnt.
3. **Deploy-output gitignoren** — niet wissen; de site heeft het nodig.
4. Pas dan weg wat echt weg kan.

Wat de enige kopie is, wordt nooit verwijderd. Ook niet op "alles opruimen".
