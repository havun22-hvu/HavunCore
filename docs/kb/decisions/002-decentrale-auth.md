# ADR-002: Decentrale Authenticatie

> Architecture Decision Record

## Status

**Accepted** - 5 december 2025

## Context

Overweging: Moet HavunCore centrale auth provider zijn voor alle apps?

Opties:
1. Centrale auth via HavunCore (SSO-achtig)
2. Elke app beheert eigen auth

## Decision

**Elke app beheert zijn eigen authenticatie.**

| App | Auth Systeem |
|-----|--------------|
| Herdenkingsportaal | Laravel auth + WebAuthn |
| HavunAdmin | Laravel auth + WebAuthn |
| HavunCore Webapp | Device tokens |
| VPDUpdate | QR login (planned) |

## Rationale

1. **Geen CORS issues** - Auth op zelfde domein als app
2. **Onafhankelijkheid** - Apps werken ook als HavunCore down is
3. **Simpeler debuggen** - Elk systeem apart te testen
4. **Geen single point of failure**

## Consequences

### Positief
- Robuuster systeem
- Makkelijker te onderhouden per app
- Geen complexe SSO implementatie nodig

### Negatief
- Gebruikers moeten per app inloggen
- Geen unified user management

## Related

- Herdenkingsportaal - WebAuthn implementatie
- HavunAdmin - WebAuthn implementatie
- [001-havuncore-standalone.md](001-havuncore-standalone.md)
