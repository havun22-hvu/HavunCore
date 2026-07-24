---
title: "Onderzoek: update-banner havuncore-webapp — niet reproduceerbaar, flow nu getest"
type: plan
scope: havuncore-webapp
status: afgerond (geen fix nodig)
date: 2026-07-24
---

# Update-banner PWA — gemeten, niet gereproduceerd

**Uitkomst (24-07-2026): de update-flow werkt in de productie-build.** Het openstaande punt
"update-banner activeert wachtende SW niet zichtbaar" is in geen enkel getest scenario
reproduceerbaar. Er is geen code gewijzigd; wel is de flow vastgelegd in een E2E-suite die
tot nu toe ontbrak.

## Wat er gemeten is

Playwright tegen `vite preview` op de echte productie-build (de bestaande E2E-suite draait
tegen de dev-server, waar de SW uit staat — `devOptions.enabled: false`). "Nieuwe versie
publiceren" = een byte wijzigen in `dist/sw.js`, daarna `registration.update()`.

| Scenario | Banner | Klik activeert + herlaadt |
|---|---|---|
| Update < 60s na registratie ("eigen" update volgens workbox) | ✅ | ✅ |
| Update > 60s na registratie (workbox ziet dit als *extern*) | ✅ | ✅ |
| Waiting worker bestond al bij page load | ✅ | ✅ |

De 60-seconden-grens is `REGISTRATION_TIMEOUT_DURATION` in workbox-window: daarna geldt een
gevonden update als extern, met een ander event-pad. Dat is het pad waar onze eigen checks
(interval van 5 min, visibility-change, na login) altijd in vallen — juist daarom expliciet
getest. Beide paden gedragen zich gelijk.

**Valse start, ter waarschuwing:** een eerste meetronde leek te bewijzen dat de banner niet
verscheen. Dat was een meetfout — Playwright's `isVisible()` wacht niet, en workbox dispatcht
zijn `waiting`-event pas 200 ms na `installed`. Met een wachtende assertie verschijnt de banner
gewoon. Er is toen kort een eigen waiting-worker-detectie in `useServiceWorker.js` gebouwd;
die is verworpen zodra bleek dat de oude code beide scenario's óók haalt.

## Ook gecontroleerd (niet de oorzaak)

- `sw.js` op productie: `Cache-Control: no-cache, no-store, must-revalidate` — correct.
- De gedeployde `sw.js` handelt `SKIP_WAITING` af en zet geen `clientsClaim`. Dat klopt bij
  `registerType: 'prompt'`: de gebruiker beslist wanneer de nieuwe versie overneemt.
- Geen dubbele SW-registratie (`injectRegister: 'auto'` injecteert niets naast de virtual module).

## Wat nog open staat

De waarneming die het handover-punt opleverde is niet nagebootst. Verschillen die nog kunnen
meespelen, en die alleen in een echte sessie zichtbaar worden:

- geïnstalleerde PWA (standalone) in plaats van een browser-tab;
- meerdere tabs/clients open op dezelfde origin;
- de gedeployde build is van 14-07 — de banner is dus nooit op een verse deploy beproefd.

Volgende keer dat het zich voordoet: eerst kijken of `navigator.serviceWorker.getRegistration()`
een `waiting` heeft. Zo ja, dan is het de UI-kant; zo nee, dan is het detectie/caching.

## Toegevoegd

- `havuncore-webapp/frontend/e2e-pwa/sw-update.spec.js` — beide update-vensters
- `havuncore-webapp/frontend/playwright.pwa.config.js` — bouwt en serveert `dist/`
- `npm run test:e2e:pwa`
