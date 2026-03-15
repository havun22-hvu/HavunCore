# Unified Auth Strategy

> **Laatst bijgewerkt:** 11 maart 2026  
> **Status:** ✅ Actieve standaard voor alle Havun-projecten  
> **Eigenaar:** HavunCore

## Overzicht

Alle Havun-applicaties gebruiken dezelfde authenticatiestrategie:
- **Geen wachtwoorden** (tenzij gebruiker expliciet wil)
- **Geen pincode login** (vervangen door biometrie + magic links)
- **PWA-first** met native biometrie ondersteuning
- **Step-up authentication** voor gevoelige acties

---

## 1. Registratie & Eerste Login

### Flow
```
1. Gebruiker voert email in
2. Magic link wordt verstuurd
3. Klik op link → direct ingelogd
4. PWA installatie prompt (indien mobiel/desktop)
5. Biometrie koppelen (indien beschikbaar)
```

### Technisch
- **Laravel Breeze** met aangepaste magic link flow
- **Signed URLs** (1 uur geldig)
- **Rate limiting:** max 3 magic links per 10 minuten
- **Email template:** branded, duidelijke CTA

---

## 2. Dagelijks Gebruik

### Mobiel (PWA)
```
Biometrie → WebAuthn API → ingelogd
```
- **Fallback:** Apparaat pincode/patroon (native OS)
- **Biometrie types:** Face ID, Touch ID, vingerafdruk, gezichtsherkenning

### Desktop
```
QR code tonen → scan met PWA → ingelogd op desktop
```
- **QR refresh:** elke 60 seconden nieuwe code
- **WebSocket:** real-time login bevestiging
- **Sessie sync:** desktop + mobiel blijven synchroon

### Fallback (altijd beschikbaar)
- **Magic link via email** (voor alle platforms)
- **Wachtwoord** (optioneel, gebruiker kan zelf instellen)

---

## 3. Step-Up Authentication

Voor gevoelige acties (betaling, delete account, wijzig email):

### Niveau 1: Pincode (6 cijfers)
- Gebruiker stelt eenmalig in (bij eerste gevoelige actie)
- Opgeslagen als bcrypt hash
- Max 3 pogingen, daarna 15 min cooldown

### Niveau 2: TOTP (optioneel)
- Google Authenticator / Authy
- QR code setup
- Backup codes (10x)

### Voorbeelden
| Actie | Auth Niveau |
|-------|-------------|
| Factuur bekijken | Normale sessie |
| Factuur betalen | Pincode |
| IBAN wijzigen | TOTP (indien ingesteld) of pincode |
| Account verwijderen | TOTP + email bevestiging |

---

## 4. Technische Stack

### Frontend (PWA)
```javascript
// Biometrie check
if (window.PublicKeyCredential) {
  // WebAuthn beschikbaar
  navigator.credentials.get({ publicKey: options })
}

// QR scanner
import { BrowserQRCodeReader } from '@zxing/library'
```

### Backend (Laravel)
```php
// Magic link
use Illuminate\Support\Facades\URL;

$url = URL::temporarySignedRoute(
    'auth.verify', 
    now()->addHour(), 
    ['user' => $user->id]
);

// Step-up
middleware(['auth', 'step-up:pincode'])
middleware(['auth', 'step-up:totp'])
```

### Database
```sql
-- users table
email (unique)
email_verified_at
step_up_pincode (bcrypt hash, nullable)
totp_secret (encrypted, nullable)
biometric_credentials (json, nullable) -- WebAuthn credentials

-- sessions table
Laravel default + PWA metadata
```

---

## 5. Security Best Practices

### ✅ Doen
- Magic links max 1 uur geldig
- Rate limiting op alle auth endpoints
- IP-based anomaly detection (nieuw land → extra verificatie)
- Biometric credentials opslaan per apparaat
- Session tokens roteren bij step-up

### ❌ Niet doen
- Pincode als primaire login
- Wachtwoorden verplicht maken
- Magic links zonder expiry
- Biometric data naar server sturen (blijft lokaal!)

---

## 6. Implementatie per Project

### Volgorde (prioriteit)
1. **HavunAdmin** (betalingen, IBAN wijzigingen → step-up essentieel)
2. **Studieplanner** (huidige pincode vervangen)
3. **JudoToernooi** (coach portal + organisator betalingen)
4. **HavunClub** (ledenadministratie + incasso)
5. **HavunVet** (patiëntgegevens AVG-gevoelig)
6. **Herdenkingsportaal** (⚠️ LIVE, voorzichtig uitrollen)

### Migratie Strategie
```
Fase 1: Nieuwe auth naast oude (feature flag)
Fase 2: Gebruikers migreren (opt-in)
Fase 3: Force migration (1 maand notice)
Fase 4: Oude auth verwijderen
```

---

## 7. User Experience

### Eerste keer (onboarding)
```
"Welkom! We sturen je een inloglink."
→ Email check
→ "Koppel je vingerafdruk voor snelle toegang"
→ Klaar
```

### Dagelijks
```
App openen → vingerafdruk → binnen
```

### Desktop + mobiel
```
Desktop: "Scan deze QR met je telefoon"
Mobiel: Camera openen → scan → beide ingelogd
```

### Gevoelige actie
```
"Bevestig met je pincode" 
(6 cijfers, numpad, duidelijke feedback)
```

---

## 8. Monitoring & Metrics

Track via HavunCore Task Queue:
- Magic link success rate
- Biometrie adoption rate
- Step-up trigger frequency
- Failed auth attempts (fraud detection)

Alerts:
- >10 failed step-up pogingen per user
- Magic link click rate <50%
- Biometrie enrollment <30% (na 1 maand)

---

## 9. Dependencies

### NPM Packages
```json
{
  "@simplewebauthn/browser": "^9.0.0",
  "@zxing/library": "^0.20.0",
  "otpauth": "^9.2.0"
}
```

### Laravel Packages
```json
{
  "laravel/breeze": "^2.0",
  "pragmarx/google2fa-laravel": "^2.1",
  "spatie/laravel-qrcode": "^3.0"
}
```

### Browser APIs
- WebAuthn (biometrie)
- Web Crypto API (key generation)
- Service Worker (offline magic links)
- Push API (login notifications)

---

## 10. Toekomstige Verbeteringen

- **Passkeys** (platform authenticators, sync via iCloud/Google)
- **WebAuthn roaming** (YubiKey support)
- **Risk-based auth** (ML model voor fraud detection)
- **SSO** tussen Havun-apps (1x login, overal binnen)

---

## Referenties

- [WebAuthn Guide](https://webauthn.guide/)
- [Laravel Signed URLs](https://laravel.com/docs/11.x/urls#signed-urls)
- [OWASP Auth Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
