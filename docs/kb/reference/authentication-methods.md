# Authenticatie Methodes — Alle Havun Projecten

> **Status:** Vastgelegd — geldt voor ALLE projecten
> **Datum:** 13 april 2026
> **Laatst bijgewerkt:** 13 april 2026

## Registratie

**Enige methode:** Magic Link via e-mail.

1. Gebruiker vult e-mailadres in
2. Systeem stuurt magic link naar e-mail
3. Gebruiker klikt link → account aangemaakt + ingelogd
4. Na eerste login: keuze om wachtwoord in te stellen en/of biometric te registreren

Geen wachtwoord nodig bij registratie. Geen gebruikersnaam. E-mail = identiteit.

## Login Methodes

### Smartphone (PWA / mobiel)

| Prioriteit | Methode | Wanneer |
|-----------|---------|---------|
| 1 | **Biometric** (fingerprint/face via WebAuthn) | Als geregistreerd |
| 2 | **Wachtwoord** | Altijd beschikbaar als fallback |

**NIET tonen op smartphone:** QR code. Heeft geen zin — je scant een QR code met je smartphone om in te loggen op een ander scherm, niet op dezelfde smartphone.

### Desktop (browser)

| Prioriteit | Methode | Wanneer |
|-----------|---------|---------|
| 1 | **QR code** scannen met smartphone | Altijd |
| 2 | **Wachtwoord** | Altijd beschikbaar als fallback |

**NIET tonen op desktop:** Biometric. Desktop browsers ondersteunen WebAuthn maar de UX is slecht (Windows Hello popup, niet iedereen heeft het). Biometric is voor smartphones.

### Gedrag

- **Laatste methode onthouden** — sla op welke methode de gebruiker laatst gebruikte (localStorage)
- Bij opnieuw openen: toon die methode als primair
- **Altijd alternatief bieden** — link "Andere inlogmethode" onderaan
- Na succesvolle login: redirect naar vorige pagina of dashboard

## NIET gebruiken

- ~~Pincode~~ — niet nodig, biometric vervangt dit
- ~~SMS verificatie~~ — te duur, niet betrouwbaar
- ~~Social login~~ (Google/Facebook) — privacy, afhankelijkheid
- ~~Magic link voor elke login~~ — alleen voor registratie + wachtwoord vergeten

## Per Project

| Project | Registratie | Login smartphone | Login desktop |
|---------|------------|-----------------|---------------|
| HavunCore Webapp | Magic link | Biometric / wachtwoord | QR / wachtwoord |
| Herdenkingsportaal | Magic link | Biometric / wachtwoord | QR / wachtwoord |
| HavunAdmin | Magic link | Biometric / wachtwoord | QR / wachtwoord |
| JudoToernooi | Magic link | Biometric / wachtwoord | QR / wachtwoord |
| Infosyst | Magic link | Biometric / wachtwoord | QR / wachtwoord |
| SafeHavun | Magic link | Biometric / wachtwoord | QR / wachtwoord |

Alle projecten gebruiken dezelfde methodes. Geen uitzonderingen.

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
- Bcrypt hash, minimaal 8 tekens
- Alleen als fallback, nooit als primaire methode promoten
