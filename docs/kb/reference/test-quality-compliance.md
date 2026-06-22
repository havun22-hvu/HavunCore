---
title: Test-kwaliteit — naleving per project (compliance-matrix)
type: reference
scope: alle-projecten
status: BINDING
last_reviewed: 2026-06-23
governs: test-quality-policy.md
---

# Test-kwaliteit — naleving per project

> **Eén regel:** élk Havun-project moet aan het bindend beleid
> `test-quality-policy.md` voldoen — niet alleen de projecten waar het toevallig
> al is opgezet. Dit document is de **status + gaten + volgorde**; het dichten
> van een gat gebeurt in de **eigen project-sessie** (HavunCore-scope = dit
> overzicht bijhouden en bewaken, niet de tests van andere repos hier bouwen).

Dit is de master-naleving over de héle policy. Het Playwright-deel (§10) heeft
een eigen, gedetailleerder uitrolplan: `playwright-rollout-plan.md`. Deze matrix
omvat dat én de overige eisen.

## De eis-dimensies (uit `test-quality-policy.md`)

| # | Dimensie | Bron | Geldt voor |
|---|----------|------|-----------|
| **KP** | Kritieke paden benoemd + 100% gedekt (`critical-paths-{project}.md`) | §2-3 | elk project met kritieke paden |
| **MUT** | Mutation-score op kritieke paden (≥80, meestal 90+) + periodieke full-suite sweep | §7.1, §11.2 | elk project met een testsuite |
| **E2E** | Browser-E2E op kritieke flows (Playwright) | §10 | elk project met browser-UI |
| **RT** | Realtime/cross-device E2E met de échte broadcaster aan (≥2 contexts + reconnect) | §11.1 | projecten waar live cross-device de kern is |
| **VIS** | Visual regression (`toHaveScreenshot`) op pixel-fragiele schermen | §11.3 | projecten met fragiele layout-/canvas-schermen |
| **DEV** | Echt-device sweep (handmatig/BrowserStack) — gat in handover tot gedekt | §11.4 | projecten met mobiel-kritische UI |

**Legenda:** ✅ voldoet · 🟡 deels/in opbouw · ❌ ontbreekt (gat) · ❓ status vast
te stellen in project-sessie · n.v.t. dimensie niet van toepassing.

## Matrix

| Project | Stack / UI | KP | MUT | E2E | RT | VIS | DEV |
|---------|-----------|----|----|-----|----|----|----|
| **havuncore** | Laravel API (geen UI) | ✅ | ❓ | n.v.t. | n.v.t. | n.v.t. | n.v.t. |
| **havuncore-webapp** | React PWA | ❓ | ❓ | ✅ 12 tests + CI | n.v.t.¹ | ❌ | ❌ |
| **judotoernooi** | Laravel + Blade | ✅ | ❓ | 🟡 9 specs, CI uit, 4+ rood | ❌ **grootste gat** | ❌ bracket/LCD/scorebord | ❌ |
| **judoscoreboard** | React Native (Expo) | ❌ | ❓ | n.v.t.² | ❌ live scorebord-client | ❌ LCD/scorebord | ❌ |
| **herdenkingsportaal** | Laravel + Blade | ✅ | ❓ | 🟡 dep+config, 0 specs | n.v.t. | ❓ | ❌ |
| **havunadmin** | Laravel + Blade | ✅ | ❓ | ❌ (Mollie+Stripe!) | n.v.t. | ❓ | ❌ |
| **infosyst** | Laravel + Blade | ✅ | ❓ | ❌ | n.v.t. | ❓ | ❌ |
| **safehavun** | Laravel + Blade | ✅ | ❓ | ❌ | n.v.t. | ❓ | ❌ |
| **studieplanner** | React Native (Expo) | ✅³ | ❓ | n.v.t.² | n.v.t. | ❌ | ❌ |
| **aeterna** | Rust + Tauri desktop | ❓ | ❓ | n.v.t.⁴ | n.v.t. | ❓ | n.v.t. |
| **agorano** | React PWA | ❌ | ❓ | ❌ (Fase 1, scaffold staat) | n.v.t. | ❓ | ❌ |
| **havunclub** | Laravel + Blade | ❓ | ❓ | ❌ **geparkeerd** | n.v.t. | ❓ | ❌ |
| **munus / havunity** | geparkeerd / bestaat nog niet | — | — | — | — | — | — |

¹ webapp toont status realtime via Socket.io (health-alerts), maar dat is geen
cross-device kerntransactie — de E2E mockt het terecht. ² React-Native native →
geen browser-E2E; Jest unit/component is de laag (en evt. Detox/Maestro voor
native-E2E, buiten Playwright). ³ `critical-paths-studieplanner.md` +
`-mobile.md` bestaan. ⁴ Tauri desktop → geen web-E2E.

> **MUT-kolom staat overal op ❓:** de mutation-baseline (`mutation-baseline-2026-04-17.md`)
> is het startpunt, maar de actuele per-project mutation-score is niet centraal
> bijgehouden. Eerste actie per project-sessie: Infection/Stryker draaien en de
> score in het eigen `critical-paths`-doc noteren.

## Grootste gaten (prioriteit)

Prioriteit volgt §2-laag-1 (auth + betalingen = hoogste risico) en §11.1
(realtime kern van het product).

1. **JudoToernooi realtime (RT)** — *hoogste waarde, meeste werk.* De kern van
   het product (mat scoort → scorebord + LCD + spreker live; reconnect na
   Reverb-uitval) is nooit écht end-to-end getest. Nu draait E2E met
   `BROADCAST_CONNECTION=null` → broadcasts zijn no-op. Bouw §11.1: Reverb aan,
   2 browser-contexts, assert B verandert als A scoort + reconnect-pad.
2. **HavunAdmin E2E** — betalingen Mollie **+ Stripe** + reconciliatie + facturen,
   nog geen enkele E2E. Hoogste financieel risico.
3. **JudoToernooi specs groen + CI** — 4+ falende specs eerst fixen (Windows-pad
   vs echte CSP-drift uitzoeken), dán CI-job. Zie rollout-plan stap 1.
4. **Herdenkingsportaal specs** — dep+config staan; alleen flows schrijven
   (login → memorial CRUD → Mollie → PDF). Quick win.
5. **Visual regression (VIS)** — JudoToernooi bracket/scorebord/LCD eerst
   (herhaaldelijk visueel gebroken), desktop-only.
6. **Mutation-status (MUT) overal vaststellen** — goedkope sweep, hoog inzicht;
   per project één keer draaien en noteren.
7. **Device-sweep (DEV)** — per project een handmatige checklist vastleggen en
   het gat in de project-handover zetten tot gedekt.

## Hoe dit bewaakt wordt

- **Per project-`/start`:** check de eigen rij hier; een ❌/❓ op een van
  toepassing zijnde dimensie is een **gat dat gemeld moet worden** (niet stil
  laten staan). `/start` kwaliteitsnorm + `/test` stap 3 dwingen §10 al af; §11
  volgt dezelfde logica.
- **Deze matrix is de single source of truth** voor policy-naleving per project.
  Bij elke nieuwe E2E/mutation/visual-suite: werk de cel hier **én** het
  `critical-paths-{project}.md` bij.
- **Nieuw project** (scaffold via `project:scaffold`) → krijgt direct een rij
  hier met alle van-toepassing-zijnde dimensies op ❌, zodat het gat zichtbaar
  is vanaf dag 1.

## Zie ook

- `test-quality-policy.md` — het bindend beleid (bron van alle dimensies).
- `playwright-rollout-plan.md` — gedetailleerd §10/E2E-uitrolplan + volgorde.
- `mutation-baseline-2026-04-17.md` — startpunt mutation testing.
- `critical-paths-{project}.md` — per-project kritieke paden + testreferenties.
