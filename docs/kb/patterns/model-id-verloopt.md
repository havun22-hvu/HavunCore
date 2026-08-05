---
title: Een model-ID is bederfelijk, en je test vangt het niet
type: pattern
scope: alle-projecten
tags: [ai, monitoring, deprecatie, externe-afhankelijkheid]
last_check: 2026-08-05
---

# Een model-ID is bederfelijk, en je test vangt het niet

**Elk AI-model-ID heeft een houdbaarheidsdatum. Zet 'm in de config, noteer de einddatum, en
bewaak de 404 — want je testsuite zwijgt.**

Op 05-08-2026 om 00:30 begon élke AI-proxy-call van HavunCore te falen met
`404 not_found_error: model: claude-3-haiku-20240307`. Oorzaak: Anthropic had dat model op
**19-04-2026** uitgefaseerd. 46 calls, 100% mislukt, AutoFix stil voor Herdenkingsportaal en
JudoToernooi. Gevonden bij een routinecheck van de productielogs, niet door een alert.

## Waarom geen test dit vangt

De suite faket de HTTP-laag (`Http::fake`) — terecht, want je wilt niet bij elke run de echte API
bellen. Maar daarmee test je je eigen code tegen een *verzonnen* antwoord. Of het model-ID nog
bestaat, is geen eigenschap van jouw code; het is een eigenschap van de wereld. **Groene tests
zeggen hier niets.** Dit is dezelfde klasse als [`bewaking-die-niets-meet.md`](bewaking-die-niets-meet.md):
het meetinstrument stond aan, maar keek de verkeerde kant op.

## Wat je wél doet

| Maatregel | Waarom |
|---|---|
| **Model-ID in config/env, nooit hardcoded in een service** | Eén plek om te wijzigen als het verloopt. Twee defaults (config *én* code) lopen uiteen |
| **Noteer de uitfaseerdatum bij de config-regel** | De datum staat in de leverancierdocs; zonder notitie vindt niemand 'm terug |
| **Alarmeer op de errorregel, niet op de test** | `AI Proxy: Claude API error` met status 404 is het enige echte signaal. Eén health-alert erop is genoeg |
| **Bij twijfel over een model-ID: opzoeken, niet uit het hoofd** | Model-IDs veranderen sneller dan een taalmodel z'n kennis. In Claude Code: de `claude-api`-skill |

## Wat je niet doet

**Een testsuite uitbreiden om dit te vangen.** Een test die de echte API belt is traag, kost geld
en faalt op elke netwerkhapering — en hij vangt het pas als het al stuk is. Monitoring hoort hier,
geen test.

## Zie ook

- [`bewaking-die-niets-meet.md`](bewaking-die-niets-meet.md) — hetzelfde patroon: het instrument
  meet iets anders dan je denkt
- [`../reference/ai-proxy.md`](../reference/ai-proxy.md) — de config van HavunCore's AI-proxy
