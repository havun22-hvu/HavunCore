---
title: Playwright E2E — uitrolplan over alle projecten
type: reference
scope: alle-projecten
status: ACTIEF
last_reviewed: 2026-06-20
---

# Playwright E2E — uitrolplan

> Doel: het bindend beleid `test-quality-policy.md` §10 (E2E op kritieke
> gebruikersflows voor projecten met een browser-UI) daadwerkelijk uitgerold
> krijgen. Dit document is de **status + volgorde**; de uitvoering gebeurt per
> project in de eigen project-sessie.

## Stand van zaken (2026-06-20)

| Project | UI-type | Playwright-status | Blauwdruk |
|---------|---------|-------------------|-----------|
| **havuncore-webapp** | React PWA | ✅ **werkend** — 6 specs (12 tests), CI draait | webapp |
| **JudoToernooi** | Laravel + Blade | ⚠️ 7 specs aanwezig, **CI draait ze niet** | laravel |
| **Herdenkingsportaal** | Laravel + Blade | 🟡 dep + `playwright.config.ts`, **0 specs** | laravel |
| **HavunAdmin** | Laravel + Blade | ❌ niets | laravel |
| **Infosyst** | Laravel + Blade | ❌ niets | laravel |
| **SafeHavun** | Laravel + Blade | ❌ niets | laravel |
| **HavunClub** | Laravel + Blade | ❌ niets — **geparkeerd** | laravel |
| **Agorano** | React PWA | ❌ niets — Fase 1 (scaffold staat) | webapp |

### Buiten scope (geen browser-UI — §10 uitgezonderd)

| Project | Reden |
|---------|-------|
| HavunCore (Laravel) | pure orchestrator/API, geen UI — PHPUnit is de juiste laag |
| JudoScoreBoard | React Native (Expo) native app — Jest unit/component |
| Studieplanner | React Native (Expo) native app — Jest unit/component |
| Aeterna | Rust + Tauri desktop app — geen web-E2E |
| Munus, Havunity | bestaan (nog) niet / geparkeerd |

## Uitrolvolgorde — kritieke flows + quick wins eerst

Prioriteit volgt §10-laag-1: betalingen en auth zijn het hoogste risico; projecten
waar de setup al deels staat zijn de goedkoopste winst.

1. **JudoToernooi — CI-stap toevoegen** (quick win). 7 specs bestaan al, maar `ci.yml`
   draait ze niet → dode dekking. Alleen een Playwright-job toevoegen. Geen nieuwe dep.
2. **Herdenkingsportaal — specs schrijven** (quick win). Dep + config staan al; alleen de
   flows afdekken: login → memorial CRUD → **Mollie-betaling** → PDF/export.
3. **HavunAdmin — opzetten** (hoogste risico). Betalingen **Mollie + Stripe** + reconciliation
   + facturen. Laravel-blauwdruk vanaf nul. Kritieke flows: registratie → betaling → factuur → reconciliatie.
4. **Infosyst — opzetten**. Login → PIN/QR-auth → artikelbeheer → zoek/chat.
5. **SafeHavun — opzetten**. Login → PIN/QR-auth → score-verificatie → dashboard.
6. **Agorano — opzetten tijdens Fase 1**. React-PWA → webapp-blauwdruk (API-mock). Login → feed → zoek.
7. **HavunClub — wacht** tot het project ontparkeerd wordt.

## Werkwijze per project (in de eigen project-sessie)

1. `@playwright/test` is een **nieuwe devDep** → Henk's go vereist (verboden-zonder-overleg).
2. Volg de blauwdruk die bij de stack past:
   - **Laravel + Blade** → `runbooks/playwright-e2e-laravel.md` (draaiende app + test-DB, géén API-mock).
   - **SPA/PWA (React)** → `runbooks/playwright-e2e-webapp.md` (API-mock, deterministisch, CI-licht).
3. **Alleen kritieke flows** (auth, betalingen, kerntransacties) — geen smoke-padding (§4 + §10).
4. **CI-job toevoegen** zodat de specs automatisch draaien (anders → JudoToernooi-situatie).
5. Update `critical-paths-{project}.md` met de nieuwe E2E-referenties.

## Werkwijze-haakjes (gedicht 2026-06-20)

- `/test` (template + per project) heeft nu een **stap 3 Frontend E2E** die §10 afdwingt en bij
  een ontbrekende suite het gat meldt.
- `/start` kwaliteitsnormen noemt nu expliciet: UI in project → E2E verplicht (§10).

## Zie ook

- `test-quality-policy.md` §10 — bindend beleid (bron).
- `runbooks/playwright-e2e-webapp.md` — SPA/PWA-blauwdruk.
- `runbooks/playwright-e2e-laravel.md` — Laravel+Blade-blauwdruk.
