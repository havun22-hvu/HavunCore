---
title: Security-review JudoToernooi ↔ JudoScoreBoard API (2026-07-15)
type: reference
scope: judotoernooi
last_check: 2026-07-15
---

# Security-review JudoToernooi ↔ JudoScoreBoard

**Aanleiding:** mogen externe testers een scorebord-code krijgen?
**Antwoord:** ja, na de fixes hieronder. Voor een **onbekende** partij: alleen op een eigen
staging-instance met eigen DB — nooit een code op de productie-DB. Voor een bekende tester volstaat
**Reset** achteraf (trekt token in + vervangt de code).

Alles hieronder is gefixt (JT `f3445e46` + `34bd9549`).

## Bevindingen

| # | Wat | Sev | Status |
|---|-----|-----|--------|
| 1 | `/result` scoopte niet op het toernooi van het token → elk token kon uitslagen zetten op élk toernooi | 🔴 | ✅ 404, fail closed |
| 2 | `/event` broadcastte het hele `DeviceToegang`-record (incl. `api_token`) op een **publiek** kanaal | 🔴 | ✅ `attributes` i.p.v. `merge()` + `$hidden` |
| 3 | "Reset" nulde `api_token` niet → gereset device schreef gewoon door | 🔴 | ✅ reset + resetAll revoken nu echt |
| 4 | Geen rate limit op beschermde routes | 🟡 | ✅ 120/min **per token** |
| 5 | CORS wildcard op `/api/*` | 🔵 | ✅ beperkt tot `app.url` |
| 6 | `result()` gaf 500 bij ontbrekende optionele `updated_at` | 🔵 | ✅ meegefixt |

**App-kant (JudoScoreBoard) kwam schoon uit** — niets gewijzigd: geen hardcoded secrets, token in
SecureStore, cleartext geblokkeerd, niets in de git-historie. Decompilatie levert alleen de
base-URL. Geen certificate pinning (bekend, geaccepteerd).

## Bewust geaccepteerd (Henk, 15 jul)

- **Publieke Reverb-kanalen** — *"publiek-openbaar: prima (als je de url weet)"*. Data =
  wedstrijdinfo die in de zaal op het scherm staat. Ná fix #2 lekt er geen token meer.
- **JSB schrijft scores** — by design; de jury corrigeert achteraf handmatig in de JT-webapp.
  Vastgelegd als `JudoScoreBoard/CONTRACTS.md` **C-11** (C-02 gold alleen de display-rol).

## Dreigingsmodel — waarom "wie is de tester" telt

Capability ≠ intent. Dat iedereen een lek *kan* misbruiken zegt niets over de kans dat het gebeurt.
De as die telt is niet herkomst maar: **heb je verhaal op deze partij, en verliest zij iets als ze
je schaadt?**

| | Bekende tester | Onbekende partij die zich aanbiedt |
|---|---|---|
| Risico | vergissing | doelgericht misbruik, stil meelezen |
| Verhaal | ja (relatie, vindbaar) | nee (geen afdwingbaar contract) |
| Detectie | meldt het zelf | merk je niet |

Snijd de maatregel op die as: dan dekt hij élke onbekende partij, ook de volgende die ergens anders
vandaan komt.

## Les voor andere Havun-projecten

`$request->merge()` om een geauthenticeerd model door te geven is een **anti-patroon**: het schrijft
in de input-bag, dus het model komt mee in `$request->all()` en lekt in elke echo/broadcast.
Gebruik `$request->attributes->set()`. Zet `$hidden` op elk model met een credential — vangnet,
geen vervanging.
