---
title: Quality sprint cross-project (24-04-2026)
type: runbook
scope: alle-projecten
last_check: 2026-04-24
status: LIVE — partial
---

# Quality sprint — 24-04-2026

> Eén autonoom-doorgewerkte nachtbatch waarin alle externe testsite-eisen
> (SSL Labs, SecurityHeaders, Mozilla Observatory) cross-project zijn
> aangepakt voor zover mogelijk zonder browser-test.

## Wat is live na deze sessie

### SSL Labs (alle 7 productie-domeinen)
Geen wijzigingen deze nacht — alle 7 domeinen al op A+ / 100/100/100/100
sinds 23-04-2026. Session-resumption fix (http-level cache) is permanent
in `nginx.conf`.

### SecurityHeaders (alle productie-sites)
Cookie-prefix + HSTS-preload + form-action overal aanwezig:

| Site | __Host- cookie | HSTS preload | CSP form-action | Comment |
|---|---|---|---|---|
| havuncore.havun.nl | n/a | ✅ | ✅ | API-only |
| havunadmin.havun.nl | ✅ | ✅ | ✅ | unsafe-eval blijft -10 (Alpine) |
| herdenkingsportaal.nl | ✅ | ✅ | ✅ | clean |
| judotournament.org | ✅ | ✅ | ✅ | unsafe-eval blijft -10 (Alpine) |
| infosyst.havun.nl | ✅ | ✅ | ✅ | clean |
| demo.havun.nl | n/a | ✅ | ✅ | (baseline snippet) |
| studieplanner.havun.nl | ✅ | ✅ | ✅ | clean |
| havun.nl | n/a | ✅ | ✅ | Next.js (baseline snippet) |

### Mozilla Observatory
Verwachte scores na deze sessie (handmatige rescan vereist per
`feedback_3_testpages_manual.md`):

| Site | Verwacht | Open punten |
|---|---|---|
| havuncore | A+ (100) | — |
| havunadmin | A (90) | unsafe-eval (Alpine CSP migration in progress) |
| herdenkingsportaal | A+ (100) | — |
| judotournament | A (90) | unsafe-eval (Alpine MPC sessie nog open) |
| infosyst | A+ (100) | — |
| demo | A+ (100) | — |
| studieplanner | A+ (100) | — |
| havun.nl | A+ (100) | — |

### Concrete fixes deze sessie

1. **JudoToernooi** — Pusher SRI op 9 plekken (7 static + 2 dynamic JS),
   Tailwind play-CDN vervangen door vite-bundle, Chromecast feature
   volledig verwijderd (-5 SRI penalty weg, geannuleerd zoals Henk
   bevestigde dat QR-code TV-koppeling de definitieve route is)
2. **HavunAdmin** — Alpine CSP migratie batch 1+2: alle 16 inline
   `x-data="{...}"` patterns naar named Alpine.data() components,
   alle inline `@click` expressies naar method refs. Backwards-
   compatible (vanilla Alpine still active). Resterend: 10 project-
   specifieke `function xyz()` declarations + final
   `@alpinejs/csp` switch (vereist browser-test sessie)

## Permanente eisen-uitbreiding

`docs/kb/reference/productie-deploy-eisen.md` blijft canonical. Geen
extra eisen toegevoegd deze sessie — alle aanpakken vallen binnen
bestaande eisen.

`templates/server-configs/`:
- `nginx-security-headers-baseline.conf` (uit eerdere sessie) blijft de
  template voor non-Laravel vhosts (Next.js, static, proxy)

## Open follow-ups

| Taak | Effort | Vereist |
|---|---|---|
| Alpine CSP final switch HavunAdmin | 1u + browser test | Browser sessie met Henk |
| Alpine CSP migratie JudoToernooi | Eigen MPC sessie (842 directives, 42 views) | Browser sessie + tijd |
| hstspreload.org submission | 5 min × 3 zones | Henk klikt UI (geen API) |
| Mozilla Observatory rescan alle 8 sites | 3 min × 8 | Henk klikt rescan in UI |

## Commit overzicht (deze sessie)

- HavunCore: SSL session resumption http-level (eerdere sessie),
  cookie __Host- policy doc, GA-removal policy, baseline-headers
  snippet, Umami runbook, Alpine-tracker reference
- HavunAdmin (`a2d8bde`): Alpine CSP migration batches 1, 2 + tracker
- JudoToernooi (`485c954a`, `1977ca01`, `2135f493`): Pusher SRI,
  Tailwind→vite, Chromecast removal
- Herdenkingsportaal (`a0a71b8`): GA removal + self-host infra cleanup
- Havun (`a5cf8ba`): Umami tracking script in Next.js layout
