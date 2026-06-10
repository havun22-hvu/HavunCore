---
title: Playwright E2E — Webapp PWA
type: runbook
scope: havuncore
last_check: 2026-06-10
---

# Playwright E2E — Webapp PWA

> End-to-end browser-tests voor de status-only PWA (`webapp/frontend`).
> Scope bewust beperkt tot de webapp — HavunCore Laravel is API/orchestrator en
> wordt al gedekt door de PHPUnit-suite (1243 tests).

## Wat het dekt

| Spec | Project | Flow |
|------|---------|------|
| `e2e/auth.spec.js` | desktop | Login-scherm (QR default op desktop) + wachtwoord-login → dashboard |
| `e2e/dashboard.spec.js` | desktop | StatusView (server/API/PM2), Projects-tab, NotificationBell (badge + dismiss + lege staat) |
| `e2e/qr-approve.spec.js` | desktop | `/qr/:code` goedkeuringsscherm (geldige + ongeldige code) |
| `e2e/biometric-setup.spec.js` | desktop | Passkey registreren (`navigator.credentials.create`) via menu |
| `e2e/biometric-login.mobile.spec.js` | mobile | Biometrische login (`navigator.credentials.get`) → dashboard |
| `e2e/qr-scanner.mobile.spec.js` | mobile | QR-scanner: biometrie-guard + doorgang naar camera |

## Twee projecten — desktop & mobile

`playwright.config.js` definieert twee projecten met device-emulatie (de app doet
device-detectie: QR/biometric-setup op desktop, biometric-login/QR-scanner op mobile).

- **desktop** — Desktop Chrome; draait alle specs behalve `*.mobile.spec.js`.
- **mobile** — Pixel 5 (touch + mobiele UA); draait alleen `*.mobile.spec.js`. Heeft
  `permissions: ['camera']` + fake-camera launch-flags voor de QR-scanner.

## Kernprincipe — volledige API-mock

De PWA is status-only: elk scherm draait op een handvol read-only API-calls naar
de Node-backend (`:8009`) en Laravel. Alle endpoints worden gestubd met
Playwright route-interception in `e2e/helpers.js` (`mockApi`). **Gevolg: geen
backend, geen DB, geen Socket.io-server nodig** — CI start alleen de Vite-server.

- `mockApi(page, overrides)` — registreert alle stubs; overschrijf per test een
  endpoint via `overrides` (bv. lege `healthAlerts`).
- `loginAs(page)` — seed't een `device_token` in localStorage zodat de app direct
  in het dashboard boot (omzeilt magic-link/biometric/QR, die niet headless-baar zijn).
- Beide moeten **vóór** `page.goto()` worden aangeroepen.

## Lokaal draaien

```bash
cd webapp/frontend
npm run test:e2e          # headless, alle specs
npm run test:e2e:ui       # interactieve UI-mode
npm run test:e2e:report   # laatste HTML-rapport openen
```

Playwright start de dev-server zelf (`webServer` in `playwright.config.js`) en
hergebruikt een al draaiende server lokaal.

## Twee valkuilen (opgelost, niet opnieuw introduceren)

1. **Dev-server i.p.v. preview.** `vite preview` serveert de productie-build
   mét PWA service-worker; die onderschept navigatie → `page.goto` faalt met
   `ERR_ABORTED`. De dev-server heeft `devOptions.enabled:false` (geen SW). Daarom
   draait E2E tegen `npm run dev`.
2. **Serieel (`workers:1`).** De dev-server compileert modules on-demand;
   parallelle navigaties racen tegen een koude compiler en time-outen. Een
   smoke-suite van deze omvang draait serieel in < 5s.

## Biometrie — WebAuthn virtual authenticator

`e2e/webauthn.js` koppelt via CDP (`WebAuthn.addVirtualAuthenticator`) een
platform-authenticator aan de context die `create()`/`get()` automatisch
beantwoordt — geen OS-prompt. Voor biometrische **login** wordt vooraf een
resident credential geïnjecteerd (`seedCredential: true`) zodat `get()` resolvet.

- **`rp.id` moet `localhost`** zijn (registrable suffix van de test-origin), anders
  faalt `create()` met SecurityError. Staat zo in de mock-payloads.
- De wegwerp-P-256-key wordt nooit geverifieerd (server is gemockt); hij hoeft
  enkel structureel geldig te zijn.
- Enable de authenticator **vóór** de UI die `isUVPAA()` / `create()` / `get()` aanroept.

## QR-scanner — camera

De headless fake-camera (`--use-fake-device-for-media-stream`) gedraagt zich per
omgeving anders (faalt of hangt op Windows-Chromium). Daarom assert de camera-test
géén werkende stream maar enkel dat de scanner voorbij de biometrie-guard naar de
camera gaat. Het echte decode→approve-pad zit in `qr-approve.spec.js`.

## Locaten op `title`, niet op accessible name

Knoppen met enkel een emoji (🔔 bel, ✕ dismiss) krijgen die emoji als
accessible name — niet hun `title`. Target ze via `button[title="..."]`.

## CI

Let op: `webapp` is een **aparte git-repo** (`havuncore-webapp`, branch `main`) —
`/webapp/` staat in HavunCore's `.gitignore`. De workflow leeft dus in die repo:
`webapp/.github/workflows/webapp-e2e.yml`, met path-filter `frontend/**` en
`working-directory: frontend`. Draait op push/PR naar main/master, cachet de
Chromium-browser op de lockfile-hash en uploadt het HTML-rapport als artifact.
