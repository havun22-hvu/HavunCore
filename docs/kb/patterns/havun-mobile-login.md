---
title: "Pattern: Havun mobile login — uniforme smartphone-PWA-login (magic link + biometrie)"
type: pattern
scope: alle-projecten
last_check: 2026-07-14
---

# Havun mobile login — uniforme login-beslislogica

> **BINDEND** voor elke Havun-app met een web/PWA-login (richting Henk 14 jul 2026:
> *zoveel mogelijk magic link + biometrie*). Vervangt per-app heuristieken.
> Gerelateerd: `universal-login-screen.md` (layout), `authentication-methods.md`
> (methode-matrix), `webauthn-passkey-laravel.md` (server-kant).

## De twee hoofdmethodes

**Magic link** (e-mail = identiteit) en **biometrie** (passkey op bekend toestel).
Wachtwoord = opt-in-bijzaak (ingeklapt onderaan, alleen als de gebruiker er zelf een
instelde). QR = alleen desktop-alternatief (inloggen via al-ingelogde telefoon).

## Beslisregels (identiek in elke app)

| # | Regel |
|---|-------|
| 1 | **Device-detectie**: smartphone = `matchMedia('(pointer: coarse)')` **én** (UA-mobiel **óf** touch). GEEN schermbreedte-drempels (`innerWidth`/`screen.width < 768` is de iPad/foldable/desktopmodus-bug). |
| 2 | **Smartphone toont NOOIT QR** — geen knop, geen pagina, óók niet als bio-detectie faalt. Fallback op mobiel = magic link. |
| 3 | **Smartphone-volgorde**: [🔒 bio-knop, alleen na geslaagde detectie] → [✉️ e-mailveld + "Stuur inloglink" — altijd zichtbaar en prominent] → [wachtwoord ingeklapt]. Geen tabs. |
| 4 | **Desktop-volgorde**: [✉️ magic link primair] → [QR-alternatief] → [wachtwoord ingeklapt]. Geen bio-knop op desktop (beslissing apr 2026: wisselvallige UX). |
| 5 | **Bio-detectie met timeout** (2s): knop verschijnt alleen na een geslaagde, tijdige detectie — nooit "later binnenploffen", nooit een lege login. |
| 6 | **Bio-ceremonie faalt** → duidelijke NL-melding + magic-link-knop direct eronder. Serverfouten leesbaar maken (nooit `[object Response]`). |
| 7 | **Bio-groei**: na elke magic-link-login op een toestel mét platform-authenticator actief voorstellen om biometrie te koppelen ("Volgende keer met je vingerafdruk?"). |
| 8 | **rp.id/origins expliciet in server-`.env`** (`WEBAUTHN_ID`, `WEBAUTHN_ORIGINS`) — nooit op host-fallback vertrouwen. Wijzigt de rp.id → bestaande passkeys zijn dood en moeten opnieuw geregistreerd (eenmalig, in UI melden). |

## Referentie-JS (kopieer per app; geen dependency)

```js
// Havun mobile-login beslislogica — zie HavunCore kb/patterns/havun-mobile-login.md
function isSmartphone() {
    const coarse = window.matchMedia('(pointer: coarse)').matches;
    const uaMobiel = /Mobi|Android|iPhone|iPod/i.test(navigator.userAgent)
        // iPadOS doet zich voor als Macintosh maar heeft touch:
        || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);
    const touch = navigator.maxTouchPoints > 0 || 'ontouchstart' in window;
    return coarse && (uaMobiel || touch);
}

async function platformBioBeschikbaar(timeoutMs = 2000) {
    if (!window.isSecureContext) return false;
    if (!window.PublicKeyCredential?.isUserVerifyingPlatformAuthenticatorAvailable) return false;
    try {
        return await Promise.race([
            PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable(),
            new Promise((r) => setTimeout(() => r(false), timeoutMs)),
        ]);
    } catch {
        return false;
    }
}

// Gebruik:
//   const mobiel = isSmartphone();
//   qrElementen.forEach(el => el.hidden = mobiel);                  // regel 2/4
//   bioKnop.hidden = !(mobiel && await platformBioBeschikbaar());   // regel 3/5
```

## Checklist per app (invoeringsstatus 14 jul 2026)

| App | Detectie | QR-op-mobiel | Bio-bug | Status (14 jul 2026) |
|-----|----------|--------------|---------|----------------------|
| HavunClub | ✅ recept | ✅ gefixt | ✅ WEBAUTHN-env gezet (server); oude passkeys opnieuw registreren | staging live, prod na Henks test |
| havuncore-webapp | ✅ recept | ✅ gefixt | ✅ `available`-check gefixt | code klaar, deploy = Henks go |
| JudoToernooi | ✅ recept | ✅ gefixt | ✅ timeout + magic-link-fallback | staging live, prod na Henks test |
| Herdenkingsportaal | CSS `md:hidden` (ok) | n.v.t. | ✅ `allowCredentials` geïnjecteerd | code klaar, deploy = Henks go |
| Studieplanner | n.v.t. (native) | n.v.t. | bio nooit gebouwd | apart traject |

Plan: `docs/kb/plans/pwa-login-uniform-plan.md`.
