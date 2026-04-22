---
title: Unified Auth Strategy
type: reference
scope: havuncore
last_check: 2026-04-22
---

# Unified Auth Strategy

> **Laatst bijgewerkt:** 31 maart 2026
> **Status:** Actieve standaard voor alle Havun-projecten
> **Versie:** 5.0
> **Eigenaar:** HavunCore

## Overzicht

Alle Havun-applicaties (web + native) gebruiken een **passwordless** authenticatiestrategie:
- **Magic link** voor eerste login + herstel (nieuw apparaat, passkey kwijt)
- **Biometrie (WebAuthn/Passkey)** voor dagelijks gebruik (smartphone + native apps)
- **QR code** voor desktop login (scan met telefoon)
- **Decentraal** — elke app beheert eigen auth (ADR-002)

### Platforms

| Platform | Primair | Eerste keer / herstel |
|----------|---------|----------------------|
| **Smartphone (web)** | Biometrie (passkey) | Magic link |
| **Desktop (web)** | QR code (scan met telefoon) | Magic link |
| **Android app** | Biometrie (passkey via Google Play Services) | Magic link (deep link) |
| **iOS app** | Biometrie (passkey via iCloud Keychain) | Magic link (universal link) |

### Niet meer gebruikt (v5.0)
- ~~Email/wachtwoord~~ (verwijderd — wachtwoorden zijn obsoleet)
- ~~PIN login~~ (verwijderd in v4.0)
- ~~Device fingerprint als primaire herkenning~~ (nu alleen voor analytics)

---

## 1. Registratie & Eerste Login

### Flow
```
1. Gebruiker voert naam + email in
2. Magic link wordt verstuurd (15 min geldig, single-use)
3. Klik op link → account aangemaakt, direct ingelogd
4. Biometrie koppelen (smartphone/native app — VERPLICHT stap)
5. Volgende keer: biometrie of QR code
```

**Native apps:** Magic link opent de app via deep link (Android) / universal link (iOS).

### Technisch
- **MagicLinkToken** model (64-char random token)
- **Rate limiting:** max 3 magic links per 10 minuten per IP
- **Email enumeration preventie:** altijd success tonen
- **Email template:** branded, duidelijke CTA knop

### Waarom passwordless?
- Wachtwoorden zijn obsoleet — slecht voor UX en security
- Magic link: lagere drempel, email verificatie ingebouwd
- Biometrie: sneller en veiliger dan elk wachtwoord
- Geen wachtwoord = geen wachtwoord-gerelateerde support

---

## 2. Dagelijks Gebruik

### Smartphone (web + native)
```
Biometrie (passkey) → WebAuthn API → ingelogd
Fallback: Magic link (nieuwe email)
```
- **Types:** Face ID, Touch ID, vingerafdruk
- **Web:** laragear/webauthn (Laravel) of raw WebAuthn API (Node.js)
- **Android:** Google Play Services Credential Manager (passkeys)
- **iOS:** ASAuthorization framework (passkeys via iCloud Keychain)

### Desktop (web)
```
QR code tonen → scan met telefoon (ingelogd) → ingelogd op desktop
Fallback: Magic link
```
- **QR verloopt:** na 5 minuten, vernieuw knop beschikbaar
- **Polling:** elke 2 seconden status check
- **Vereist:** telefoon moet al ingelogd zijn

### Herstel (bij problemen)
- **Passkey kwijt / nieuw apparaat** → Magic link aanvragen → opnieuw biometrie koppelen
- **Magic link komt niet aan** → opnieuw aanvragen (rate limit: 3 per 10 min)
- **Gebruiker mag NOOIT vastlopen** — altijd een pad terug via magic link

---

## 3. Herstel & Account Recovery

### Passkey kwijt / nieuw apparaat
```
1. Gebruiker klikt "Stuur mij een login link"
2. Magic link wordt verstuurd (15 min geldig, single-use)
3. Klik op link → direct ingelogd
4. Prompt: "Koppel biometrie op dit apparaat"
5. Nieuwe passkey geregistreerd → klaar
```

### Geen wachtwoord reset nodig
Er zijn geen wachtwoorden meer. Magic link IS de recovery methode.

---

## 4. Step-Up Authentication (optioneel per project)

Voor gevoelige acties (betaling, delete account, wijzig email):

### Niveau 1: Biometrie herbevestiging
- Gebruiker bevestigt met vingerafdruk/Face ID
- Session-based: 15 min geldig

### Niveau 2: TOTP (optioneel)
- Google Authenticator / Authy
- QR code setup
- Backup codes (8-10 stuks)

### Voorbeelden
| Actie | Auth Niveau |
|-------|-------------|
| Factuur bekijken | Normale sessie |
| Factuur betalen | Biometrie herbevestiging |
| IBAN wijzigen | TOTP (indien ingesteld) |
| Account verwijderen | TOTP + email bevestiging |

---

## 5. Technische Stack

### Backend (Laravel)
```php
// Magic link
$token = MagicLinkToken::generate($email, 'login', ['name' => $name]);
Mail::to($email)->send(new MagicLinkMail($token));

// Login pattern (KRITIEK)
Auth::guard('web')->login($user, true);
session()->save(); // NIET regenerate()
```

### Frontend (Web)
```javascript
// Platform detectie
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

// QR (desktop)
fetch('/auth/qr/generate') → toon QR → poll status

// Biometrie (smartphone)
navigator.credentials.get({ publicKey: options }) → passkey login
```

### Native Apps
```
// Android — Credential Manager API
val credentialManager = CredentialManager.create(context)
val getCredentialRequest = GetCredentialRequest(listOf(getPublicKeyCredentialOption))
credentialManager.getCredential(context, getCredentialRequest)

// iOS — ASAuthorization
let provider = ASAuthorizationPlatformPublicKeyCredentialProvider(relyingPartyIdentifier: rpId)
let request = provider.createCredentialAssertionRequest(challenge: challenge)
```

### Database
```sql
-- magic_link_tokens: registratie + herstel login
-- auth_devices: device tracking + biometrie status
-- qr_login_tokens: QR sessie management
-- webauthn_credentials: passkey opslag (laragear)
-- webauthn_challenges: challenge opslag (custom)
```

**Verwijderd:** password kolom is niet meer nodig voor nieuwe projecten. Bestaande projecten: kolom nullable maken, niet verwijderen (backwards compat).

---

## 6. Security Best Practices

### Doen
- Magic links max 15 min geldig, single-use
- Rate limiting op alle auth endpoints
- Email enumeration preventie (altijd success tonen)
- `session()->save()` i.p.v. `session()->regenerate()`
- CSRF exceptions voor passkey/QR endpoints
- HTTPS verplicht in productie (WebAuthn vereist dit)
- Biometric credentials blijven lokaal (nooit naar server)
- `allowCredentials` meesturen in loginOptions (zie JudoToernooi fix 31-03-2026)

### Niet doen
- Wachtwoord velden tonen of aanbieden
- Magic links zonder expiry
- `session()->regenerate()` na AJAX login
- PIN als login methode
- Wachtwoord als fallback aanbieden

---

## 7. Implementatie per Project

### Prioriteit
1. **JudoToernooi** — wachtwoord login verwijderen, magic link toevoegen
2. **Herdenkingsportaal** — wachtwoord login verwijderen, magic link toevoegen
3. **HavunAdmin** — wachtwoord login verwijderen, magic link toevoegen
4. **SafeHavun** — wachtwoord login verwijderen, magic link toevoegen
5. **Infosyst** — biometrie + magic link toevoegen, wachtwoord verwijderen
6. **JudoScoreBoard** — native Android passkey implementatie

### Migratie Strategie (v4.0 → v5.0)
```
Fase 1: Magic link als login methode toevoegen (naast bestaande wachtwoord)
Fase 2: Biometrie prompt verplicht maken na eerste login
Fase 3: Wachtwoord login UI verwijderen (magic link als fallback)
Fase 4: Password kolom nullable maken in database (niet verwijderen)
```

### Native App Strategie
```
Android: Google Credential Manager API (passkeys via Play Services)
iOS:     ASAuthorization framework (passkeys via iCloud Keychain)
Magic link: Deep link / universal link opent de app direct
```

---

## 8. User Experience

### Eerste keer (onboarding)
```
"Welkom! Vul je naam en email in."
→ "Check je inbox voor de login link."
→ Link klikken → "Account actief!"
→ "Koppel je vingerafdruk voor snelle toegang" (verplicht op smartphone/native)
→ Klaar — volgende keer alleen vingerafdruk
```

### Dagelijks
```
Smartphone/native: App openen → vingerafdruk → binnen
Desktop: QR scannen met telefoon → binnen
```

### Nieuw apparaat / herstel
```
"Stuur mij een login link" → email → link klikken → ingelogd
→ "Koppel je vingerafdruk op dit apparaat"
→ Klaar
```

---

## 9. Monitoring & Metrics

Track via logging:
- Magic link success rate (verstuurd vs geverifieerd)
- Biometrie adoption rate (% users met passkey)
- QR login usage (desktop sessions)
- Failed auth attempts (fraud detection)

---

## 10. Dependencies

### Laravel Packages
```json
{
  "laragear/webauthn": "^4.0"
}
```

### Browser APIs (geen NPM packages nodig)
- WebAuthn (biometrie) — native
- Web Crypto API (fingerprint hashing) — native

---

## Referentie Documenten

| Document | Inhoud |
|----------|--------|
| `reference/unified-login-system.md` | Compleet technisch document (code, migrations, troubleshooting) |
| `patterns/magic-link-auth.md` | Magic link pattern (model, controller, email) |
| `runbooks/unified-login-procedure.md` | Stap-voor-stap implementatie procedure |
| `decisions/002-decentrale-auth.md` | Waarom decentrale auth (geen SSO) |

## Changelog

### v5.0 (31 maart 2026)
- **BREAKING:** Wachtwoorden volledig verwijderd als login methode
- Magic link wordt primaire onboarding + herstel methode
- Biometrie (passkey) wordt enige dagelijkse login methode op mobiel
- QR code blijft desktop login methode
- Native app support toegevoegd (Android Credential Manager, iOS ASAuthorization)
- Password kolom nullable in bestaande projecten (niet verwijderen)

### v4.0 (17 maart 2026)
- PIN login verwijderd uit standaard
- Magic link toegevoegd voor registratie en wachtwoord vergeten
- Strategie vereenvoudigd: 3 login methodes (ww, QR, biometrie) + magic link

### v3.0 (11 maart 2026)
- Originele versie met PIN, biometrie, QR, wachtwoord
