---
title: Beslissing: Vector-based Search via Ollama Embeddings — Evaluatie
type: decision
scope: havuncore
last_check: 2026-04-22
---

# Beslissing: Vector-based Search via Ollama Embeddings — Evaluatie

> **Bron:** VP-03.4 (verbeterplan-q2-2026.md)
> **Datum:** 17-04-2026
> **Status:** Geëvalueerd — **NIET geïntroduceerd in Q2**, heroverwegen Q4 2026

## Context

Huidige `docs:search` (DocSearchCommand) gebruikt TF-IDF-achtige term-matching
met een `Relevance: X%` score. Werkt goed voor exacte termen maar mist
semantische matches ("betaling" vs. "Mollie webhook").

Gemini (v1.0) suggereerde vector-search op basis van Ollama embeddings
(Command-R / nomic-embed-text) om dit gat te dichten.

## Afweging

| Criterium | Huidige TF-IDF | Vector-search (Ollama) |
|-----------|----------------|------------------------|
| Indexatietijd | Seconden | Minuten (per wijziging re-embed) |
| Zoekvraag-latency | <50 ms | 200-800 ms (Ollama call) |
| Geheugen | Verwaarloosbaar | ~1-2 GB voor embedmodel |
| Exacte term-match | Sterk | Matig (semantisch, mist exact) |
| Semantische match | Zwak | Sterk |
| Onderhoud | Nul | Ollama service moet draaien |
| Cross-project bruikbaar | Ja | Ja — ook via HavunCore API |
| Kosten | Nul | Nul (lokaal), tenzij cloud-embed |

## Actuele pijn

Bij het ontwikkelen in de laatste sessies (Q1+Q2 2026) leverde
`docs:search` steeds direct de juiste top-3 resultaten. Voorbeelden:

- "verbeterplan VP status" → `verbeterplan-q2-2026.md` (57%)
- "havun quality standards" → correct pad (58%)
- "security audit checklist" → `security-headers-check.md` (51%)

De bottleneck bij het lezen is niet de relevance-matching maar het
**aantal kennisbank-bestanden** (80+). Dat lost een vector-index niet op —
dat is een documentatie-inflatie-probleem (VP-03.1 + VP-03.2).

## Beslissing

**NIET introduceren in Q2 2026** om de volgende redenen:

1. TF-IDF voldoet in de praktijk voor ~80% van de zoekvragen.
2. Toevoeging van een Ollama-afhankelijkheid verzwaart het setup-proces
   voor zowel lokaal (vereiste service) als CI (extra stap in workflow).
3. De essentiële-docs-lijst (VP-03.2) dekt de overige 20% zonder extra
   dependency.
4. De mutation-baseline (VP-16) en branch-model (VP-01) hebben hogere ROI.

## Heroverwegen

Triggers om deze beslissing Q4 2026 te herzien:

- Meer dan 150 KB-bestanden (nu ~80)
- Meetbare klachten over "ik vond X niet"
- Ollama al draaiend voor andere features (bijv. AutoFix embeddings)
- Gratis embed-modellen met <100 ms latency beschikbaar

## Acceptatiecriterium VP-03.4

- [x] Evaluatierapport vector-search beschikbaar (dit document)
