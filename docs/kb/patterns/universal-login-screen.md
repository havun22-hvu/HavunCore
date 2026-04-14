# Universal Login Screen Pattern

> **Status:** Standaard voor alle Havun projecten
> **Laatste update:** 14 april 2026
> **Basis:** Studieplanner design (dark theme, centered card)
> **Gerelateerd:** `magic-link-auth.md`, `reference/authentication-methods.md`, `standards/unified-auth-strategy.md`

## Overzicht

Elk Havun project bouwt zijn eigen login/registratie scherm. Dit document beschrijft de **standaard layout, flows en componenten** die elk project moet implementeren. Kleuren en branding zijn per project aanpasbaar.

### Drie schermen

| Scherm | Functie | Primaire methode |
|--------|---------|-----------------|
| **Login** | Bestaande gebruiker inloggen | Desktop: QR + wachtwoord / Mobiel: biometric + wachtwoord |
| **Registratie** | Nieuw account aanmaken | Magic link via email |
| **Wachtwoord vergeten** | Wachtwoord resetten | Magic link via email |

---

## 1. Design Systeem

### Basis layout (Studieplanner-stijl)

```
+--------------------------------------------------+
|                                                    |
|         [Dark/themed background — full screen]     |
|                                                    |
|           +----------------------------+           |
|           |                            |           |
|           |     [App logo / naam]      |           |
|           |     [Ondertitel]           |           |
|           |                            |           |
|           |     [Login/Register tabs]  |           |
|           |                            |           |
|           |     [Form velden]          |           |
|           |                            |           |
|           |     [Primaire actie knop]  |           |
|           |                            |           |
|           |     [Alternatieve login]   |           |
|           |                            |           |
|           |     [Links onderaan]       |           |
|           |                            |           |
|           +----------------------------+           |
|                max-width: 400px                    |
|                                                    |
+--------------------------------------------------+
```

### Kleuren (per project aanpasbaar)

Elk project definieert zijn eigen kleurenschema via CSS custom properties:

```css
:root {
  /* === PROJECT KLEUREN — PAS DIT AAN === */
  --color-primary: #10b981;       /* Studieplanner: emerald */
  --color-primary-dark: #059669;
  --color-primary-light: #34d399;

  --color-bg: #1a1a2e;            /* Dark background */
  --color-bg-gradient: #16213e;   /* Gradient end */
  --color-surface: rgba(255, 255, 255, 0.05);  /* Glass effect */
  --color-surface-solid: #0f3460;
  --color-border: rgba(255, 255, 255, 0.1);

  --color-text: #f1f5f9;          /* Primary text */
  --color-text-secondary: #94a3b8;
  --color-text-inverse: #1a1a2e;

  --color-danger: #ef4444;
  --color-success: #10b981;
  --color-warning: #f59e0b;

  /* === LAYOUT === */
  --radius-sm: 4px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --radius-full: 9999px;

  --spacing-xs: 4px;
  --spacing-sm: 8px;
  --spacing-md: 16px;
  --spacing-lg: 24px;
  --spacing-xl: 32px;
}
```

**Voorbeelden per project:**

| Project | Primary | Achtergrond | Stijl |
|---------|---------|-------------|-------|
| Studieplanner | `#10b981` (emerald) | Dark `#1a1a2e` | Dark theme |
| HavunAdmin | `#0066cc` (blue) | Dark `#1a1a2e` | Dark theme |
| Herdenkingsportaal | `#7c3aed` (purple) | Light `#f8fafc` | Light theme |
| JudoToernooi | `#dc2626` (red) | Dark `#1a1a2e` | Dark theme |
| VPDUpdate | `#667eea` (indigo) | Gradient `#667eea → #764ba2` | Gradient |

### Typografie

```css
/* System font stack — geen externe fonts laden */
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
             'Helvetica Neue', Arial, sans-serif;

/* Schaal */
h1    { font-size: 28px; font-weight: 700; }  /* App naam */
h2    { font-size: 22px; font-weight: 600; }
body  { font-size: 16px; font-weight: 400; }
small { font-size: 14px; font-weight: 400; }
label { font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
```

---

## 2. Login Scherm

### Desktop (browser)

```
+----------------------------+
|       [App naam]           |
|     [Ondertitel]           |
|                            |
|  [ Login ]  [ Registreren ]|  <-- tabs
|                            |
|  E-mailadres               |
|  [____________________]    |
|                            |
|  Wachtwoord                |
|  [____________________]    |
|                            |
|  [     Inloggen      ]     |  <-- primary button
|                            |
|  -------- OF --------      |
|                            |
|  Scan met je telefoon      |
|  +--------------------+    |
|  |                    |    |
|  |     [QR CODE]      |    |
|  |                    |    |
|  +--------------------+    |
|  Geldig nog 0:58           |
|                            |
|  Wachtwoord vergeten?      |
+----------------------------+
```

**Gedrag:**
- QR code auto-refresh elke 60 seconden
- QR status via **WebSocket** (GEEN polling) — zie `decisions/geen-polling.md`
- Wachtwoord = altijd beschikbaar als fallback
- "Wachtwoord vergeten?" link onderaan

### Mobiel (smartphone/PWA)

```
+----------------------------+
|       [App naam]           |
|     [Ondertitel]           |
|                            |
|  [ Login ]  [ Registreren ]|
|                            |
|  E-mailadres               |
|  [____________________]    |
|                            |
|  Wachtwoord                |
|  [____________________]    |
|                            |
|  [     Inloggen      ]     |
|                            |
|  [Inloggen met biometric]  |  <-- als beschikbaar
|                            |
|  Wachtwoord vergeten?      |
+----------------------------+
```

**Gedrag:**
- **GEEN QR code tonen** — heeft geen zin op mobiel
- Biometric knop alleen tonen als `biometricsAvailable && biometricsEnabled`
- Biometric label: "Inloggen met Face ID" / "Inloggen met vingerafdruk" (device-dependent)

### Device detectie

```javascript
// Simpele check — geen externe library nodig
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

// Op basis hiervan:
// - Desktop: toon QR sectie, verberg biometric
// - Mobiel: toon biometric, verberg QR
```

---

## 3. Registratie Scherm

Magic link is de primaire registratiemethode.

```
+----------------------------+
|       [App naam]           |
|     Maak een account       |
|                            |
|  [ Login ]  [ Registreren ]|  <-- tabs, "Registreren" actief
|                            |
|  Naam                      |
|  [____________________]    |
|                            |
|  E-mailadres               |
|  [____________________]    |
|                            |
|  [PROJECT-SPECIFIEKE       |  <-- optioneel: extra velden
|   VELDEN HIER]             |     per project (organisatie, rol, etc.)
|                            |
|  [ Account aanmaken  ]     |  <-- stuurt magic link
|                            |
+----------------------------+
```

**Flow:**
1. Gebruiker vult naam + email in (+ optionele project-specifieke velden)
2. Systeem stuurt magic link naar email
3. Gebruiker klikt link -> account aangemaakt + ingelogd
4. Na eerste login: optioneel wachtwoord instellen en/of biometric registreren

**GEEN wachtwoord veld bij registratie.** Wachtwoord instellen is optioneel na eerste login.

### "Magic link verstuurd" scherm

```
+----------------------------+
|       [App naam]           |
|                            |
|     [Email icoon]          |
|                            |
|  Check je inbox!           |
|                            |
|  We hebben een link        |
|  gestuurd naar             |
|  naam@voorbeeld.nl         |
|                            |
|  Link is 15 min geldig     |
|                            |
|  [Opnieuw versturen]       |  <-- rate limited: 3 per 10 min
|                            |
|  Terug naar login          |
+----------------------------+
```

---

## 4. Wachtwoord Vergeten Scherm

Gebruikt dezelfde magic link flow.

```
+----------------------------+
|       [App naam]           |
|                            |
|  Wachtwoord vergeten       |
|                            |
|  E-mailadres               |
|  [____________________]    |
|                            |
|  [ Reset link versturen ]  |
|                            |
|  Terug naar login          |
+----------------------------+
```

**Flow:**
1. Gebruiker vult email in
2. Systeem stuurt magic link (type `password_reset`)
3. Gebruiker klikt link -> wachtwoord reset formulier
4. Nieuw wachtwoord instellen -> ingelogd

**Security:** Altijd success tonen, nooit "email niet gevonden" (email enumeration prevention).

---

## 5. Componenten

### Tabs (Login / Registreren)

```html
<div class="auth-tabs">
  <button class="auth-tab active">Login</button>
  <button class="auth-tab">Registreren</button>
</div>
```

```css
.auth-tabs {
  display: flex;
  background: var(--color-surface);
  border-radius: var(--radius-md);
  padding: 4px;
  max-width: 320px;
  margin: 0 auto var(--spacing-xl);
}

.auth-tab {
  flex: 1;
  padding: var(--spacing-sm) var(--spacing-md);
  border: none;
  background: transparent;
  border-radius: 6px;
  color: var(--color-text-secondary);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.auth-tab.active {
  background: var(--color-bg);
  color: var(--color-primary);
  font-weight: 600;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}
```

### Input velden

```css
.auth-input {
  width: 100%;
  padding: 12px 16px;
  background: var(--color-surface);
  border: 2px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-text);
  font-size: 15px;
  transition: border-color 0.2s;
}

.auth-input:focus {
  outline: none;
  border-color: var(--color-primary);
}

.auth-input::placeholder {
  color: var(--color-text-secondary);
}
```

### Primary button

```css
.auth-btn {
  width: 100%;
  padding: 14px;
  background: var(--color-primary);
  color: var(--color-text-inverse);
  border: none;
  border-radius: var(--radius-md);
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.auth-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.auth-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}
```

### QR sectie (alleen desktop)

```css
.qr-section {
  text-align: center;
  margin-top: var(--spacing-lg);
}

.qr-container {
  display: inline-block;
  padding: 8px;
  background: white;           /* QR altijd op wit */
  border: 2px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.qr-timer {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin-top: var(--spacing-sm);
}

.qr-timer.expiring {
  color: var(--color-danger);
  font-weight: 600;
}
```

### Divider

```css
.auth-divider {
  display: flex;
  align-items: center;
  margin: var(--spacing-lg) 0;
  color: var(--color-text-secondary);
  font-size: 13px;
}

.auth-divider::before,
.auth-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--color-border);
}

.auth-divider span {
  padding: 0 12px;
}
```

---

## 6. Security Checklist

Bij elke implementatie controleren:

- [ ] **CSP nonce** op alle `<script>` en `<style>` tags (`@nonce` in Blade)
- [ ] **Geen inline styles** — gebruik CSS classes
- [ ] **CSRF token** op alle forms
- [ ] **Rate limiting** op login, registratie en magic link endpoints
- [ ] **Email enumeration prevention** — altijd success tonen bij magic link
- [ ] **Magic link tokens:** 64 tekens, 15 min geldig, single-use
- [ ] **Wachtwoord hashing:** bcrypt (Laravel default)
- [ ] **Session:** `session()->save()` na login (NIET `regenerate()`)
- [ ] **QR status:** via WebSocket (GEEN polling)
- [ ] **HTTPS** verplicht in productie (WebAuthn + magic links vereisen dit)
- [ ] **Externe scripts:** SRI hash + `crossorigin="anonymous"`
- [ ] **Geen wachtwoord als primaire methode promoten** — altijd fallback

---

## 7. Implementatie per Stack

### Laravel (Blade)

Gebruik de patterns uit `magic-link-auth.md` voor de backend.
Views in `resources/views/auth/`:
- `login.blade.php`
- `register.blade.php`
- `forgot-password.blade.php`
- `magic-link-sent.blade.php`
- `reset-password.blade.php`

CSP: `<style @nonce>`, `<script @nonce>`, geen `style=""` attributen.

### Node.js (plain HTML)

Aparte HTML bestanden: `login.html`, `register.html`.
Gebruik `fetch()` voor API calls. QR via server-side image generation of client-side library met SRI.

### React Native (Expo)

Enkele `AuthScreen` met tabs. Zie Studieplanner als referentie-implementatie.
Biometric via `expo-local-authentication` of `react-native-webauthn`.

---

## 8. Flow Samenvatting

```
REGISTRATIE:
  Naam + Email  -->  Magic link email  -->  Klik link  -->  Account actief
                                                        -->  Optioneel: wachtwoord instellen
                                                        -->  Optioneel: biometric registreren

LOGIN (desktop):
  Email + Wachtwoord  -->  Ingelogd
  OF
  QR scannen met telefoon  -->  WebSocket bevestiging  -->  Ingelogd

LOGIN (mobiel):
  Email + Wachtwoord  -->  Ingelogd
  OF
  Biometric (fingerprint/face)  -->  Ingelogd

WACHTWOORD VERGETEN:
  Email  -->  Magic link email  -->  Klik link  -->  Nieuw wachtwoord instellen  -->  Ingelogd
```

---

## Referenties

| Document | Inhoud |
|----------|--------|
| `patterns/magic-link-auth.md` | Magic link backend implementatie (Laravel) |
| `reference/authentication-methods.md` | Welke methode wanneer |
| `standards/unified-auth-strategy.md` | Overkoepelende auth strategie |
| `decisions/geen-polling.md` | Waarom WebSocket i.p.v. polling voor QR |
| `decisions/002-decentrale-auth.md` | Waarom elk project eigen auth |
