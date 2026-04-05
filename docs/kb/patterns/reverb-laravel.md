# Pattern: Laravel Reverb WebSockets (Self-hosted)

> Herbruikbaar pattern voor real-time communicatie via Laravel Reverb.
> Vervangt het Pusher SaaS pattern voor projecten die self-hosted WebSockets nodig hebben.

## Wanneer gebruiken

- Real-time updates (scores, timers, chat, live dashboards)
- Self-hosted vereist (geen externe afhankelijkheid)
- Laravel backend

## Projecten die dit gebruiken

- **JudoToernooi** — LCD scoreboard, mat interface, chat, heartbeat
- **JudoScoreBoard** — Gekoppelde modus (events van bediening naar display)

## Server Setup

### Poorten conventie (Havun standaard)

| Omgeving | Reverb poort | Extern | Supervisor process |
|----------|-------------|--------|-------------------|
| Production | 8080 | 443 via nginx `/app` | `reverb` |
| Staging | 8081 | 443 via nginx `/app` | `reverb-staging` |

### .env

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=mijn-project
REVERB_APP_KEY=random-key
REVERB_APP_SECRET=random-secret
REVERB_SERVER_HOST=0.0.0.0
REVERB_HOST=mijndomein.nl         # Waar Laravel heen POST
REVERB_PORT=8080                  # Interne poort
REVERB_SCHEME=http                # Intern altijd http

VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=mijndomein.nl    # Waar browser heen verbindt
VITE_REVERB_PORT=443              # Extern via nginx
VITE_REVERB_SCHEME=https
```

### config/reverb.php — KRITIEKE VALKUILEN

```php
'apps' => [
    [
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'app_id' => env('REVERB_APP_ID'),
        // ⚠️ allowed_origins MOET een array zijn (Reverb v1.7+ crasht met string)
        'allowed_origins' => explode(',', env('REVERB_ALLOWED_ORIGINS', 'mijndomein.nl')),
    ],
],
```

### config/broadcasting.php — env() vs config()

```php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST'),
        'port' => env('REVERB_PORT', 443),
        'scheme' => env('REVERB_SCHEME', 'https'),
        'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
    ],
],
```

> **LET OP:** `env()` retourneert `null` na `config:cache`.
> In config files is dit OK (ze worden gecached).
> In Blade views, controllers, of services: gebruik ALTIJD `config()`.

### Nginx WebSocket proxy

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

upstream reverb-production {
    server 127.0.0.1:8080;
}

location /app {
    proxy_pass http://reverb-production;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection $connection_upgrade;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

## Event Pattern (met SafelyBroadcasts)

```php
use App\Events\Concerns\SafelyBroadcasts;

class MyEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, SafelyBroadcasts {
        SafelyBroadcasts::dispatch insteadof Dispatchable;
    }

    public function broadcastOn(): Channel
    {
        return new Channel("my-channel.{$this->id}");
    }
}
```

**SafelyBroadcasts biedt:**
- Circuit breaker (3 failures → 30s cooldown)
- Try-catch (geen crashes)
- Log throttling (1x/min per event type op WARNING niveau)

## Frontend (Blade view)

```php
// Server-side config ophalen (NIET env()!)
$appUrl = config('app.url', 'https://localhost');
$reverbHost = parse_url($appUrl, PHP_URL_HOST);
$reverbPort = parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? 443 : 80;
$reverbKey = config('broadcasting.connections.reverb.key');
```

```javascript
const pusher = new Pusher(reverbKey, {
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    cluster: 'mt1'
});

const channel = pusher.subscribe('my-channel.123');
channel.bind('my-event', (data) => { /* handle */ });
```

## Verplichte safeguards

1. **`reverb:health` command** — test config + server + broadcast na deploy
2. **`BroadcastConfigValidator`** — boot-time validatie op prod/staging
3. **`ReverbConfigTest`** — unit tests voor config types/waarden
4. **SafelyBroadcasts trait** — voorkom crashes, log errors zichtbaar
5. **Post-deploy check** — `php artisan reverb:health --fix` na elke git pull

## Bekende valkuilen

| Valkuil | Gevolg | Preventie |
|---------|--------|-----------|
| `allowed_origins` als string | Reverb 500 op alle broadcasts | `explode()` + `ReverbConfigTest` |
| `env()` in Blade/controllers | `null` na `config:cache` → port 0 | Gebruik `config()` of `parse_url(config('app.url'))` |
| Dubbel procesbeheer | EADDRINUSE crash loop | Kies Supervisor OF Systemd, niet beide |
| SafelyBroadcasts te stil | Errors onzichtbaar | Log op WARNING met error message |
| Circuit breaker blijft open | Events permanent gedropt | `reverb:health --fix` na deploy |

## Troubleshooting

Zie: `docs/kb/runbooks/reverb-troubleshoot.md`

---

*Pattern toegevoegd: 5 april 2026*
*Gebruikt door: JudoToernooi, JudoScoreBoard*
