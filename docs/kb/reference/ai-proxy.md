# AI Proxy API

> Centrale Claude API proxy voor alle Havun projecten.

## Overzicht

HavunCore biedt een centrale AI proxy die Claude API calls afhandelt voor alle projecten. Dit zorgt voor:
- Eén API key beheer
- Centraal usage logging
- Rate limiting per tenant
- Kosten inzicht

## Endpoints

### POST /api/ai/chat

Stuur een bericht naar Claude.

**Request:**
```json
{
  "tenant": "infosyst",
  "message": "Wat is de huidige politieke situatie?",
  "context": ["Extra context regel 1", "Extra context regel 2"],
  "system_prompt": "Optionele custom system prompt",
  "max_tokens": 1024
}
```

**Parameters:**

| Parameter | Type | Verplicht | Beschrijving |
|-----------|------|-----------|--------------|
| tenant | string | Ja | Project identifier: `infosyst`, `herdenkingsportaal`, `havunadmin`, `havuncore`, `judotoernooi` |
| message | string | Ja | Gebruikersvraag (max 10.000 tekens) |
| context | array | Nee | Extra context als array van strings |
| system_prompt | string | Nee | Override default system prompt |
| max_tokens | int | Nee | Max response tokens (100-4096, default 1024) |

**Response:**
```json
{
  "success": true,
  "response": "Claude's antwoord hier...",
  "usage": {
    "input_tokens": 150,
    "output_tokens": 200,
    "execution_time_ms": 1234
  }
}
```

**Error responses:**

| Status | Reden |
|--------|-------|
| 422 | Validatie fout |
| 429 | Rate limit overschreden |
| 503 | AI service niet beschikbaar |

### GET /api/ai/usage

Haal usage statistieken op voor een tenant.

**Parameters:**

| Parameter | Type | Verplicht | Beschrijving |
|-----------|------|-----------|--------------|
| tenant | string | Ja | Project identifier |
| period | string | Nee | `hour`, `day`, `week`, `month` (default: `day`) |

**Response:**
```json
{
  "success": true,
  "tenant": "infosyst",
  "period": "day",
  "stats": {
    "total_requests": 42,
    "total_input_tokens": 5000,
    "total_output_tokens": 8000,
    "total_tokens": 13000,
    "avg_execution_time_ms": 1500
  }
}
```

### GET /api/ai/health

Health check voor de AI service.

**Response:**
```json
{
  "success": true,
  "status": "ok",
  "api_configured": true,
  "model": "claude-3-haiku-20240307"
}
```

## Rate Limiting

Per tenant geldt een rate limit van 60 requests per minuut (configureerbaar).

Bij overschrijding krijg je:
```json
{
  "success": false,
  "error": "Rate limit exceeded. Try again later.",
  "retry_after": 60
}
```

## Tenant System Prompts

Elke tenant heeft een default system prompt:

| Tenant | Focus |
|--------|-------|
| infosyst | Maatschappelijke/politieke informatie, onderbouwd met bronnen |
| herdenkingsportaal | Memorial beheer, monument editor, empathisch |
| havunadmin | Facturatie, klantenbeheer, technisch |
| havuncore | Centrale hub, Task Queue, orchestratie |
| judotoernooi | AutoFix: production error analyse en fix voorstellen |

Je kunt de default overriden met `system_prompt` parameter.

## Integratie voorbeeld

### PHP/Laravel

```php
use Illuminate\Support\Facades\Http;

$response = Http::post('https://havuncore.havun.nl/api/ai/chat', [
    'tenant' => 'infosyst',
    'message' => 'Leg uit wat de Eerste Kamer doet',
    'context' => [
        'De gebruiker is geïnteresseerd in politiek',
    ],
]);

$answer = $response->json('response');
```

### JavaScript/Fetch

```javascript
const response = await fetch('https://havuncore.havun.nl/api/ai/chat', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    tenant: 'infosyst',
    message: 'Wat zijn de taken van een burgemeester?',
  }),
});

const data = await response.json();
console.log(data.response);
```

## Configuratie

In `.env`:
```
CLAUDE_API_KEY=sk-ant-...
CLAUDE_MODEL=claude-3-haiku-20240307
CLAUDE_RATE_LIMIT=60
```

In `config/services.php`:
```php
'claude' => [
    'api_key' => env('CLAUDE_API_KEY'),
    'model' => env('CLAUDE_MODEL', 'claude-3-haiku-20240307'),
    'rate_limit' => env('CLAUDE_RATE_LIMIT', 60),
],
```
