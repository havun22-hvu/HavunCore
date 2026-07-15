---
title: "Plan: aangemeld blijven op het eigen toestel (HavunClub, SafeHavun, webapp, Studieplanner)"
type: plan
scope: alle-projecten
last_updated: 2026-07-14
---

# Plan: "goedkeuring bewaren op de smartphone" — blijvend ingelogd per toestel

> Opdracht Henk 14 jul 2026 (avond): op één apparaat niet telkens inloggen — webapp,
> HavunClub-judoka's, Studieplanner, SafeHavun. Onderzoek (3 agents) afgerond.

## Diagnose per app (feiten)

| App | Waarom lig je eruit | Kern-fix |
|-----|---------------------|----------|
| **HavunClub** | Sessie = 120 min; `remember_token`-infra bestaat, maar **geen enkel wachtwoordloos pad** (magic link, WebAuthn, QR, reset-/onboarding-autologin) geeft `remember=true`; checkbox op wachtwoordpad default uit | `remember=true` op alle wachtwoordloze logins (Laravel-remember-cookie ≈ 5 jaar) |
| **SafeHavun** | Zelfde patroon: sessie 120 min; PIN-, QR- en WebAuthn-login zetten geen remember (alleen wachtwoord-pad, met checkbox default áán) | `remember=true` op PIN/QR/WebAuthn-paden |
| **havuncore-webapp** | JWT is al 365 dagen geldig, máár validatie eist óók een rij in de `sessions`-tabel — verdwijnt die (DB-reset), dan 401 op alle toestellen; geen herstel-pad | Self-healing: geldige JWT + ontbrekende sessie-rij → rij heraanmaken i.p.v. `SESSION_EXPIRED` |
| **Studieplanner** | Sanctum-token verloopt nooit, máár (a) élke 401/403 op élk endpoint wist token+user onherstelbaar uit de secure store; (b) 3-device-FIFO kan jouw toestel stil deregistreren → 403 bij app-start → zelfde wipe | 401/403-afhandeling verzachten + FIFO beschermt actieve devices; token nooit wissen bij transiënte fouten |

## Agendapunten

### 1. KB-standaard aanvullen
`patterns/havun-mobile-login.md` regel 9 (nieuw): **wachtwoordloze logins (bio/magic/QR/PIN)
zetten ALTIJD `remember=true`** — het toestel ís de tweede factor; opnieuw moeten inloggen
op het eigen toestel is een bug, geen feature. Wachtwoord-pad: checkbox default aan.

### 2. HavunClub (branch staging → staging-deploy)
- `remember=true`/`loginUsingId($id, true)` op: `MagicLinkController::login` (beide guards),
  `WebAuthnLoginController::login` (`$request->login(remember: true)`),
  `QrLoginController` (approve-pad), `WachtwoordResetController` autologin,
  `OnboardingController` autologin, `RegisterController`.
- Gezin- + beheer-login: "Onthoud mij"-checkbox default aangevinkt.
- Tests: per pad assert dat de remember-cookie (`remember_web_*`/`remember_gezin_*`) gezet
  wordt; bestaande suite groen.

### 3. SafeHavun (code klaar; deploy = Henks go)
- `Auth::login($user, true)` in `PinAuthController::loginWithPin`, `QrAuthController`,
  `WebAuthnLoginController` (+ registratie). Tests idem.

### 4. havuncore-webapp (code + E2E; deploy = Henks go)
- `verifyToken`: JWT geldig maar sessie-rij weg → rij opnieuw aanmaken (zelfde expiry als
  token) i.p.v. 401 `SESSION_EXPIRED`. DB-bestand (`backend/data/`) wordt door het
  deploy-recept al niet geraakt — geverifieerd.
- localStorage-eviction (iOS) buiten scope: Henks toestel is Android en bio-herlogin is
  nu 2 tikken; httpOnly-cookie-verbouwing alleen als dit in de praktijk blijft opspelen.

### 5. Studieplanner (API-kant direct; app-kant = code klaar, **nieuwe EAS-build nodig**)
- **App**: `forceLogout` alleen bij definitieve auth-fouten (401 met expliciete
  auth-code / 403 `device_not_registered` ná een geslaagde retry) — nooit token wissen op
  een transiënte 401; bij twijfel: ingelogd blijven en stil opnieuw proberen.
- **API**: device-FIFO verdringt alleen devices die >90 dagen niet gezien zijn en nooit
  het device van de meest recente login (last_seen-kolom bijhouden). `deviceLogin`
  NIET routeren (server vertrouwt client-bio blind — auth-bypass-risico; genoteerd).
- App-wijziging vereist een nieuwe native build/OTA-update — Henks moment.

## Buiten scope (bewust)
- Herdenkingsportaal/JudoToernooi/HavunAdmin: zelfde remember-patroon, maar geen klacht —
  meenemen bij eerstvolgend login-onderhoud per app (checklist in KB).
- Refresh-token-architectuur webapp; httpOnly-cookies; Studieplanner-biometrie.

## Security-afweging (voor Henks beeld)
Remember-cookie ≈ 5 jaar op het toestel. Op een gedeeld/gestolen toestel is de app dan
open; schermvergrendeling van de telefoon is de vangrail. Voor deze apps (ledenadministratie,
status-dashboard, studieplanning, eigen tooling) is dat een normale afweging — banken doen
het anders, wij hoeven dat niet.

## Status
- [ ] 1. KB-regel 9
- [ ] 2. HavunClub remember-paden + tests + staging
- [ ] 3. SafeHavun remember-paden + tests
- [ ] 4. webapp self-healing sessie + E2E
- [ ] 5. Studieplanner 401-verzachting (app) + FIFO-fix (API)
