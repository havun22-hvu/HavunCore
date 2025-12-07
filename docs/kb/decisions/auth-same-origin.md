# Decision: Authenticatie binnen dezelfde applicatie houden

## Datum
7 december 2025

## Context
Bij Herdenkingsportaal ging biometrische login eerst via HavunCore API (cross-origin), waarna een lokale sessie werd gemaakt. Dit veroorzaakte sessie-problemen.

## Probleem
Cross-origin authenticatie flow:
1. Frontend → HavunCore API (andere origin)
2. HavunCore valideert passkey
3. Frontend → lokale `/auth/biometric/complete`
4. Lokale server probeert sessie te maken

**Resultaat:** Sessie cookies werkten niet goed door:
- `SESSION_SAME_SITE="none"` vereist voor cross-origin
- Complexe token-passing tussen origins
- Sessie regeneratie problemen
- Login loop (steeds opnieuw inloggen)

## Beslissing
**Authenticatie altijd binnen dezelfde applicatie afhandelen.**

Elke app (Herdenkingsportaal, HavunAdmin) heeft eigen:
- Passkey registratie en validatie (laragear/webauthn)
- Sessie management
- Login routes

HavunCore is ALLEEN voor:
- Gedeelde business logic
- Task queue API
- Cross-app communicatie (niet auth)

## Configuratie
Met lokale auth kan `SESSION_SAME_SITE=lax` (default, veiliger):

```env
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=true
```

`SameSite=none` alleen nodig voor echte cross-site scenarios (3rd party embeds).

## Gevolgen
- Elke app beheert eigen passkeys (users moeten per app registreren)
- Geen single sign-on via passkeys (wel via wachtwoord als gewenst)
- Simpelere, stabielere login flow
- Minder debug-sessies om 2 uur 's nachts

## Zie ook
- `docs/kb/runbooks/token-based-login.md` - Token exchange pattern voor AJAX login
- `docs/kb/runbooks/fix-qr-login-csrf.md`
