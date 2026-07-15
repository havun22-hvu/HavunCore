---
title: KB draait op TF-fallback i.p.v. Ollama-embeddings (ontdekt 15-07-2026)
type: reference
scope: havuncore
last_check: 2026-07-15
---

# Doc Intelligence — alle embeddings zijn TF-maps, niet nomic-embed-text

> **Status:** ONTDEKT, NIET GEFIXT. Wacht op Henk (herindexering kost tijd + 2758 Ollama-calls).
> **Impact:** de KB-zoekfunctie — de kernfunctie van HavunCore, verplicht bij elke taak volgens
> `CLAUDE.md` — doet **keyword-matching, geen semantisch zoeken**. Al maanden.

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

Geverifieerd op 15-07: Ollama draait, `nomic-embed-text:latest` is aanwezig, en een directe
`POST /api/embeddings` geeft een correcte 768-dim vector. De fallback is dus ooit ingeslagen
(Ollama uit/timeout tijdens indexeren) en daarna nooit meer teruggedraaid.

## Voorgestelde fix

1. **Splits generatie en labeling** zodat het label de waarheid vertelt:
   ```php
   $embedding = $this->generateOllamaEmbedding($content);   // null bij falen
   $model = $embedding ? $this->embeddingModel : 'tfidf-fallback';
   $embedding ??= $this->generateLocalEmbedding($content);
   ```
2. **Herstel-trigger:** bij het skippen op `content_hash` óók herindexeren als
   `embedding_model === 'tfidf-fallback'` en Ollama nu beschikbaar is. Anders blijft degraded
   voor altijd degraded.
3. **Eenmalige `docs:index --force`** over alle projecten om de 2758 TF-maps te vervangen door
   echte vectoren. Kost ~2758 Ollama-calls; draaien wanneer het uitkomt.
4. Overweeg te falen i.p.v. stil terug te vallen wanneer Ollama onbereikbaar is tijdens een
   expliciete indexeer-actie — een stille degradatie van de kernfunctie is erger dan een foutmelding.

## Waarom dit ertoe doet

`CLAUDE.md` schrijft voor: *"VOORDAT je code leest, schrijft of voorstelt: `docs:search`"*, met als
rechtvaardiging "het bespaart tokens en voorkomt fouten". Die belofte leunt op semantisch zoeken.
Met keyword-matching mist de KB documenten die anders geformuleerd zijn dan de zoekterm — precies
wanneer je ze het hardst nodig hebt. Waargenomen deze sessie: `docs:search "poort register"` gaf als
beste treffer een PHP-bestand; de security-review was onvindbaar op zijn eigen titelwoorden.
