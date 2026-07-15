---
title: KB draait op TF-fallback i.p.v. Ollama-embeddings (ontdekt 15-07-2026)
type: reference
scope: havuncore
last_check: 2026-07-15
---

# Doc Intelligence — alle embeddings zijn TF-maps, niet nomic-embed-text

> **Status:** GEFIXT 15-07-2026 (commit `2c43318`) + herindexering uitgevoerd.
> **Impact (was):** de KB-zoekfunctie — de kernfunctie van HavunCore, verplicht bij elke taak
> volgens `CLAUDE.md` — deed **keyword-matching, geen semantisch zoeken**. Maandenlang, ongemerkt.

## Hoe het ontdekt is

Een nieuw KB-document (`scoreboard-api-security-review-2026-07-15.md`) bleek onvindbaar via
`docs:search`, ook op termen die letterlijk in de titel staan. Het document zat wél correct in
de index, met een up-to-date `content_hash` en een gevulde `embedding`.

Bij vergelijking: mediane embedding-lengte 15208 tekens, deze 2902. Beide echter `dims=100` —
en de **keys zijn woorden** (`de`, `geen`, `op`, `token`), geen numerieke indices. Een echte
`nomic-embed-text`-vector is 768 floats.

## De bug

`DocIndexer::generateEmbedding()` probeert Ollama en valt bij een fout terug op
`generateLocalEmbedding()` — een woordfrequentie-map van de top-100 woorden. Dat is een prima
fallback. Het label is het probleem (`DocIndexer.php:352` en `:632`):

```php
'embedding_model' => $embedding ? $this->embeddingModel : 'tfidf-fallback',
```

`generateLocalEmbedding()` geeft **altijd** een niet-lege array terug, dus `$embedding` is altijd
truthy. Gevolg:

1. Elke fallback wordt gelabeld als `nomic-embed-text`. De waarde `'tfidf-fallback'` is
   **onbereikbare code** — hij komt in de hele database niet voor (2758/2758 = `nomic-embed-text`).
2. Je kunt dus niet zien welke docs degraded zijn. Er is geen signaal, geen log-spoor achteraf.
3. **Het herstelt nooit vanzelf:** `indexFile()` skipt op `content_hash`. Zolang het bestand niet
   wijzigt, wordt de TF-map nooit vervangen — ook niet nu Ollama wél werkt.

### Derde oorzaak: Ollama's context is 2048 tokens, niet 8192

Tijdens het herstel bleek de fallback niet "ooit een keer" te zijn ingeslagen — hij sloeg
**structureel** toe op elk groter document. Ollama serveert `nomic-embed-text` met een context van
~2048 tokens (niet de 8192 die het model zelf aankan) en **weigert** langere invoer met een HTTP 500
in plaats van af te kappen:

```
{"error":"the input length exceeds the context length"}
```

De code kapte af op **8000 tekens** met de comment *"nomic-embed-text: 8192 tokens"* — een
verwarring tussen tekens en tokens. Alles boven ~2048 tokens viel dus terug op de TF-map.
Gemeten grens lag voor een doorsnee `.claude/context.md` tussen 5000 en 6000 tekens; voor dichte
code ligt hij lager. `options.num_ctx = 8192` meesturen helpt niet — Ollama negeert dat voor
embeddings.

## De fix (commit `2c43318`)

1. **Generatie en labeling gesplitst** (`generateOllamaEmbedding()` geeft `null` bij falen) zodat
   het label de waarheid vertelt.
2. **Herstel-trigger** in beide `indexFile()`/`indexCodeFile()`-skips. Omdat het label van
   historische rijen liegt, wordt de fallback óók op **vorm** herkend: een echte vector is een
   `array_is_list` van floats, de fallback een `woord => gewicht`-map (`isFallbackEmbedding()`).
   Daardoor is het self-healing — geen `--force` of relabel-migratie nodig.
3. **Adaptieve truncatie:** 8000 → 4000 → 2000 tekens bij een context-lengte-fout. Een echte
   embedding van de eerste helft is beter dan een woordmap van het geheel. Andere fouten
   (model weg, Ollama down) worden **niet** herprobeerd — die falen op elk formaat identiek.

Geverifieerd op Vusista: 82/82 rijen dragen nu echte 768-dim vectoren (was 0/82; eerst 77, en na
de truncatie-fix ook de laatste 5 grote `.claude`-bestanden).

Tests: `tests/Feature/DocIntelligence/EmbeddingFallbackLabelTest.php` (7).

> **Openstaand punt — plan geschreven 15-07:** documenten langer dan ~2000-8000 tekens worden nog
> steeds alleen op hun **begin** geëmbed — de staart is onvindbaar. Dat gold altijd al (de oude code
> kapte ook af), maar het is nu zichtbaar. Gemeten: **22-59% van alle KB-inhoud**, afhankelijk van
> waar Ollama's tokengrens per document valt. De oplossing is **chunking**, via een aparte tabel
> `doc_chunks` — niet extra rijen in `doc_embeddings`, want te veel code neemt aan dat één rij één
> bestand is. Plan: `docs/kb/plans/kb-chunking-plan.md` (wacht op "ga maar").

## Waarom dit ertoe doet

`CLAUDE.md` schrijft voor: *"VOORDAT je code leest, schrijft of voorstelt: `docs:search`"*, met als
rechtvaardiging "het bespaart tokens en voorkomt fouten". Die belofte leunt op semantisch zoeken.
Met keyword-matching mist de KB documenten die anders geformuleerd zijn dan de zoekterm — precies
wanneer je ze het hardst nodig hebt. Waargenomen deze sessie: `docs:search "poort register"` gaf als
beste treffer een PHP-bestand; de security-review was onvindbaar op zijn eigen titelwoorden.
