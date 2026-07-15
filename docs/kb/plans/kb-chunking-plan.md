---
title: "Plan: KB-chunking — de staart van lange documenten weer vindbaar maken"
type: plan
scope: havuncore
last_updated: 2026-07-15
---

# Plan: KB-chunking

> **Status: geïmplementeerd 15-07-2026.** 1305 tests groen. HavunCore herindexeerd: 444 docs →
> 3216 chunks, 154 gesplitst, 0 op de TF-fallback. Geverifieerd: de `## Support`-sectie op 41k
> tekens diep in `CHANGELOG.md` is nu de eerste treffer op *"github discussions support issues
> melden"* (64%) — die was onvindbaar. Overige projecten herindexeren draait.
>
> Aanleiding: het openstaande punt uit `reference/doc-intelligence-embedding-fallback-bug.md`.

## Conclusie

Lange documenten worden alleen op hun **begin** geëmbed. Gemeten op de huidige index
(3172 rijen, 7,97 mln tekens):

| Embed-grens | Docs afgekapt | Inhoud onvindbaar |
|-------------|---------------|-------------------|
| 8000 tekens (beste geval) | 245 | **22%** |
| 4000 tekens | 568 | **41%** |
| 2000 tekens (slechtste geval) | 967 | **59%** |

De echte grens ligt per document anders — Ollama rekent in tokens (~2048), niet in tekens,
en dichte code haalt die grens veel eerder dan proza. Realistisch is dus **~⅓ van de KB
onvindbaar**, en juist in de documenten waar het meest naar gezocht wordt:

```
92.201  judotoernooi/laravel/routes/web.php
84.348  aeterna/PLAN.md
67.057  havunclub/docs/business-rules.md      → ~8% vindbaar
58.471  judoscoreboard/.claude/context.md
54.315  havunadmin/routes/web.php
```

**Aanpak: een aparte tabel `doc_chunks`, niet meer rijen in `doc_embeddings`.**

## Waarom een aparte tabel (correctie op de handover)

De handover schreef "chunking = meerdere rijen per bestand". Dat is de dure variant. Een
inventarisatie van alle code die `doc_embeddings` raakt vond ~30 plekken die aannemen dat
één rij = één bestand. De drie ergste:

1. **`IssueDetector::detectBrokenLinks()`** (`IssueDetector.php:326`) parst `content` als een
   volledig markdown-bestand — het strippen van code-fences gaat met één regex over de hele
   tekst. Een chunkgrens knipt links (`](`) en fences doormidden → een stroom **valse**
   broken-link-issues. Ook `detectDuplicates` (`:67`) zou chunks van hetzelfde bestand als
   duplicaten van elkaar zien, met een O(n²) die meegroeit met het chunk-aantal.
2. **`updateOrCreate(['project','file_path'])`** op `DocIndexer.php:329` en `:611` — de unique
   constraint in z'n zuiverste vorm; die klapt alle chunks op één rij.
3. **Elke `COUNT(*)`-als-bestandsaantal** in `DocIntelligenceController.php` (`:127`, `:145`,
   `:167-178`) plus de tests die die getallen vastpinnen. `total_files` zou chunks tellen.

Met een aparte tabel blijft `doc_embeddings` precies wat het is — één rij per bestand, met de
volledige `content` — en verdwijnt die hele lijst. Alleen `search()` gaat elders kijken.

## Ontwerp

### Schema — nieuwe tabel `doc_chunks` (connectie `doc_intelligence`)

| Kolom | Type | Toelichting |
|-------|------|-------------|
| `id` | id | |
| `doc_embedding_id` | foreignId, cascade delete | bestand weg → chunks weg, gratis |
| `chunk_index` | int | 0-based volgorde binnen het bestand |
| `heading` | string, nullable | het koppad (`## Ontwerp › ### Schema`) voor de snippet |
| `content` | text | de chunk-tekst |
| `embedding` | json | 768-dim vector van deze chunk |
| `embedding_model` | string | `nomic-embed-text` of `tfidf-fallback` |
| `token_count` | int | |

Unique: `['doc_embedding_id','chunk_index']`. Index op `doc_embedding_id`.

`doc_embeddings` verandert **niet**. De bestaande `embedding`-kolom blijft de
document-embedding (afgekapt begin) en dient als vangnet zolang een bestand nog geen chunks heeft.

### Chunk-strategie

- **Doel ~2500 tekens per chunk, hard max 4000.** Ruim onder de gemeten Ollama-grens, zodat de
  adaptieve truncatie (8000→4000→2000) niet meer hoeft te vuren. Die blijft staan als vangnet.
- **Markdown:** splitsen op koppen (`#`..`######`). Een sectie boven het max wordt verder op
  alinea's gesplitst; een alinea boven het max op zinnen. Nooit midden in een code-fence knippen.
- **Code:** splitsen op regelgrenzen rond het doel, want een `routes/web.php` van 92k heeft geen
  koppen. De bestaande `extractCodeSummary()` blijft chunk 0 — dat is de beste samenvatting die er is.
- **Elke chunk krijgt een context-prefix** (`{file_path} › {koppad}`) vóór het embedden. Een chunk
  uit het midden van `business-rules.md` betekent zonder die kop weinig; dit is contextual
  retrieval, licht uitgevoerd. De prefix gaat **niet** in de opgeslagen `content` — alleen in wat
  naar Ollama gaat.
- **Geen overlap.** Koppen zijn al natuurlijke grenzen; overlap kost ruimte en levert dubbele hits.

### Zoeken

`DocIndexer::search()` scoort **chunks**, groepeert dan per bestand en houdt per bestand de
beste chunk over. Het resultaat blijft dus één regel per bestand — `--limit=5` betekent nog
steeds 5 documenten, niet 5 chunks van hetzelfde document. Winst bovenop vindbaarheid: de
**snippet wordt de relevante passage** in plaats van de eerste 200 tekens van het bestand
(nu vrijwel altijd de YAML-frontmatter — zie de `docs:search`-output in deze sessie, waar de
preview van elke treffer `---\ntitle: ...` is).

Bestanden zonder chunks vallen terug op `doc_embeddings.embedding`, zodat zoeken blijft werken
tijdens de herindexering.

### Herindexering

3172 bestanden opnieuw embedden bij ~2500 tekens per chunk ≈ **6-8k Ollama-calls**. Dat is een
paar uur; `docs:index` draait per project, dus dat kan gespreid. Self-healing volgens het patroon
dat er al is (`needsEmbeddingUpgrade`): een `doc_embeddings`-rij zonder chunks wordt bij de
eerstvolgende index-run gechunkt, ook als `content_hash` gelijk is. Geen `--force` nodig,
geen big-bang.

## Stappen — allemaal gedaan

1. ✅ **Migratie** `create_doc_chunks_table` + model `DocChunk` + `DocEmbedding::chunks()`.
2. ✅ **`DocumentChunker`** — 11 unit-tests, geen DB en geen Ollama nodig.
3. ✅ **`DocIndexer`** — `indexChunks()` na het schrijven van de rij; delete+insert, want een
   bewerkt bestand kan krimpen en een verweesde chunk 7 blijft anders matchen op tekst die
   niet meer bestaat. Skip-check uitgebreid met `needsChunking()`.
4. ✅ **`search()`** — scoort chunks, houdt per document de beste over, valt terug op de
   documentvector zolang een rij nog geen chunks heeft.
5. ✅ **Tests** — `ChunkedSearchTest` (7) bewijst de staart, de één-resultaat-per-document-regel,
   het opruimen van stale chunks en de cascade bij verwijderen.
6. ✅ **Herindexeren** + geverifieerd (zie status bovenaan).
7. ✅ **Docs** — het openstaande punt in de reference-doc is afgesloten.

## Wat tijdens het bouwen bijgesteld is

- **Code-bestanden worden óók gechunkt.** Het plan ging ervan uit dat `extractCodeSummary()`
  code klein maakt. Dat doet het niet: JudoToernooi's `routes/web.php` levert een samenvatting
  van 92k tekens op, die net zo hard werd afgekapt.
- **Eén chunk = hergebruik de documentvector.** De eerste versie embedde elk kort document
  twee keer (één keer als document, één keer als zijn enige chunk) — dat verdubbelde elke
  index-run voor de 82% van de bestanden die in één chunk passen. Twee bestaande tests vingen
  dit: ze telden exact 2× en 6× zoveel Ollama-calls als verwacht.
- **`test_over_long_content_is_retried_shorter`** meet nu een document ónder de chunk-grens. Het
  test de retry-ladder van `generateOllamaEmbedding()`, en chunk-calls vertroebelden die telling.

## Wat dit plan bewust NIET doet

- **`scripts/HavunAIBridge.php` (`:119`) en `backend/src/routes/orchestrate.js` (`:26`)** queryen
  `doc_embeddings` rechtstreeks en blijven dus gewoon werken — ze profiteren alleen niet van
  chunking. Los punt: `orchestrate.js` haalt 15 rijen **zonder `ORDER BY`** op en noemt dat "top 15
  fragmenten"; dat is nu al willekeurig. Eigen fix, niet hier.
- **De TF-fallback vervangen.** Blijft zoals hij is.
- **Een vector-index (sqlite-vss e.d.).** `search()` laadt alle rijen in PHP en rekent daar de
  cosinus uit. Met chunks worden dat er ~2× zoveel. Bij 3172 rijen is dat nu geen probleem;
  wordt het dat wel, dan is dát de volgende taak — niet deze.

## Openstaand voor Henk

Geen. Dit raakt geen `.env`, geen prod-data, geen dependencies: één nieuwe tabel in de lokale
`doc_intelligence.sqlite`, één nieuwe service, en `search()` die elders kijkt. De herindexering
draait lokaal tegen Ollama.
