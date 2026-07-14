---
title: "Plan: PWA-logins fixen + uniforme smartphone-login (alle projecten)"
type: plan
scope: alle-projecten
last_updated: 2026-07-14
---

# Plan: PWA-logins — bio-fixes, QR-op-mobiel weg, uniforme smartphone-login

> Opdracht Henk 14 jul 2026: (a) biometrie/vingerscan-login lukt niet, (b) soms QR-code
> op de smartphone (hoort alleen op PC), (c) één duidelijke inlogpagina voor de
> smartphone-PWA, alle projecten. Onderzoek (3 agents + server-.env-check) afgerond.

## Diagnose per app (feiten)

| App | Bio faalt door | QR op mobiel door |
|-----|----------------|-------------------|
| **HavunClub** | `WEBAUTHN_ID`/`ORIGINS` ontbreken in prod+staging server-`.env` → rp.id-fallback → SecurityError; servefouten tonen als `[object Response]` | JS toont QR-knop zodra bio-detectie `false` is — géén device-check (app.js r.181-183) |
| **havuncore-webapp** | `GET /webauthn/available` wordt zónder username aangeroepen → backend zegt altijd `false` → bio-knop verschijnt nooit op echte toestellen (Login.jsx:79 ↔ auth.js:270) | iPad/desktop-modus/brede toestellen vallen door de UA-heuristiek in het desktop-pad → **QR is daar default** |
| **JudoToernooi** | Server-env ✓; bio-knop verschijnt ook zonder geregistreerde passkey → verwarrend falen; `allowCredentials` = álle organisators | `isSmartphone`-heuristiek (`min(screen)<768`+touch) faalt op grote telefoons/foldables/webviews → QR |
| **Herdenkingsportaal** | `loginOptions()` injecteert geen `allowCredentials` → alleen resident/discoverable passkeys werken (PasskeyController:47-61); server-env ✓ | n.v.t. — actieve loginpagina toont geen QR (CSS `md:hidden` werkt) |
| **Studieplanner** | Bio is **nooit gebouwd** (expo-local-authentication ongebruikt; `deviceLogin` niet gerouteerd). Native app, geen PWA | n.v.t. — geen QR |

## Uniform recept (nieuwe portfolio-standaard; richting Henk 14 jul: **zoveel mogelijk magic link + biometrie**)

Eén gedeelde beslislogica, in elke app identiek. Magic link + biometrie zijn de twee
hoofdmethodes; wachtwoord = opt-in-bijzaak, QR = alleen desktop-alternatief.

1. **Device-detectie robuust**: smartphone = `matchMedia('(pointer: coarse)')` **én**
   (UA-mobiel **óf** touch) — geen schermbreedte-drempels meer (iPad/foldable-bug).
2. **Smartphone**: [🔒 vingerafdruk/gezicht] (indien beschikbaar) →
   [✉️ e-mailveld + "Stuur inloglink"] altijd direct zichtbaar en prominent →
   wachtwoord alleen ingeklapt/onderaan (opt-in). **NOOIT een QR-knop of QR-pagina** —
   ook niet als bio-detectie faalt (dan is magic link dé weg).
3. **Desktop**: [✉️ magic link] primair + [QR scannen met telefoon] als alternatief +
   wachtwoord ingeklapt (opt-in). Geen bio-knop op desktop (bewuste standaard-keuze
   apr 2026: wisselvallige Windows Hello-UX).
4. **Bio-knop alleen na geslaagde feature-detectie mét 2s-timeout** (geen "knop ploft
   later binnen"); falen van de ceremonie → duidelijke NL-melding + magic-link-knop.
5. **Na elke magic-link-login op een toestel mét platform-authenticator: actief
   voorstellen om biometrie te koppelen** ("Volgende keer met je vingerafdruk?") —
   zo groeit bio-gebruik vanzelf. Geen tabs, e-mail = identiteit.

## Agendapunten

### 1. HavunCore KB — standaard vastleggen (docs-first)
`docs/kb/patterns/havun-mobile-login.md`: het recept hierboven + referentie-JS-snippet
(device-detect + bio-detect-met-timeout) + checklist per app. `universal-login-screen.md`
en `authentication-methods.md` verwijzen ernaar.

### 2. HavunClub (grootste klachtenbron; branch staging)
- **⚠️ Server-`.env` prod+staging**: `WEBAUTHN_ID`, `WEBAUTHN_ORIGINS`, `WEBAUTHN_NAME`
  zetten (`havunclub.havun.nl` resp. `staging.…`) + `.env.example` aanvullen. *(Env-wijziging —
  gedekt door dit plan na "ga maar".)* **Bestaande passkeys die onder de foute rp.id zijn
  geregistreerd blijven dood → gebruikers registreren opnieuw; melden in UI-tekst.*
- Login-JS + beide login-views op het uniforme recept: magic link prominent (nu een
  klein linkje), bio-knop bovenaan, wachtwoord ingeklapt, QR-knop alleen desktop;
  bio-detect met timeout; na magic-link-login bio-koppeling voorstellen.
- `[object Response]`-fouten leesbaar (webauthn.js-wrapper response→tekst).
- QR-flow: `scan()`-redirect naar de juiste guard-login (nu hard `gezin.login`).
- Tests (Feature + JS waar zinvol).

### 3. havuncore-webapp
- `available`-check fixen: laatst-ingelogde username meegeven (localStorage) en/of
  endpoint laten antwoorden op "zijn er discoverable credentials mogelijk" i.p.v. `false`
  zonder username.
- Device-detectie op het uniforme recept (iPad/desktopmodus → geen QR-default op handhelds).
- Hardcoded `?username=henkvu@gmail.com` in `QrApprove.jsx` weg (uit auth-state).
- Playwright E2E bijwerken (bestaande 12-tests-suite).

### 4. JudoToernooi (branch main; alleen login-blade + PasskeyController)
- `isSmartphone`-heuristiek → uniform recept (QR-knop alleen desktop).
- Bio-faal-pad: NL-melding + magic-link-knop als fallback (knop verschijnt zonder passkey).
- Login-tabs (Inloggen/Registreren) laten staan — herbouw naar email-first is een
  aparte, grotere klus (optioneel punt 6).

### 5. Herdenkingsportaal (kleinste ingreep)
- `allowCredentials` injecteren in `PasskeyController::loginOptions()` (JT-patroon) zodat
  ook niet-resident passkeys werken.
- Verder al conform (geen QR op mobiel, bio-knop `md:hidden` + detectie bij klik).

### 6. BUITEN SCOPE (bewust — apart traject / Henks keuze)
- **Studieplanner**: native app zonder bio — "vingerscan" vereist daar nieuwbouw
  (expo-local-authentication aansluiten + `deviceLogin` routeren). Apart project.
- **Email-first-herbouw** van HP/JT-loginpagina's (tabs weg, één e-mailveld): grotere
  UX-verbouwing; de standaard bestaat al, invoering per app op eigen moment.
- SafeHavun/HavunAdmin/Infosyst: geen PWA-smartphone-loginklachten bekend; checklist
  uit punt 1 volstaat daar later.

## Volgorde, risico, tests
Volgorde 1→2→3→4→5 (HavunClub eerst — daar zit de gebruikerspijn). Per agendapunt:
tests + /simplify + docs + atomic commit + push; staging-deploy waar beschikbaar.
**Praktische bio-test op echte toestellen = Henk** (per app na staging; passkeys onder
oude rp.id moeten opnieuw geregistreerd worden). **Prod-deploys = Henks go per app.**
Grootste risico: rp.id-wijziging HavunClub maakt eerder geregistreerde (kapotte)
passkeys definitief ongeldig — verwacht en eenmalig.

## Status — GEBOUWD 14 jul 2026 (avond)
- [x] 1. KB-standaard `havun-mobile-login.md` (HavunCore `70324a6`)
- [x] 2. HavunClub: WEBAUTHN-env op prod+staging server gezet, login-views + JS op recept, `[object Response]`-fix, bio-voorstel-banner na magic-login; 353 tests groen; **staging live**. QR-`scan()`-redirect naar `gezin.login` bewust gelaten (cross-links op beide loginpagina's dekken het).
- [x] 3. havuncore-webapp: `available`-endpoint antwoordt zonder username nu op "bestaan er passkeys" (bio-knop verscheen anders nooit), `last_username` gebruikt voor available/login-options/QrApprove (hardcoded adres weg), detectie op recept; 12 E2E groen (`d3bd3a0`). **Nog niet gedeployed** (webapp = build+rsync = prod; Henks go).
- [x] 4. JudoToernooi: detectie op recept + bio-timeout + foutteksten → magic link (`140045ab`); 264 auth-tests groen; **staging live**.
- [x] 5. Herdenkingsportaal: `allowCredentials`-injectie (JT-patroon, `e961edf`); 55 passkey-tests groen; **nog niet gedeployed** (prod = Henks go).

### Door Henk te testen (echte toestellen, staging waar beschikbaar)
1. HavunClub staging: login-pagina's (bio-knop op telefoon, GEEN QR op telefoon, magic-link-knop, wachtwoord-collapse), passkey opnieuw registreren (oude zijn dood door rp.id-fix), bio-voorstel-banner na magic-login.
2. JT staging: login op telefoon → geen QR-knop; desktop → wel.
3. Daarna per app prod-go: HavunClub (merge naar main + deploy), JT (deploy-knop), HP (deploy), webapp (build+rsync+pm2).
