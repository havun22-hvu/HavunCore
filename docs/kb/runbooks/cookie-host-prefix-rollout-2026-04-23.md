---
title: Session cookie __Host- prefix rollout (2026-04-23)
type: runbook
scope: alle-projecten
last_check: 2026-04-23
status: RESOLVED
---

# Session cookie `__Host-` prefix rollout

## Symptoom

SecurityHeaders meldde twee oranje warnings op `herdenkingsportaal-session`:

1. _"There is no Cookie Prefix on this cookie."_
2. Cookie had `domain=.herdenkingsportaal.nl` → lekt naar alle subdomeinen.

## Root cause

- `SESSION_COOKIE` niet expliciet gezet → Laravel genereert een default
  (`{slug}-session`) zonder `__Host-` prefix.
- `SESSION_DOMAIN=.herdenkingsportaal.nl` (en vergelijkbaar op havunadmin)
  → expliciet domain-attribute, incompatibel met `__Host-` prefix.

## Fix per project

Productie `.env`:
```
SESSION_COOKIE=__Host-<slug>-session
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
```

Daarna: `php artisan config:clear`.

## Rollout status (2026-04-23)

| Project | Cookie | Status |
|---|---|---|
| herdenkingsportaal | `__Host-herdenkingsportaal-session` | ✅ verified |
| havunadmin | `__Host-havunadmin-session` | ✅ verified |
| judotoernooi (judotournament.org) | `__Host-judotoernooi-session` | ✅ verified |
| studieplanner | `__Host-studieplanner-session` | ✅ verified |
| infosyst | `__Host-infosyst-session` | ✅ verified |
| havuncore | `__Host-havuncore-session` | ✅ config gezet (stateless API, zet geen session op publieke endpoints) |

## Verificatie

```bash
curl -skI -L https://<domain>/login | grep -i '^set-cookie:.*__Host-'
```
→ output bevat `__Host-<slug>-session=...; path=/; secure; httponly; samesite=lax`
(GEEN `domain=` attribute).

## Bijwerking

Bestaande sessies zijn ongeldig → alle users moeten één keer opnieuw inloggen.
Backup `.env.bak-YYYYMMDD-HHMMSS` per project voor rollback.

## Open follow-up: XSRF-TOKEN prefix

`XSRF-TOKEN` cookie heeft nog geen prefix. Laravel's `VerifyCsrfToken`
middleware bevat de naam hardcoded. Om naar `__Secure-XSRF-TOKEN` te gaan:

1. Custom middleware per project extenden van `VerifyCsrfToken`
2. `addCookieToResponse()` override met nieuwe naam
3. Axios defaults aanpassen (frontend):
   `axios.defaults.xsrfCookieName = '__Secure-XSRF-TOKEN'`
4. Blade `@csrf` directive check — gebruikt session-token, niet cookie

Aparte sessie / issue; niet nu live uitgerold.
