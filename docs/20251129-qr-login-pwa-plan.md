# QR Login PWA - Plan 29 November 2025

## ✅ Wat is Gedaan

### PWA Setup (Herdenkingsportaal)
- [x] Service worker (`/public/sw.js`)
- [x] Manifest bestond al (`site.webmanifest`)
- [x] App icons bestonden al (192x192, 512x512)

### QR Scanner in App
- [x] Scanner pagina (`/resources/views/auth/qr-scanner.blade.php`)
- [x] Menu item "📱 Scan om in te loggen" (alleen mobiel)
- [x] Route `/scan` met auth middleware
- [x] QR-scanner library (qr-scanner@1.4.2)

### HavunCore API Endpoints
- [x] `POST /api/auth/qr/approve-from-app` - voor client apps
- [x] `POST /api/auth/qr/approve-authenticated` - voor device token auth
- [x] Device info tonen op approve pagina

### Desktop Install Banner
- [x] Component (`/resources/views/components/pwa-install-banner.blade.php`)
- [x] Toegevoegd aan login pagina
- [x] Alleen zichtbaar op desktop

---

## 🔴 Te Fixen

### 1. Logout werkt niet op staging
**Symptoom:** Uitloggen lukt niet
**Te onderzoeken:**
- [ ] Check Laravel session config
- [ ] Check CSRF token handling
- [ ] Check logout route
- [ ] Test in incognito

### 2. Complete QR Flow Testen
- [ ] Desktop QR code verschijnt
- [ ] Telefoon camera opent
- [ ] QR scan succesvol
- [ ] API call naar HavunCore werkt
- [ ] Desktop wordt automatisch ingelogd

---

## 🧪 Test Checklist

### Desktop Flow
```
1. Ga naar: staging.herdenkingsportaal.nl/login
2. Check: Paarse "Installeer app" banner zichtbaar? (alleen desktop)
3. Check: QR code zichtbaar? (alleen desktop)
4. Check: QR code refresht elke 5 min?
```

### Mobiel Flow
```
1. Ga naar: staging.herdenkingsportaal.nl
2. Log in met email/wachtwoord
3. Open hamburger menu
4. Check: "📱 Scan om in te loggen" zichtbaar?
5. Tap "Scan"
6. Check: Camera permissie gevraagd?
7. Check: Camera opent?
8. Scan QR code van desktop
9. Check: Success melding?
10. Check desktop: Automatisch ingelogd?
```

### PWA Installatie
```
1. Open site op telefoon (Chrome)
2. Tap menu (3 dots)
3. Tap "Toevoegen aan startscherm"
4. Check: App icon op homescreen?
5. Open app via icon
6. Check: Fullscreen (geen browser UI)?
```

---

## 🔧 Mogelijke Issues & Oplossingen

### CORS Errors
**Symptoom:** Network error in console bij API call
**Oplossing:** Check HavunCore CORS config voor staging domain

### QR Library Niet Geladen
**Symptoom:** `QrScanner is not defined` in console
**Oplossing:** Check of CDN URL correct is, probeer andere CDN

### Email Niet Gevonden
**Symptoom:** "Email niet gevonden" na scan
**Oorzaak:** User bestaat niet in HavunCore `auth_users` tabel
**Oplossing:** User moet in beide systemen bestaan

### Session Polling Werkt Niet
**Symptoom:** Desktop logt niet automatisch in na scan
**Oorzaak:** Polling stopt of QR session verlopen
**Oplossing:** Check polling interval, check session expiry

### Camera Werkt Niet
**Symptoom:** Zwart scherm of "geen toegang"
**Oorzaak:** HTTPS vereist voor camera API, of permissie geweigerd
**Oplossing:** Moet op HTTPS draaien, user moet permissie geven

---

## 📁 Aangepaste Bestanden

### Herdenkingsportaal Staging
```
/var/www/herdenkingsportaal/staging/
├── public/
│   └── sw.js                          # Service worker (nieuw)
├── resources/views/
│   ├── auth/
│   │   ├── login.blade.php            # Banner toegevoegd
│   │   └── qr-scanner.blade.php       # Scanner pagina (nieuw)
│   ├── components/
│   │   └── pwa-install-banner.blade.php  # Desktop banner (nieuw)
│   └── layouts/
│       └── navigation.blade.php       # Menu item toegevoegd
└── routes/
    └── web.php                        # /scan route toegevoegd
```

### HavunCore
```
D:\GitHub\HavunCore\
├── app/Http/Controllers/Api/
│   └── QrAuthController.php           # Nieuwe methods
├── app/Http/Controllers/Web/
│   └── AuthApproveController.php      # Device info display
└── routes/
    └── api.php                        # Nieuwe routes
```

---

## 🎯 Gewenste Eindresultaat

```
┌─────────────────────────────────────────────────────────┐
│  DESKTOP (login pagina)                                 │
│  ┌─────────────────────────────────────────────────┐   │
│  │ 📱 Installeer de app op je telefoon            │   │
│  │    Scan straks de QR code met de app...        │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│         ┌───────────┐                                  │
│         │ QR CODE   │  ← Bevat approve URL             │
│         │           │                                  │
│         └───────────┘                                  │
│                                                         │
│  [Email]     [Wachtwoord]     [Login]                  │
└─────────────────────────────────────────────────────────┘
                      │
                      │ Scan met telefoon
                      ▼
┌─────────────────────────────────────────────────────────┐
│  TELEFOON (Herdenkingsportaal app)                      │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ 📷 Camera viewfinder                           │   │
│  │                                                 │   │
│  │      [ QR scan frame ]                         │   │
│  │                                                 │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  "Richt camera op QR code"                             │
└─────────────────────────────────────────────────────────┘
                      │
                      │ QR gescand → API call
                      ▼
┌─────────────────────────────────────────────────────────┐
│  DESKTOP (automatisch)                                  │
│                                                         │
│  ✅ Ingelogd! Doorsturen naar dashboard...             │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 📝 Notities

- Herdenkingsportaal heeft eigen Laravel auth (sessions)
- HavunCore heeft device token auth systeem
- QR flow gebruikt email uit Herdenkingsportaal session
- User moet in BEIDE systemen bestaan met zelfde email

---

*Laatst bijgewerkt: 28 november 2025, 23:xx*
