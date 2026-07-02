---
title: "Contract: HavunClub ↔ JudoToernooi (judotournament.org) — weegkaart + portals"
type: reference
scope: havuncore
audience: JudoToernooi-team (judotournament.org)
last_check: 2026-07-03
status: SPEC / handoff — HavunClub-kant gebouwd (base-URL/ID/match); JT levert portal-vul-API + weegkaart-endpoint + frame-ancestors
---

> **Bron:** `HavunClub/docs/integratie-judotournament-spec.md` (identiek). Centrale handoff-kopie in de HavunCore KB.
> Zie ook `havunclub-koppelingen.md` (het bredere 3-apps-contract; dat noemde nog het foute domein `judotoernooi.nl`).

# HavunClub ↔ JudoToernooi (judotournament.org) — integratieplan

> **Doel van dit doc:** één gedeelde bron zodat de HavunClub- en JudoToernooi-teams samen kunnen
> werken. HavunClub = de aanroeper/pusher; JudoToernooi (judotournament.org) = de leverancier.
> Beide apps zijn van dezelfde eigenaar. Bestaande JT-implementatie: `JudoToernooi/laravel/docs/2-FEATURES/HAVUNCLUB-KOPPELING.md`.

## Doel in het kort (voor JT)
Een judoschool die HavunClub gebruikt wil zijn judoka's **niet dubbel intypen** op judotournament.org. De
bedoeling:
1. **HavunClub vult de judoka-lijst op JT** (push vanuit HCl), met de **HavunClub-judoka-ID** erbij, zodat JT
   die judoka's kan herkennen.
2. Bij een toernooi van een **andere** organisator die de (optionele) **judoschool-portals** gebruikt, vult de
   HCl-school zijn **portal** via de **portal-link + pincode** die de organisator stuurde.
3. Omdat JT de HavunClub-ID kent, kan de **weegkaart** die JT maakt **binnen HavunClub** getoond worden (als
   ingebedde page), niet alleen via de losse QR.

**Wat JT hiervoor moet doen:** een base-URL bevestigen, een **portal-vul-API** (met pincode-autorisatie) leveren,
een **weegkaart-lookup-endpoint** (per judoka → weegkaart-URL) toevoegen, en de weegkaart-page **iframe-baar**
maken vanaf HavunClub. Details onderaan ("Wat JudoToernooi nog moet leveren").

## Context
Een judoschool gebruikt drie losse SaaS-apps: **HavunClub** (ledenadministratie/hub), **JudoToernooi**
(judotournament.org — toernooien én interne puntencompetitie) en **HavunAdmin** (boekhouding). HavunClub
is de bron van de judoka-stamdata van een school. JT organiseert toernooien en maakt weegkaarten.

## Kernflow (besluit 02-07)
1. **HavunClub pusht de judolijst** van een school naar JT (stamdata + de **HavunClub-judoka-ID**). JT
   bewaart die ID als `stam_judokas.havunclub_ref` → **JT kent de HavunClub-ID** van elke judoka.
2. **Inschrijven voor een toernooi** gebeurt op de **JT-judoschool-page** van die school binnen dat toernooi:
   `https://judotournament.org/{organisator}/{toernooi}/school/{school-code}/judokas`. HavunClub pusht de
   judoka's daarheen en/of deep-linkt naar die page.
3. **Weegkaart** wordt door JT gemaakt (QR-verspreiding: publieke route `judotournament.org/weegkaart/{token}`).
   Omdat JT de HavunClub-ID kent, kan HavunClub de weegkaart **binnen de app** tonen (iframe) — **alleen voor
   judoka's die via HavunClub zijn ingeschreven**. Niet via HavunClub ingeschreven → judoka gebruikt de **QR
   buiten de app** (geen onzekere koppeling).
4. **Deterministische ID-koppeling, GEEN fuzzy matching.** De vroegere `POST /judokas/match` (matchen op
   naam+geboortejaar+band) vervalt — te onzeker. De ID wordt bij de push/inschrijving vastgelegd.

## HCl en JT staan volledig los — koppeling per uitnodiging
HavunClub en JudoToernooi zijn **onafhankelijke, wereldwijde tools**: JT kan door elke organisator gebruikt
worden voor een toernooi; HCl door elke judoschool voor de ledenadministratie. Er is **geen vaste 1-op-1
koppeling**. Twee scenario's waarin ze elkaar raken:

**Scenario 1 — eigen toernooi / puntencompetitie.** De judoschool ís zelf de organisator op JT (bv. een interne
puntencompetitie). Dan geldt het bestaande **`ClubApiToken`**-model (token = Organisator, zie `HAVUNCLUB-KOPPELING.md`):
HavunClub synct de eigen judoka's naar de eigen JT.

**Scenario 2 — uitgenodigd bij andermans toernooi (judoschool-portals).** Een organisator (school A) organiseert
een toernooi op JT en gebruikt de **judoschool-portals** (een **optionele** JT-feature). Dan:
- A nodigt elke deelnemende judoschool uit → JT stuurt per school een **portal-link + pincode**.
- Een uitgenodigde judoschool B die HavunClub gebruikt, vult zijn **JT-portal vanuit HavunClub** met behulp van
  die **link + pincode** (in te voeren in HCl `/koppelingen` per toernooi). HCl pusht dan B's judoka's naar B's
  portal in A's toernooi.
- **De portal-link + pincode zijn de autorisatie** voor die push — niet het globale ClubApiToken. B kan alleen
  zijn eigen portal vullen.

**Open JT-detail (te bevestigen):** de **API-vorm** waarmee HCl een portal vult met link + pincode — welk endpoint,
hoe de pincode meegestuurd/geverifieerd wordt, en welke velden (incl. `havunclub_judoka_id` + gewicht). De
judoschool-portals zijn optioneel: gebruikt de organisator ze niet, dan is er geen HCl-push mogelijk voor dat toernooi.

## Bestaande JT-endpoints (✅ werken, na base-URL-fix aan HavunClub-kant)
Auth: `Authorization: Bearer <ClubApiToken>` (token = tenant/Organisator; géén `tenant`-parameter). Base-URL: `https://judotournament.org/api`.

| Doel | Methode + pad | HavunClub stuurt | Response |
|---|---|---|---|
| Judoka-sync (upsert) | `POST /api/judokas` | `havunclub_judoka_id`, `voornaam`, `achternaam`, `geboortedatum`, `geslacht`, `band` | `{ "id": <stam-judoka-id> }` |
| Inschrijven | `POST /api/inschrijvingen` | `toernooi_id`, `judoka_id` (stam-id), `naam?`, `band?`, `gewicht?` | `{ "id": <judoka-id> }` |
| Resultaten | `GET /api/toernooien/{toernooi}/resultaten` | — | `[ { judoka_id, stam_judoka_id, naam, gewichtsklasse, resultaat, partijen } ]` |

JT mapt server-side (`naam`, `geboortejaar`, `geslacht → M/V`). Idempotentie via (`organisator`, `havunclub_judoka_id`→`havunclub_ref`). `resultaat` = eindpositie (1=goud …).

## Wat JudoToernooi nog moet leveren (handoff)
1. **Base-URL bevestigen:** `https://judotournament.org/api`.
2. **Portal-vul-endpoint (judoschool-portals):** de API waarmee HavunClub een uitgenodigde portal vult met
   **portal-link + pincode** als autorisatie (i.p.v. het ClubApiToken). Specificeer: het endpoint/pad (uit de
   portal-link af te leiden?), hoe de **pincode** wordt meegestuurd/geverifieerd, en de velden per judoka
   (incl. `havunclub_judoka_id`, geboortedatum, band, gewicht). B mag alleen de eigen portal muteren.
3. **Weegkaart-lookup-endpoint (nieuw):** `GET /api/toernooien/{toernooi}/weegkaart/{judoka}` (op stam-id /
   `havunclub_ref`) → `{ "token": "<uuid>", "url": "https://judotournament.org/weegkaart/<uuid>" }`, of `404`
   als nog niet aangemaakt.
4. **Iframe-toestemming (config):** op de weegkaart-route
   `Content-Security-Policy: frame-ancestors https://havunclub.havun.nl` (i.p.v. `X-Frame-Options: DENY`),
   zodat HavunClub de weegkaart-page kan inbedden.
5. **`gewicht`** accepteren bij `POST /api/inschrijvingen` (voor de weging).

## Wat HavunClub gaat bouwen (eigen scope, aparte MPC-taak)
- Base-URL `judotoernooi.nl` → `judotournament.org` in `JudoToernooiService`.
- `havunclub_judoka_id` (= `judokas.id`) meesturen bij sync + inschrijven.
- `koppelJudoka`/`kiesKandidaat` (fuzzy `/judokas/match`) **verwijderen**.
- **Weegkaart-page** met `<iframe>` naar `judotournament.org/weegkaart/{token}` + CSP `frame-src judotournament.org`; `haalWeegkaart` afstemmen op het nieuwe JT-endpoint.
- `/koppelingen`-beheer: scenario 1 = eigen JT-token; scenario 2 = per toernooi een **portal-link + pincode** invoeren (uit de uitnodiging van de organisator) waarmee HavunClub de portal vult.

## Nog te bevestigen (business/JT)
- **Portal-vul-API** (scenario 2): endpoint + hoe de **pincode** als autorisatie werkt (punt 2). De autorisatie
  zelf is duidelijk (portal-link + pincode per uitnodiging); alleen de exacte API-vorm ontbreekt nog.
- Verhouding "push via API" (portal vullen) vs "inschrijven op de JT-portal-page" — vult HCl alleen de lijst en
  gebeurt het definitieve inschrijven op de JT-portal, of pusht HCl de inschrijving compleet?
- Judoschool-portals zijn **optioneel** in JT — zonder portal geen HCl-push voor dat toernooi (dan QR-weegkaart buiten de app).
