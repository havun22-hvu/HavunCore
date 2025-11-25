# HavunCore Vault System

**Version:** 1.0.0
**Last Updated:** 2025-11-26

## Overview

De Vault is een centraal secrets en configuratie management systeem binnen HavunCore. Het biedt:

- **Secrets Management** - Encrypted opslag van API keys, wachtwoorden, tokens
- **Config Templates** - Herbruikbare configuraties voor nieuwe projecten
- **Project Access Control** - Per-project toegang tot specifieke secrets
- **Audit Logging** - Volledige trail van wie wat wanneer heeft opgevraagd

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    HavunCore Vault                          │
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Secrets    │  │   Configs    │  │   Projects   │      │
│  │  (encrypted) │  │   (JSON)     │  │  (access)    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│                                                             │
│  🔐 AES-256 encryption (Laravel Crypt)                      │
│  📝 Access logs for audit trail                             │
│  🔑 Per-project API tokens                                  │
└─────────────────────────────────────────────────────────────┘
           ↑              ↑              ↑
     HavunAdmin    Herdenkings-    NieuwProject
     (token A)       portaal       (token C)
                    (token B)
```

## Database Tables

### vault_secrets
Encrypted key-value store voor gevoelige data.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| key | string | Unique identifier (e.g., `mollie_api_key`) |
| value | text | Encrypted value |
| category | string | Category for grouping (e.g., `payment`, `storage`) |
| description | text | Human-readable description |
| is_sensitive | boolean | Whether to mask in UI |

### vault_configs
JSON configuratie templates.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Unique name (e.g., `laravel_base`) |
| type | string | Type: `laravel`, `nodejs`, `shared` |
| config | json | Configuration structure |
| description | text | Description |

### vault_projects
Project registratie en toegangsrechten.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| project | string | Project name (e.g., `havunadmin`) |
| secrets | json | Array of secret keys this project can access |
| configs | json | Array of config names this project can access |
| api_token | string | Unique API token (hvn_xxxx...) |
| is_active | boolean | Whether access is enabled |
| last_accessed_at | timestamp | Last API access |

### vault_access_logs
Audit trail van alle toegang.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| project | string | Project that accessed |
| action | string | `read`, `write`, `delete` |
| resource_type | string | `secret`, `config`, `bootstrap` |
| resource_key | string | Which resource |
| ip_address | string | Client IP |
| created_at | timestamp | When |

## API Endpoints

### Project Endpoints (require Bearer token)

```bash
# Get all secrets the project has access to
GET /api/vault/secrets
Authorization: Bearer hvn_xxxxxx

# Get specific secret
GET /api/vault/secrets/{key}
Authorization: Bearer hvn_xxxxxx

# Get all configs
GET /api/vault/configs
Authorization: Bearer hvn_xxxxxx

# Get specific config
GET /api/vault/configs/{name}
Authorization: Bearer hvn_xxxxxx

# Bootstrap - get everything at once
GET /api/vault/bootstrap
Authorization: Bearer hvn_xxxxxx
```

### Admin Endpoints

```bash
# List all secrets (masked values)
GET /api/vault/admin/secrets

# Create secret
POST /api/vault/admin/secrets
{
  "key": "mollie_live_key",
  "value": "live_xxxxx",
  "category": "payment",
  "description": "Mollie live API key"
}

# Update secret
PUT /api/vault/admin/secrets/{key}
{
  "value": "new_value"
}

# Delete secret
DELETE /api/vault/admin/secrets/{key}

# List projects
GET /api/vault/admin/projects

# Create project
POST /api/vault/admin/projects
{
  "project": "nieuw-project",
  "secrets": ["mollie_live_key", "hetzner_password"],
  "configs": ["laravel_base"]
}
# Returns: { "api_token": "hvn_xxxx" } - SAVE THIS!

# Update project permissions
PUT /api/vault/admin/projects/{project}
{
  "secrets": ["mollie_live_key", "new_secret"],
  "is_active": true
}

# Regenerate token
POST /api/vault/admin/projects/{project}/regenerate-token

# Get access logs
GET /api/vault/admin/logs?project=havunadmin&days=7
```

## Usage Examples

### 1. Setup: Create secrets

```bash
# Add Mollie API key
curl -X POST "https://havuncore.havun.nl/api/vault/admin/secrets" \
  -H "Content-Type: application/json" \
  -d '{
    "key": "mollie_live_key",
    "value": "live_xxxxxxxxxx",
    "category": "payment",
    "description": "Mollie Live API Key"
  }'

# Add Hetzner credentials
curl -X POST "https://havuncore.havun.nl/api/vault/admin/secrets" \
  -H "Content-Type: application/json" \
  -d '{
    "key": "hetzner_storage_password",
    "value": "G63^C@GB&PD2#jCl#1uj",
    "category": "storage",
    "description": "Hetzner Storage Box password"
  }'
```

### 2. Setup: Register a project

```bash
curl -X POST "https://havuncore.havun.nl/api/vault/admin/projects" \
  -H "Content-Type: application/json" \
  -d '{
    "project": "havunadmin",
    "secrets": ["mollie_live_key", "hetzner_storage_password"],
    "configs": ["laravel_base"]
  }'

# Response:
# {
#   "success": true,
#   "project": "havunadmin",
#   "api_token": "hvn_abc123..."  <-- SAVE THIS!
# }
```

### 3. Use: Fetch secrets in your project

```php
// In HavunAdmin's AppServiceProvider or config

use Illuminate\Support\Facades\Http;

$response = Http::withToken(env('HAVUNCORE_VAULT_TOKEN'))
    ->get('https://havuncore.havun.nl/api/vault/bootstrap');

$vault = $response->json();

// Now you have:
// $vault['secrets']['mollie_live_key']
// $vault['secrets']['hetzner_storage_password']
// $vault['configs']['laravel_base']
```

### 4. Use: Laravel config integration

```php
// config/services.php
return [
    'mollie' => [
        'key' => env('MOLLIE_KEY'), // fallback to .env
    ],
];

// Or fetch from Vault at runtime
'mollie' => [
    'key' => app('vault')->get('mollie_live_key'),
],
```

## Security

### Encryption
- All secrets zijn encrypted met Laravel's `Crypt` facade
- Gebruikt AES-256-CBC encryption
- Key is de `APP_KEY` van HavunCore

### Access Control
- Elk project heeft unieke API token
- Projecten kunnen alleen secrets zien waarvoor ze geautoriseerd zijn
- Alle toegang wordt gelogd

### Best Practices
1. **Nooit** tokens in code committen
2. Sla project tokens op in `.env` van elk project
3. Regenereer tokens periodiek
4. Check access logs regelmatig

## Categories

Aanbevolen categorieën voor secrets:

| Category | Examples |
|----------|----------|
| `payment` | Mollie keys, Stripe keys |
| `storage` | Hetzner, AWS S3, FTP credentials |
| `email` | SMTP credentials, Mailgun keys |
| `api` | Third-party API keys |
| `database` | Database passwords (externe DBs) |
| `encryption` | Backup encryption keys |
| `oauth` | OAuth client secrets |

## Migration from .env

Om bestaande .env secrets naar Vault te migreren:

```bash
# 1. Add secrets to Vault
curl -X POST ".../api/vault/admin/secrets" -d '{"key":"mollie_live_key","value":"..."}'

# 2. Register project with access
curl -X POST ".../api/vault/admin/projects" -d '{"project":"havunadmin","secrets":["mollie_live_key"]}'

# 3. Add token to project's .env
HAVUNCORE_VAULT_TOKEN=hvn_xxxxx

# 4. Update project code to fetch from Vault
# 5. Remove old secret from .env
```

## Troubleshooting

### "Unauthorized" error
- Check of token correct is
- Check of project `is_active` is
- Check of token niet geregenereerd is

### "Access denied" error
- Project heeft geen toegang tot deze secret
- Update project permissions via admin endpoint

### Decryption failed
- `APP_KEY` van HavunCore is gewijzigd
- Secrets moeten opnieuw worden aangemaakt

## Future Enhancements

- [ ] Admin authentication middleware
- [ ] UI in HavunCore webapp voor secret management
- [ ] Secret rotation reminders
- [ ] Backup/export van encrypted secrets
- [ ] Environment-specific secrets (prod/staging)
