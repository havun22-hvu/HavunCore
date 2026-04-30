---
title: Authenticatie Methodes — Alle Havun Projecten
type: reference
scope: havuncore
last_check: 2026-04-30
---

# Authenticatie Methodes — Alle Havun Projecten

> **Status:** Vastgelegd — portfolio-standaard (passwordless + biometric/QR)
> **Datum:** 13 april 2026
> **Laatst bijgewerkt:** 30 april 2026 — Herdenkingsportaal-uitzondering (wachtwoord-primair) gemarkeerd

## Email-First Flow (geen tabs)

Er zijn **geen aparte login/registratie schermen**. Eén flow, het systeem bepaalt wat nodig is:

1. Gebruiker vult e-mailadres in → "Verder"
2. Backend checkt of email bestaat:
   - **Ja:** stuurt magic link → gebruiker klikt → ingelogd → biometric koppelen (nieuw apparaat)
   - **Nee:** frontend toont extra velden (naam, evt. project-specifiek) → account aanmaken via magic link
3. Na eerste login op een apparaat: biometric koppelen (verplicht op mobiel)

E-mail = identiteit. Geen gebruikersnaam. Geen login/register tabs.

**Backend contract:** Eén endpoint `POST /api/auth/magic-link`
- `{ email }` → 200 (bestaand) of 422 `{ needs_registration: true }` (nieuw)
- `{ email, name, role }` → 200 (account aangemaakt + magic link verstuurd)

## Login Methodes

### Smartphone / Native app (Android/iOS)

| Prioriteit | Methode | Wanneer |
|-----------|---------|---------|
| 1 | **Biometric** (fingerprint/face) | Als geregistreerd op dit apparaat |
| 2 | **Magic link** (herstel) | Nieuw apparaat, biometric kwijt |
| 3 | **Wachtwoord** (optioneel) | Alleen als gebruiker zelf heeft ingesteld in account-settings |

**NIET tonen op smartphone:** QR code. Heeft geen zin — je scant een QR code met je smartphone om in te loggen op een ander scherm, niet op dezelfde smartphone.

### Desktop (browser)

| Prioriteit | Methode | Wanneer |
|-----------|---------|---------|
| 1 | **QR code** scannen met smartphone | Altijd |
| 2 | **Magic link** (herstel) | Nieuw apparaat, QR niet beschikbaar |
| 3 | **Wachtwoord** (optioneel) | Alleen als gebruiker zelf heeft ingesteld in account-settings |

**NIET tonen op desktop:** Biometric. Desktop browsers ondersteunen WebAuthn maar de UX is slecht (Windows Hello popup, niet iedereen heeft het). Biometric is voor smartphones.

### Wachtwoord-flow (optioneel, opt-in per gebruiker)

- **Default:** geen wachtwoord. Login-scherm vraagt alleen e-mail → magic-link of bio/QR.
- **Opt-in:** gebruiker kan in account-settings zelf een wachtwoord aanmaken (security-tab).
- **Login-flow met optioneel wachtwoord:**
  1. Gebruiker vult e-mail in
  2. Backend checkt: heeft deze user een wachtwoord ingesteld?
  3. **Ja:** toon "wachtwoord-veld + magic-link knop als alternatief"
  4. **Nee:** stuur magic-link direct (geen wachtwoord-veld)
- **Resetten:** "wachtwoord vergeten" = magic-link → reset-flow.
- **Verwijderen:** gebruiker kan zelf wachtwoord uitzetten in settings (terug naar magic-only).

### Gedrag

- **Biometric onthouden** — als biometric beschikbaar + gekoppeld, toon als primaire login
- **Altijd alternatief bieden** — link "Ander account of nieuw apparaat?" voor email-first flow
- Na succesvolle login: redirect naar vorige pagina of dashboard

## NIET gebruiken

- ~~Pincode~~ — niet nodig, biometric vervangt dit
- ~~SMS verificatie~~ — te duur, niet betrouwbaar
- ~~Social login~~ (Google/Facebook) — privacy, afhankelijkheid van derde partij (zie incident 27-04-2026: Google verwijderde HP OAuth-client)
- ~~Magic link voor elke login~~ — alleen voor registratie + herstel (nieuw apparaat)
- ~~Wachtwoord verplicht~~ — opt-in alleen, nooit default

## Per Project

| Project | Registratie | Login smartphone | Login desktop | Wachtwoord-rol |
|---------|------------|-----------------|---------------|----------------|
| Studieplanner | Magic link | Biometric / magic link (herstel) | N.v.t. (alleen app) | Optioneel (opt-in) |
| HavunCore Webapp | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) | Optioneel (opt-in) |
| **Herdenkingsportaal** ⚠️ | Magic link | **Wachtwoord (primair)** + magic-link knop + biometrie-knop (expliciet, alleen smartphone) | **Wachtwoord (primair)** + magic-link knop | **Primair** (bewuste afwijking) |
| HavunAdmin | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) | Optioneel (opt-in) |
| JudoToernooi | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) | Optioneel (opt-in) |
| Infosyst | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) | Optioneel (opt-in) |
| SafeHavun | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) | Optioneel + verplicht TOTP-2FA (vault) |

Alle projecten **behalve Herdenkingsportaal** gebruiken dezelfde passwordless-default methodes.

### ⚠️ Herdenkingsportaal — bewuste afwijking (sinds 30-04-2026)

HP houdt **wachtwoord-primair** met magic-link en biometrie als alternatieven. Reden: doelgroep (oudere familie, memoriaal-context) ervaart magic-link/passwordless als drempel — gebruikers verwachten een ouderwetse wachtwoord-flow. QR is verwijderd uit de login-UI in 3.3.0; backend-routes blijven werkend voor toekomstige integratie. Volledige rationale: [HP SPEC.md sectie 3](../../../../Herdenkingsportaal/SPEC.md), [HP LOGIN-METHODS.md](../../../../Herdenkingsportaal/docs/2-FEATURES/LOGIN-METHODS.md). Heroverweg bij grote redesign.

## Technisch

### WebAuthn (biometric)
- Standaard Web API, werkt in alle moderne browsers
- Registratie: `navigator.credentials.create()`
- Login: `navigator.credentials.get()`
- Server-side: challenge genereren + response valideren

### QR Login
- Server genereert QR code met uniek token (verloopt na 60 seconden, auto-refresh)
- Smartphone scant QR → bevestigt login
- Desktop ontvangt goedkeuring via **WebSocket** (GEEN polling) → JWT token
- Zie `docs/kb/decisions/geen-polling.md` — NOOIT polling voor QR status

### Magic Link
- Server genereert eenmalig token (verloopt na 15 min)
- E-mail met link `https://app.nl/auth/magic/{token}`
- Klik = account aanmaken (nieuw) of inloggen (bestaand)

### Wachtwoord
- **NIET MEER GEBRUIKEN** — verwijderd uit alle login flows
- Biometric + magic link dekt alle scenario's
- Bestaande password kolommen: nullable maken, niet verwijderen (backwards compat)
