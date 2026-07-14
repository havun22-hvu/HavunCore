---
title: "Pattern: PWA-schil voor Laravel/Blade-apps"
type: pattern
scope: havuncore
last_check: 2026-07-14
---

# PWA-schil voor Laravel/Blade-apps

> **Referentie-implementatie:** HavunClub (gezins-PWA + aparte admin-PWA, live).
> **Wanneer:** een Blade/Livewire-app installeerbaar maken (manifest + service worker),
> zonder SPA. Voor Vite/React-PWA's: zie SafeHavun/Agorano (`vite-plugin-pwa`).

## Onderdelen (HavunClub-bestanden als voorbeeld)

| Onderdeel | Bestand |
|-----------|---------|
| Manifest (evt. 2: user + admin) | `public/manifest.json`, `public/manifest-admin.json` |
| Service worker | `public/sw.js` |
| Registratie + meta-tags | `resources/views/components/app-shell.blade.php` |
| Push opt-in/opt-out | `resources/js/app.js` (`initPush`) |

## Cache-strategie — de kernregels

1. **Network-first met cache-fallback** voor assets; versienaam in de cache-key
   (`<app>-<scope>-v5`).
2. **HTML/navigatie NOOIT cachen.** Pagina's bevatten een sessie-gebonden CSRF-token;
   een gecachte loginpagina serveert een verlopen token → 419 bij submit → "inloggen
   lukt niet" op mobiel. POST nooit cachen. (Zelfde reden waarom `SecurityHeaders`
   HTML-responses `Cache-Control: no-store` geeft.)
3. **Versie-bust bij deploy:** (a) bump de `CACHE`-constante — `activate` ruimt oude
   caches op; (b) registreer met `updateViaCache: 'none'` + `reg.update()` zodat een
   nieuwe deploy wordt opgepikt zonder dat de gebruiker "cache moet legen".
4. **Nginx:** `location = /sw.js` altijd `Cache-Control "no-cache, must-revalidate"` —
   zit in het standaard Havun-vhost-patroon (zie `runbooks/nieuw-project-opzetten.md`).

## iOS-valkuil

iOS negeert het manifest grotendeels → aparte `apple-mobile-web-app-*` meta-tags +
`apple-mobile-web-app-title` voor het home-screen-label. Zonder deze tags heet de app
op iOS naar de `<title>`.

## CSRF op uit-cache geladen pagina's

Forms met `data-csrf-refresh` halen vlak vóór submit een vers token op via `GET /csrf-token`
(`initCsrfRefresh` in `app.js`). Zie `patterns/csrf-token-refresh.md`.

## Web-push

Package `laravel-notification-channels/webpush`; VAPID-keys via `config/webpush.php`
(`VAPID_SUBJECT/PUBLIC_KEY/PRIVATE_KEY` — in de Vault, zie HavunCore health-alerts-push).
Opt-in/opt-out-knop in `app.js`. Install-prompt: geen eigen `beforeinstallprompt`-handler
nodig — native browserprompt volstaat.

## Testen

E2E van PWA-flows: zie `runbooks/playwright-e2e-laravel.md` (Laravel/Blade) en
`runbooks/playwright-e2e-webapp.md` (valkuil: `vite preview`-SW breekt `page.goto` —
gebruik dev-server).
