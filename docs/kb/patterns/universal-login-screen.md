---
title: Universal Login Screen Pattern
type: pattern
scope: havuncore
last_check: 2026-04-22
---

# Universal Login Screen Pattern

> **Status:** Standaard voor alle Havun projecten
> **Laatste update:** 14 april 2026
> **Basis:** Studieplanner design (dark theme, centered card)
> **Gerelateerd:** `magic-link-auth.md`, `reference/authentication-methods.md`, `standards/unified-auth-strategy.md`

## Overzicht

Elk Havun project bouwt zijn eigen login/registratie scherm. Dit document beschrijft de **standaard layout, flows en componenten** die elk project moet implementeren. Kleuren en branding zijn per project aanpasbaar.

### Eén flow, geen tabs

Er zijn geen aparte "login" en "registratie" schermen. Het systeem bepaalt zelf wat nodig is op basis van het emailadres. De gebruiker hoeft niet te kiezen.

| Situatie | Wat het systeem doet |
|----------|---------------------|
| **Biometric beschikbaar** (terugkerende gebruiker) | Toon biometric knop als primaire login |
| **Email bestaat** (nieuw apparaat) | Stuur magic link → inloggen → biometric koppelen |
| **Email bestaat niet** (nieuwe gebruiker) | Toon naam veld → account aanmaken via magic link |

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
|           |     [Form velden]          |           |
|           |     (dynamisch per stap)   |           |
|           |                            |           |
|           |     [Primaire actie knop]  |           |
|           |                            |           |
|           |     [Secundaire opties]    |           |
|           |                            |           |
|           +----------------------------+           |
|                max-width: 400px                    |
|                                                    |
+--------------------------------------------------+
```

**Geen tabs.** Het formulier past zich dynamisch aan op basis van de backend response.

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

## 2. Email-First Flow (geen tabs)

De gebruiker hoeft niet te kiezen tussen "inloggen" of "registreren". Het systeem bepaalt dit automatisch op basis van het emailadres.

### Stap 1: Biometric check (mobiel)

Als biometric beschikbaar is (terugkerende gebruiker op dit apparaat):

```
+----------------------------+
|       [App naam]           |
|     Welkom terug!          |
|                            |
|  [Inloggen met biometric]  |  <-- grote primaire knop
|                            |
|  Ander account of          |
|  nieuw apparaat?           |  <-- subtiele link → stap 2
+----------------------------+
```

### Stap 2: Email invoeren

Geen biometric, of gebruiker kiest "ander account":

```
+----------------------------+
|       [App naam]           |
|     [Ondertitel]           |
|                            |
|  E-mailadres               |
|  [____________________]    |
|                            |
|  [       Verder      ]     |  <-- stuurt email naar backend
+----------------------------+
```

### Stap 3a: Email bestaat → Magic link (login)

Backend herkent het emailadres → stuurt magic link:

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
|  Ander emailadres          |  <-- terug naar stap 2
+----------------------------+
```

Na klik op link → ingelogd → biometric koppelen op nieuw apparaat.

### Stap 3b: Email bestaat niet → Registratie velden tonen

Backend meldt `needs_registration: true` → toon extra velden:

```
+----------------------------+
|       [App naam]           |
|     Maak een account       |
|                            |
|  E-mailadres               |
|  [naam@voorbeeld.nl    ]   |  <-- al ingevuld vanuit stap 2
|                            |
|  Naam                      |
|  [____________________]    |
|                            |
|  [PROJECT-SPECIFIEKE       |  <-- optioneel per project
|   VELDEN HIER]             |     (organisatie, rol, etc.)
|                            |
|  [ Account aanmaken  ]     |  <-- stuurt magic link
+----------------------------+
```

Na klik op link → account actief → biometric koppelen.

### Desktop variant

Desktop volgt dezelfde email-first flow, maar toont ook QR code:

```
+----------------------------+
|       [App naam]           |
|                            |
|  Scan met je telefoon      |
|  +--------------------+    |
|  |     [QR CODE]      |    |
|  +--------------------+    |
|  Geldig nog 0:58           |
|                            |
|  -------- OF --------      |
|                            |
|  E-mailadres               |
|  [____________________]    |
|  [       Verder      ]     |  <-- email-first flow
+----------------------------+
```

QR status via **WebSocket** (GEEN polling) — zie `decisions/geen-polling.md`.

### Device detectie

```javascript
// Simpele check — geen externe library nodig
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

// Op basis hiervan:
// - Desktop: toon QR + email-first flow
// - Mobiel: toon biometric (als beschikbaar) + email-first flow
// - GEEN QR op mobiel, GEEN biometric op desktop
```

### Backend contract

Twee endpoints:

```
POST /api/auth/magic-link
Body: { email: string, name?: string, role?: string }

Response (email bestaat):
  200 { message: "Magic link verstuurd naar ..." }

Response (email bestaat niet, geen naam):
  422 { message: "Account niet gevonden", needs_registration: true }

Response (email bestaat niet, met naam):
  200 { message: "Magic link verstuurd naar ..." }
  (account wordt aangemaakt + magic link verstuurd)
```

```
POST /api/auth/device-login
Body: { email: string }

Gebruik: na succesvolle client-side biometric verificatie.
Direct token uitgeven voor bestaande accounts.

Response (email bestaat):
  200 { user: {...}, token: "1|abc..." }

Response (email bestaat niet):
  404 { message: "Account niet gevonden." }

Rate limit: 3/min per IP
```

**Flow op nieuw apparaat:**
1. Email invoeren → eerst checken of account bestaat (magic-link endpoint zonder naam)
2. Account bestaat + biometric beschikbaar → biometric prompt → device-login → ingelogd
3. Account bestaat + GEEN biometric → magic link flow (fallback)
4. Account bestaat niet → toon registratie velden

**Security:** Device-login vertrouwt client-side biometric check. Acceptabel voor apps zonder financiële/medische data. Rate limiting voorkomt brute force.

---

## 3. Componenten

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

## 4. Security Checklist

Bij elke implementatie controleren:

- [ ] **CSP nonce** op alle `<script>` en `<style>` tags (`@nonce` in Blade)
- [ ] **Geen inline styles** — gebruik CSS classes
- [ ] **CSRF token** op alle forms
- [ ] **Rate limiting** op login, registratie en magic link endpoints
- [ ] **Email enumeration prevention** — altijd success tonen bij magic link
- [ ] **Magic link tokens:** 64 tekens, 15 min geldig, single-use
- [ ] **Session:** `session()->save()` na login (NIET `regenerate()`)
- [ ] **QR status:** via WebSocket (GEEN polling)
- [ ] **HTTPS** verplicht in productie (WebAuthn + magic links vereisen dit)
- [ ] **Externe scripts:** SRI hash + `crossorigin="anonymous"`
- [ ] **Geen wachtwoord velden** — biometric + magic link dekt alles

---

## 5. Implementatie per Stack

### Laravel (Blade)

Gebruik de patterns uit `magic-link-auth.md` voor de backend.
Eén view met dynamisch formulier:
- `auth.blade.php` — email-first flow (stap 2 → 3a/3b)
- `magic-link-sent.blade.php` — bevestigingsscherm

CSP: `<style @nonce>`, `<script @nonce>`, geen `style=""` attributen.
Desktop: QR sectie toevoegen boven het email formulier.

### Node.js (plain HTML)

Eén `login.html` met JavaScript die de stappen afhandelt.
Gebruik `fetch()` voor API calls. QR via server-side image generation of client-side library met SRI.

### React Native (Expo)

Enkele `AuthScreen` met email-first flow. Zie Studieplanner als referentie-implementatie.
Biometric via `expo-local-authentication`.

---

## 6. Flow Samenvatting

```
MOBIEL — TERUGKERENDE GEBRUIKER (zelfde apparaat):
  Biometric (fingerprint/face)  -->  Token uit SecureStore  -->  Ingelogd

MOBIEL — NIEUW APPARAAT (email bestaat + biometric beschikbaar):
  Email invoeren  -->  Backend: 200 (account bestaat)
                  -->  Biometric prompt  -->  POST /api/auth/device-login
                  -->  Token ontvangen  -->  Ingelogd + biometric koppelen

MOBIEL — NIEUW APPARAAT (email bestaat, GEEN biometric):
  Email invoeren  -->  Backend: 200  -->  Magic link  -->  Klik  -->  Ingelogd

MOBIEL — NIEUWE GEBRUIKER (email bestaat niet):
  Email invoeren  -->  Backend: 422 (needs_registration)
                  -->  Naam + extra velden invullen
                  -->  Backend: 200  -->  Magic link  -->  Klik  -->  Account actief
                                                                  -->  Biometric koppelen

DESKTOP — QR (terugkerend):
  QR scannen met telefoon  -->  WebSocket bevestiging  -->  Ingelogd

DESKTOP — EMAIL-FIRST (nieuw apparaat):
  Zelfde flow als mobiel (zonder biometric stap)
```

**Kernprincipes:**
- De gebruiker kiest nooit tussen "login" of "registreren" — het systeem detecteert automatisch
- Magic link alleen voor: registratie + apparaten zonder biometric
- Biometric is de primaire login op alle apparaten met biometric hardware
- Na registratie (magic link): ELKE volgende login is biometric (ook op nieuwe apparaten)

---

## Referenties

| Document | Inhoud |
|----------|--------|
| `patterns/magic-link-auth.md` | Magic link backend implementatie (Laravel) |
| `reference/authentication-methods.md` | Welke methode wanneer |
| `standards/unified-auth-strategy.md` | Overkoepelende auth strategie |
| `decisions/geen-polling.md` | Waarom WebSocket i.p.v. polling voor QR |
| `decisions/002-decentrale-auth.md` | Waarom elk project eigen auth |
