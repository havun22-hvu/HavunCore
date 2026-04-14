# Authenticatie Methodes — Alle Havun Projecten

> **Status:** Vastgelegd — geldt voor ALLE projecten
> **Datum:** 13 april 2026
> **Laatst bijgewerkt:** 14 april 2026

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

**NIET tonen op smartphone:** QR code. Heeft geen zin — je scant een QR code met je smartphone om in te loggen op een ander scherm, niet op dezelfde smartphone.
**NIET nodig op smartphone:** Wachtwoord. Biometric + magic link dekt alle scenario's.

### Desktop (browser)

| Prioriteit | Methode | Wanneer |
|-----------|---------|---------|
| 1 | **QR code** scannen met smartphone | Altijd |
| 2 | **Magic link** (herstel) | Nieuw apparaat, QR niet beschikbaar |

**NIET tonen op desktop:** Biometric. Desktop browsers ondersteunen WebAuthn maar de UX is slecht (Windows Hello popup, niet iedereen heeft het). Biometric is voor smartphones.

### Gedrag

- **Biometric onthouden** — als biometric beschikbaar + gekoppeld, toon als primaire login
- **Altijd alternatief bieden** — link "Ander account of nieuw apparaat?" voor email-first flow
- Na succesvolle login: redirect naar vorige pagina of dashboard

## NIET gebruiken

- ~~Wachtwoord~~ — niet nodig, biometric + magic link dekt alles
- ~~Pincode~~ — niet nodig, biometric vervangt dit
- ~~SMS verificatie~~ — te duur, niet betrouwbaar
- ~~Social login~~ (Google/Facebook) — privacy, afhankelijkheid
- ~~Magic link voor elke login~~ — alleen voor registratie + herstel (nieuw apparaat)

## Per Project

| Project | Registratie | Login smartphone | Login desktop |
|---------|------------|-----------------|---------------|
| Studieplanner | Magic link | Biometric / magic link (herstel) | N.v.t. (alleen app) |
| HavunCore Webapp | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) |
| Herdenkingsportaal | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) |
| HavunAdmin | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) |
| JudoToernooi | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) |
| Infosyst | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) |
| SafeHavun | Magic link | Biometric / magic link (herstel) | QR / magic link (herstel) |

Alle projecten gebruiken dezelfde methodes. Geen wachtwoorden. Magic link = herstel methode (nieuw apparaat, biometric kwijt).

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
