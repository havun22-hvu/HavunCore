---
title: Security portfolio eindstand (24-04-2026)
type: runbook
scope: alle-projecten
last_check: 2026-04-24
status: SNAPSHOT
---

# Security portfolio eindstand — 24-04-2026

Snapshot na twee-daagse cross-portfolio sprint (23-24 april).

## Productie live state

| Site | CSP unsafe-eval | HSTS preload | __Host- cookie | Alpine CSP build |
|---|---|---|---|---|
| havuncore.havun.nl | ✅ clean | ✅ | n/a (API) | n/a |
| havunadmin.havun.nl | ✅ **clean (24-04)** | ✅ | ✅ | ✅ `@alpinejs/csp` |
| herdenkingsportaal.nl | ✅ clean | ✅ | ✅ | eigen CSP-build (fabric.js) |
| judotournament.org | ✅ **clean (24-04)** | ✅ | ✅ | ✅ `@alpinejs/csp` |
| infosyst.havun.nl | ✅ clean | ✅ | ✅ | n/a |
| demo.havun.nl | ✅ clean | ✅ | n/a (staging) | n/a |
| studieplanner.havun.nl | ✅ clean | ✅ | ✅ | n/a |
| havun.nl | ✅ clean | ✅ | n/a (Next.js) | n/a |
| umami.havun.nl | ⚠️ Next.js intern | ✅ | n/a (eigen) | n/a |

**Alle publieke Havun productie-sites zijn CSP unsafe-eval clean.**
Umami is een intern dashboard waarvan Next.js runtime eval nodig heeft
— bewust geaccepteerd.

## Verwachte Mozilla Observatory scores

| Site | Pre-sprint | Verwacht na sprint |
|---|---|---|
| havuncore.havun.nl | A+ | A+ |
| havunadmin.havun.nl | A (90) | **A+ (100)** ← |
| herdenkingsportaal.nl | A+ | A+ |
| judotournament.org | A (85-90) | **A+ (100)** ← |
| infosyst.havun.nl | A+ | A+ |
| demo.havun.nl | A+ | A+ |
| studieplanner.havun.nl | A+ | A+ |
| havun.nl | A+ | A+ |

## SSL Labs

Alle 7 productie-domeinen op **A+ / 100 / 100 / 100 / 100** sinds
23-04-2026 (ECDSA P-384, secp384r1, TLS 1.2/1.3 AEAD 256-bit, session
resumption via http-level cache). Zie `ssl-100-100-2026-04-23.md` +
`ssl-session-resumption-http-level-2026-04-23.md`.

## Dependency security (24-04-2026)

| Project | Composer audit | Npm audit (prod) |
|---|---|---|
| HavunCore | ✅ 0 CVE | — |
| HavunAdmin | ✅ 0 CVE | ✅ 0 |
| Herdenkingsportaal | ✅ 0 CVE | ✅ 0 |
| JudoToernooi | ✅ 0 CVE | ✅ 0 |
| Infosyst | ✅ 0 CVE | — |

## Niet-Alpine beveiligings-wins in sprint

- **SSL session resumption** — http-level cache (1 bron van waarheid i.p.v. per-server)
- **Session cookie `__Host-` prefix** cross-project (6 Laravel apps)
- **Google Analytics verwijderd** uit JudoToernooi + Herdenkingsportaal (AVG + SRI penalty)
- **Umami self-host** live op `umami.havun.nl` (cookieless, eigen data)
- **mijn.host DNS API** werkend vanaf server-IP (whitelisted)
- **DNS CAA records** op alle 3 zones met iodef
- **HSTS preload directive** cross-project
- **Baseline nginx security-headers snippet** voor non-Laravel vhosts
- **Pusher SRI** op alle 9 locaties (JudoToernooi)
- **Tailwind play-CDN → vite-build** (JudoToernooi)
- **Chromecast feature verwijderd** (JudoToernooi, QR-pairing gekozen)
- **Cross-project SecurityHeadersTest regression-set** in alle 4 Laravel-projecten

## Alpine CSP migratie klaar

- **HavunAdmin** (24-04): `@alpinejs/csp` actief, 20 shared Alpine.data components
- **JudoToernooi** (24-04 merge): VP-18 branch gemerged in main, `@alpinejs/csp` actief

Beide projecten: geen `unsafe-eval` meer in productie CSP.

## Open items (niet kritiek)

- **hstspreload.org submission** voor 3 root zones (havun.nl,
  herdenkingsportaal.nl, judotournament.org) — Henk moet UI klikken,
  geen API beschikbaar
- **Mozilla Observatory handmatig rescan** op 8 domeinen ter bevestiging
  van verwachte A+ scores (per `feedback_3_testpages_manual.md`)
- **Umami wachtwoord** — Henk moet eerste login doen + eigen wachtwoord zetten

## Rollback-tags

Voor veiligheid behouden:
- `backup/pre-vp18-merge` op JudoToernooi
- `backup/pre-invoiceprocessor-reconciliation` op HavunAdmin

## Gerelateerde runbooks

- `quality-sprint-2026-04-24.md` — sprint-overzicht
- `vp18-alpine-csp-merge-2026-04-24.md` — VP-18 merge details
- `ssl-session-resumption-http-level-2026-04-23.md` — SSL fix
- `cookie-host-prefix-rollout-2026-04-23.md` — cookie rollout
- `google-analytics-removal-2026-04-23.md` — GA removal
- `umami-analytics-setup-2026-04-23.md` — Umami install
- `productie-deploy-eisen.md` (reference) — canonical requirements
