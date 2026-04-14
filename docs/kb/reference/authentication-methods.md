# Authenticatie Methodes — Alle Havun Projecten

> **Status:** Vastgelegd — geldt voor ALLE projecten
> **Datum:** 13 april 2026
> **Laatst bijgewerkt:** 14 april 2026

## Registratie

**Primair:** Magic Link via e-mail.

1. Gebruiker vult e-mailadres in
2. Systeem stuurt magic link naar e-mail
3. Gebruiker klikt link → account aangemaakt + ingelogd
4. Na eerste login: keuze om wachtwoord in te stellen en/of biometric te registreren

**Fallback (als e-mail service niet beschikbaar):** Registratie met e-mail + wachtwoord.

1. Frontend detecteert dat magic link niet beschikbaar is
2. Gebruiker vult e-mailadres + wachtwoord in
3. Account direct aangemaakt + ingelogd
4. Daarna zijn ALLE login methodes beschikbaar (biometric, QR, wachtwoord) — registratiemethode beperkt niets

E-mail = identiteit. Geen gebruikersnaam.

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

- **Laatste methode onthouden** — sla op welke methode de gebruiker laatst gebruikte (localStorage)
- Bij opnieuw openen: toon die methode als primair
- **Altijd alternatief bieden** — link "Andere inlogmethode" onderaan
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
