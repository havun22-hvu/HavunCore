# Unified Auth Strategy

> **Laatst bijgewerkt:** 17 maart 2026
> **Status:** Actieve standaard voor alle Havun-projecten
> **Versie:** 4.0
> **Eigenaar:** HavunCore

## Overzicht

Alle Havun Laravel-applicaties gebruiken dezelfde authenticatiestrategie:
- **Magic link** voor registratie en wachtwoord vergeten
- **Email/wachtwoord** als standaard login
- **QR code** voor desktop login (scan met telefoon)
- **Biometrie (WebAuthn/Passkey)** voor smartphone login
- **Decentraal** — elke app beheert eigen auth (ADR-002)

### Niet meer gebruikt (v4.0)
- ~~PIN login~~ (vervangen door biometrie + wachtwoord)
- ~~Device fingerprint als primaire herkenning~~ (nu alleen voor analytics)

---

## 1. Registratie & Eerste Login

### Flow
```
1. Gebruiker voert naam + email in
2. Magic link wordt verstuurd (15 min geldig, single-use)
3. Klik op link → account aangemaakt, direct ingelogd
4. Optioneel: wachtwoord instellen (voor toekomstige logins)
5. Optioneel: biometrie koppelen (smartphone)
```

### Technisch
- **MagicLinkToken** model (64-char random token)
- **Rate limiting:** max 3 magic links per 10 minuten per IP
- **Email enumeration preventie:** altijd success tonen
- **Email template:** branded, duidelijke CTA knop

### Waarom magic link?
- Geen wachtwoord nodig bij registratie → lagere drempel
- Email verificatie ingebouwd → geen aparte verificatie stap
- Veiliger dan wachtwoord-only registratie

---

## 2. Dagelijks Gebruik

### Smartphone
```
Biometrie (passkey) → WebAuthn API → ingelogd
Fallback: Email/wachtwoord
```
- **Types:** Face ID, Touch ID, vingerafdruk
- **Package:** laragear/webauthn (lokaal, geen cloud)

### Desktop
```
QR code tonen → scan met telefoon (ingelogd op PWA) → ingelogd op desktop
Fallback: Email/wachtwoord
```
- **QR verloopt:** na 5 minuten, vernieuw knop beschikbaar
- **Polling:** elke 2 seconden status check
- **Vereist:** telefoon moet al ingelogd zijn

### Fallback (altijd beschikbaar)
- **Email/wachtwoord** op alle platforms
- **Wachtwoord vergeten** via magic link

---

## 3. Wachtwoord Vergeten

### Flow
```
1. Gebruiker voert email in
2. Magic link wordt verstuurd (15 min geldig)
3. Klik op link → wachtwoord reset formulier
4. Nieuw wachtwoord instellen → direct ingelogd
```

Zelfde MagicLinkToken systeem als registratie, met type `password_reset`.

---

## 4. Step-Up Authentication (optioneel per project)

Voor gevoelige acties (betaling, delete account, wijzig email):

### Niveau 1: Wachtwoord herbevestiging
- Gebruiker voert wachtwoord opnieuw in
- Session-based: 15 min geldig

### Niveau 2: TOTP (optioneel)
- Google Authenticator / Authy
- QR code setup
- Backup codes (8-10 stuks)

### Voorbeelden
| Actie | Auth Niveau |
|-------|-------------|
| Factuur bekijken | Normale sessie |
| Factuur betalen | Wachtwoord bevestiging |
| IBAN wijzigen | TOTP (indien ingesteld) |
| Account verwijderen | TOTP + email bevestiging |

---

## 5. Technische Stack

### Backend (Laravel)
```php
// Magic link
$token = MagicLinkToken::generate($email, 'register', ['name' => $name]);
Mail::to($email)->send(new MagicLinkMail($token));

// Login pattern (KRITIEK)
Auth::guard('web')->login($user, true);
session()->save(); // NIET regenerate()
```

### Frontend
```javascript
// Platform detectie
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

// QR (desktop)
fetch('/auth/qr/generate') → toon QR → poll status

// Biometrie (smartphone)
navigator.credentials.get({ publicKey: options }) → passkey login
```

### Database
```sql
-- magic_link_tokens: registratie + wachtwoord vergeten
-- auth_devices: device tracking + biometrie status
-- qr_login_tokens: QR sessie management
-- webauthn_credentials: passkey opslag (laragear)
-- webauthn_challenges: challenge opslag (custom)
```

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

### Niet doen
- Wachtwoorden verplicht maken bij registratie
- Magic links zonder expiry
- `session()->regenerate()` na AJAX login
- PIN als login methode (te zwak, vervangen door biometrie)

---

## 7. Implementatie per Project

### Prioriteit
1. **JudoToernooi** — PIN verwijderen, magic link toevoegen
2. **Herdenkingsportaal** — PIN verwijderen, magic link toevoegen
3. **HavunAdmin** — magic link toevoegen
4. **SafeHavun** — magic link toevoegen
5. **Infosyst** — biometrie + magic link toevoegen
6. **HavunClub** — volledige implementatie

### Migratie Strategie
```
Fase 1: Magic link + wachtwoord vergeten toevoegen (geen breaking changes)
Fase 2: PIN login UI verwijderen (fallback naar wachtwoord)
Fase 3: PIN gerelateerde code + database opschonen
```

---

## 8. User Experience

### Eerste keer (onboarding)
```
"Welkom! Vul je naam en email in."
→ "Check je inbox voor de activatielink."
→ Link klikken → "Account actief! Stel optioneel een wachtwoord in."
→ "Koppel je vingerafdruk voor snelle toegang" (smartphone)
→ Klaar
```

### Dagelijks
```
Smartphone: App openen → vingerafdruk → binnen
Desktop: QR scannen met telefoon → binnen
Fallback: Email + wachtwoord
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

### v4.0 (17 maart 2026)
- PIN login verwijderd uit standaard
- Magic link toegevoegd voor registratie en wachtwoord vergeten
- Strategie vereenvoudigd: 3 login methodes (ww, QR, biometrie) + magic link

### v3.0 (11 maart 2026)
- Originele versie met PIN, biometrie, QR, wachtwoord
