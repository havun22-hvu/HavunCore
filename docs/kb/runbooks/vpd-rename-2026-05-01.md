---
title: VPD-rename — vpdupdate.havun.nl → vpd.havun.nl (1 mei 2026)
type: runbook
scope: vpd
last_check: 2026-05-04
related:
  - docs/kb/projects/vpd.md
  - docs/kb/decisions/auth-standard-v51.md
---

# VPD-rename: vpdupdate.havun.nl → vpd.havun.nl

## Aanleiding

Op **2026-05-01** hernoemd van `vpdupdate.havun.nl` naar `vpd.havun.nl`.
Reden: het oude hostname triggerde malware-pattern false-positives bij
security-firewalls (o.a. Fortinet) — gebruikers in zakelijke netwerken
kregen de site geblokkeerd.

## Server-kant (al uitgevoerd)

- Nieuw vhost `vpd.havun.nl` — reverse proxy naar Node.js poort 3002
- Oud vhost `vpdupdate.havun.nl` — permanent **301 redirect** naar `vpd.havun.nl`
- TLS-certificaten voor beide hostnames actief
- PM2-proces blijft draaien onder de naam `vpdupdate`

## Client-side gevolgen — wat gebruikers moeten weten

Een 301-redirect lost het meeste op, maar **twee zaken zijn origin-gebonden**
en overleven de domeinwissel niet. Per gebruiker eenmalig herstellen:

### 1. PWA herinstallatie nodig

**Symptoom:** PWA opent wel maar laadt geen data ("geen connectie").

**Oorzaak:**
- Service Worker scope, IndexedDB, cookies en local-storage zijn gekoppeld
  aan het origin waarop de PWA is geïnstalleerd (`vpdupdate.havun.nl`)
- `fetch()` calls van de SW gaan nog naar het oude origin → 301 → maar
  cookies van het oude origin gaan niet automatisch mee naar het nieuwe origin
- WebSockets kunnen geen 301-redirect volgen
- Resultaat: app-shell laadt uit cache, maar geauthenticeerde calls falen

**Oplossing (per device):**
1. Long-press op het VPD-app-icoon → **App-info → Verwijderen**
2. Open Chrome → ga naar `https://vpd.havun.nl/`
3. Inloggen
4. Chrome-menu (⋮) → **App installeren** / "Toevoegen aan startscherm"
5. Open de nieuwe app vanuit het startscherm

### 2. WebAuthn / biometrische login opnieuw registreren

**Symptoom:** PWA toont QR-koppeling i.p.v. biometric-prompt op een device
waar bio eerder werkte.

**Oorzaak:**
WebAuthn passkeys zijn gebonden aan de `rpId` (Relying Party ID = domein).
Een credential geregistreerd voor `vpdupdate.havun.nl` is niet bruikbaar
op `vpd.havun.nl`. De server ziet "onbekend apparaat" en biedt de QR/magic-link
koppelflow aan.

**Oplossing (per device):**
1. Inloggen via QR-scan (vanaf andere ingelogde device) of magic-link via e-mail
2. Eenmaal binnen: **Instellingen → Beveiliging / Biometrie**
3. "Vingerafdruk / gezicht koppelen" → bevestigen met device-biometrie
4. Nieuwe passkey wordt nu geregistreerd voor `vpd.havun.nl`
5. Volgende keer openen: biometric-login werkt weer direct

## Communicatie

Bij elke gebruiker die een connectieprobleem of plotse QR-prompt meldt:
**eerste vraag** is "had je VPD eerder geïnstalleerd via vpdupdate.havun.nl?".
Zo ja → bovenstaande twee stappen doorlopen.

Geldt ook voor de oude installatie op desktop-Chrome / iOS Safari / Edge —
elk OS + browser-combinatie vereist eigen herinstallatie + her-registratie
van de passkey.

## Wanneer kan de oude vhost weg?

De `vpdupdate.havun.nl` 301-redirect blijft minstens **tot eind 2026** staan
om bookmarks, externe links en oude PWA-installaties op te vangen.

Voorwaarden voor verwijdering:
- Geen verkeer meer in `nginx access.log` voor `vpdupdate.havun.nl` over
  een aaneengesloten periode van 30 dagen
- Alle bekende gebruikers hebben minimaal één keer ingelogd via het nieuwe
  domein (zichtbaar in `auth_devices` of `webauthn_credentials` tabel:
  `created_at > 2026-05-01` voor het user-account)

Bij verwijdering ook: TLS-certificaat van Let's Encrypt revoken + DNS-record
opruimen bij mijnhost.nl.

## Referenties

- nginx-config: `/etc/nginx/sites-enabled/vpdupdate.havun.nl` (redirect-only)
- nginx-config: `/etc/nginx/sites-enabled/vpd.havun.nl` (live)
- PM2-proces: `vpdupdate` op poort 3002 (naam ongewijzigd gelaten)
- Auth-standaard: `docs/kb/decisions/auth-standard-v51.md`
