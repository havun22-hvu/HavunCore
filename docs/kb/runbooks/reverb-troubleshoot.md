---
title: Runbook: Reverb WebSocket Troubleshooting
type: runbook
scope: havuncore
last_check: 2026-06-06
---

# Runbook: Reverb WebSocket Troubleshooting

> Stappenplan voor diagnose en herstel van Reverb broadcasting problemen.
> Geschreven n.a.v. de outage van 3-5 april 2026 (LCD scoreboard down).

## Snelle diagnose

```bash
# Op de server:
cd /var/www/judotoernooi/laravel   # of staging
php artisan reverb:health --fix
```

Als dit `✓ Reverb is healthy` zegt, werkt alles. Anders: lees de errors.

## Veelvoorkomende problemen

### 1. "Pusher error: Internal server error" (500)

**Oorzaak:** Reverb crasht intern bij het verwerken van events.

**Check:**
```bash
# Test de Reverb API direct:
php artisan tinker --execute="
try {
    event(new \App\Events\ScoreboardEvent(0, 0, ['event' => 'test']));
    echo 'OK';
} catch (\Throwable \$e) {
    echo 'FAIL: ' . \$e->getMessage();
}
"
```

**Veelvoorkomende oorzaken:**
- `allowed_origins` is string i.p.v. array → check `config/reverb.php`
- Reverb app key/secret mismatch → check `php artisan reverb:health`
- Reverb process crashed → `supervisorctl status reverb`

### 2. "Invalid frame header" in browser console

**Oorzaak:** WebSocket upgrade lukt, maar daarna crasht het.

**Check:**
```bash
# Is Reverb bereikbaar?
curl -s -o /dev/null -w 'HTTP %{http_code}' http://127.0.0.1:8080/

# Draait het correct?
supervisorctl status reverb reverb-staging
```

**Veelvoorkomende oorzaken:**
- Reverb herstart continu (EADDRINUSE) → dubbel procesbeheer
- Nginx proxy misconfiguratie → check `/etc/nginx/sites-available/judotoernooi`

### 3. Circuit breaker staat open (events worden gedropt)

**Check:**
```bash
php artisan tinker --execute="
\$b = new \App\Support\CircuitBreaker(service: 'reverb');
print_r(\$b->getStatus());
"
```

**Fix:**
```bash
php artisan reverb:health --fix
# OF handmatig:
php artisan tinker --execute="
Cache::forget('circuit_breaker:reverb:failures');
Cache::forget('circuit_breaker:reverb:opened_at');
echo 'Reset';
"
```

### 4. LCD scherm verbindt niet / verkeerde host/port

**Check browser console:** Kijk naar welke URL de WebSocket verbindt.
Moet zijn: `wss://judotournament.org/app/{key}` (production) of `wss://staging.judotournament.org/app/{key}` (staging).

**Oorzaak:** `env()` retourneert `null` na `config:cache`.

**Fix:**
```bash
php artisan config:clear
php artisan optimize:clear
# LCD pagina herladen
```

**Structurele fix:** Gebruik `config()` in Blade views, NIET `env()`.

### 5. Dubbel procesbeheer (Supervisor + Systemd)

**Symptoom:** EADDRINUSE errors, 100.000+ restarts in systemd journal.

**Check:**
```bash
systemctl status judotoernooi-reverb-staging
supervisorctl status reverb-staging
```

**Fix:** Kies ÉÉN systeem. Supervisor is de standaard:
```bash
systemctl disable --now judotoernooi-reverb-staging
```

### 6. FATAL na MySQL-/dependency-herstart (stale logs!)

**Symptoom:** `supervisorctl status` toont `reverb` én `reverb-staging` op **FATAL — Exited too quickly**.
Poorten 8080/8081 dood. In de webapp-status: "niet goed" voor beide tegelijk.

**Oorzaak:** Reverb leest bij boot de cache-tabel (`BroadcastConfigValidator` → cache-driver = database/MySQL).
Als MySQL even herstart, faalt die boot-check (`SQLSTATE[HY000] [2002] Connection refused`),
supervisor verbruikt `startretries` en zet het proces op **FATAL**. MySQL komt seconden later terug,
maar **supervisor herstelt nooit zelf uit FATAL** → reverb blijft dagen down terwijl de DB allang werkt.

**Valkuil:** de errors in `storage/logs/reverb.log` zijn dan **stale** — kijk altijd naar de mtime:
```bash
stat -c '%y' /var/www/judotoernooi/laravel/storage/logs/reverb.log   # == MySQL-herstartmoment?
# Werkt de DB nu wél?
cd /var/www/judotoernooi/laravel && sudo -u www-data php artisan tinker \
  --execute='echo DB::connection()->getDatabaseName();'
```

**Fix:** als de DB nu bereikbaar is → simpelweg herstarten:
```bash
supervisorctl restart reverb reverb-staging
sudo -u www-data php artisan reverb:health   # verifieer
```

**Structurele preventie (JudoToernooi-config):** verhoog `startretries` in de supervisor-conf, of maak
reverb tolerant voor een korte DB-hapering bij boot, zodat een MySQL-restart geen permanente FATAL geeft.

> **Incident 4-6 juni 2026:** MySQL-restart 4 jun 06:21 UTC → reverb prod+staging FATAL → 2,5 dag down
> ondanks gezonde DB. Opgelost met `supervisorctl restart`. Status-monitoring toonde dit correct.

## Server poorten

| Omgeving | Reverb poort | Extern via nginx | Supervisor process |
|----------|-------------|-----------------|-------------------|
| Production | 8080 | 443 (`/app` proxy) | `reverb` |
| Staging | 8081 | 443 (`/app` proxy) | `reverb-staging` |

## Na elke deploy

```bash
php artisan optimize:clear
php artisan reverb:health --fix
supervisorctl restart reverb          # production
supervisorctl restart reverb-staging  # staging
```

## Monitoring

De `BroadcastConfigValidator` ServiceProvider logt `CRITICAL` bij config fouten bij de eerste request na deploy. Check:

```bash
grep "CRITICAL" storage/logs/laravel.log | tail -5
```

## Gerelateerde documenten

- Post-mortem: `JudoToernooi/laravel/docs/postmortem/2026-04-05-reverb-broadcasting-failure.md`
- Scoreboard API: `HavunCore/docs/kb/projects/judoscoreboard.md`
- Pattern: `HavunCore/docs/kb/patterns/reverb-laravel.md`

---

*Aangemaakt: 5 april 2026*
