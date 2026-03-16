# KB Search API Reference

> Remote toegang tot de HavunCore kennisbank voor Claude sessies op andere locaties.

## Status: ACTIEF

## Waarom?

Claude sessies op deze PC gebruiken `php artisan docs:search` direct.
Op andere locaties (reis-PC, VS Code Remote) is dat niet mogelijk.
De KB moet via HTTP bereikbaar zijn vanaf elke locatie.

## Bestaande situatie

| Wat | Status |
|-----|--------|
| `GET /api/docs/search` | Bestaat, geen auth |
| `GET /api/docs/issues` | Bestaat, geen auth |
| `GET /api/docs/stats` | Bestaat, geen auth |
| `GET /api/docs/read` | Bestaat, geen auth |
| Embeddings | Ollama (lokaal) + TF-IDF fallback |
| Indexering server | Handmatig, geen cron |

## Wat moet er gebeuren

### 1. Authenticatie toevoegen

Bearer token, zelfde patroon als Vault API:

```
GET /api/docs/search?q=auth+middleware&project=herdenkingsportaal
Authorization: Bearer hvn_kb_xxxxx
```

- Nieuwe token specifiek voor KB access
- Opslaan in Vault (zodat projecten op reis-PC het via Vault bootstrap krijgen)
- Zonder token: 401 Unauthorized

### 2. Automatische indexering op server

Cron job op de server:

```bash
# Elke 6 uur herindexeren
0 */6 * * * cd /var/www/development/HavunCore && php artisan docs:index all --no-code 2>&1 >> storage/logs/docs-index.log
```

- `--no-code` omdat code-indexering Ollama vereist (niet op server)
- MD files indexeren met TF-IDF (geen Ollama nodig)
- Loggen naar storage/logs

### 3. Endpoints (bestaand, alleen auth toevoegen)

#### Search

```
GET /api/docs/search?q=zoekterm&project=optional&limit=5
Authorization: Bearer hvn_kb_xxxxx
```

Response:
```json
{
  "results": [
    {
      "file": "docs/kb/projects/herdenkingsportaal.md",
      "project": "herdenkingsportaal",
      "relevance": 0.87,
      "preview": "Auth guard = web (default)..."
    }
  ]
}
```

#### Read

```
GET /api/docs/read?project=herdenkingsportaal&path=docs/SPEC.md
Authorization: Bearer hvn_kb_xxxxx
```

Response:
```json
{
  "content": "# Herdenkingsportaal Spec\n...",
  "file": "docs/SPEC.md",
  "project": "herdenkingsportaal"
}
```

## Gebruik door Claude op andere locatie

In CLAUDE.md van elk project:

```bash
# Als lokaal (D:\GitHub\HavunCore bestaat):
cd D:\GitHub\HavunCore && php artisan docs:search "zoekterm" --project=projectnaam

# Als remote (geen lokale HavunCore):
curl -s -H "Authorization: Bearer $HAVUNCORE_KB_TOKEN" \
  "https://havuncore.havun.nl/api/docs/search?q=zoekterm&project=projectnaam"
```

## Implementatie stappen

1. [x] Bearer token auth toevoegen aan DocIntelligenceController
2. [x] Config key `DOC_INTELLIGENCE_API_TOKEN` in `config/services.php`
3. [x] Op-reis-workflow.md updaten met KB remote access
4. [x] `DOC_INTELLIGENCE_API_TOKEN` instellen in server `.env`
5. [x] Cron job instellen op server: `0 */6 * * *` docs:index all --no-code
6. [x] Getest op server: zonder token → 401, met token → resultaten
7. [x] CLAUDE.md globaal updaten met remote fallback instructie
8. [ ] KB token toevoegen aan credentials.vault (handmatig bij volgende USB update)

## Gerelateerd

- [api-vault.md](api-vault.md) - Vault API (zelfde auth patroon)
- [doc-intelligence-system.md](../projects/doc-intelligence-system.md) - Doc Intelligence systeem
- [doc-intelligence-setup.md](../runbooks/doc-intelligence-setup.md) - Setup instructies

---

*Aangemaakt: 16 maart 2026*
