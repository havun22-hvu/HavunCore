---
title: Testgereedschap per stack — de norm is één, het gereedschap verschilt
type: reference
scope: alle-projecten
last_check: 2026-07-31
---

# Testgereedschap per stack

**De norm staat in [`test-quality-policy.md`](test-quality-policy.md) en verandert hier niet.**
Zinvolheid boven percentage, kritieke paden volledig gedekt, mutation-score als échte maat,
VP-17 (nooit een assertion omdraaien). Wat per stack verschilt is alléén het *gereedschap*.

Dit doc bestaat omdat de policy stilzwijgend PHP/Laravel veronderstelde. Zolang alles Laravel
was viel dat niet op; bij de eerste Rust-app stond er geen norm die iemand kon volgen — precies
zoals `qv:scan` daar een lege nul rapporteerde (`plans/vk-per-stack-plan.md`).

## Per ecosysteem

| | **PHP / Laravel** | **JS/TS** | **Rust** | **React Native** |
|---|---|---|---|---|
| Tests draaien | `php artisan test --no-coverage` | `npm test` (Vitest) | `cargo test` | `npm test` (Jest) |
| Coverage | pcov + PHPUnit | Vitest `--coverage` (v8) | `cargo llvm-cov` | Jest `--coverage` |
| **Mutation** | **Infection** (`infection.json5`) | **Stryker** | **`cargo-mutants`** | Stryker |
| Lint / statisch | Pint, PHPStan | ESLint, `tsc --noEmit` | `cargo clippy -- -D warnings` | ESLint, `tsc` |
| Dependency-audit | `composer audit` | `npm audit --omit=dev` | `cargo audit` | `npm audit` |
| E2E | Playwright (`runbooks/playwright-e2e-laravel.md`) | Playwright (`…-webapp.md`) | zie hieronder | Maestro / device-sweep |
| In `qv:scan` | ✅ | ✅ | ✅ (sinds 31-07) | ✅ (npm) |

**Go, Python en .NET staan hier bewust niet in.** Er draait geen audit voor, en `qv:scan` meldt
dat als `high`-bevinding in plaats van te zwijgen. Komt er zo'n project, dan hoort de regel hier
eerst bij te staan.

## Rust — wat er anders is

Drie dingen wijken genoeg af om ze expliciet te maken:

1. **Tests staan náást de code**, in `#[cfg(test)] mod tests` binnen hetzelfde bestand, plus
   `tests/` voor integratietests. De `test-erosion`-check van `qv:scan` kijkt naar `tests/` en
   ziet unit-tests in `src/` dus **niet** — een verwijderde `#[cfg(test)]`-module valt daar
   buiten beeld. Weet dat, en leun er niet op als enige vangnet.
2. **`cargo clippy -- -D warnings` is geen luxe maar de goedkoopste kritieke-padbewaking** die
   deze stack heeft: het vangt klassen fouten die je in PHP met een test zou moeten afdekken.
3. **`cargo audit` meldt in twee velden.** `vulnerabilities` én `warnings` (`unmaintained`,
   `unsound`). Alleen het eerste lezen gaf op de Tauri-crate 0 terwijl er 17 in stonden.
   Geldt net zo goed als je het handmatig draait.

**E2E op een desktop-app:** Playwright kan hier niet bij. Voor Tauri is de bruikbare vorm een
integratietest die de commands aanroept zoals de UI dat doet, plus een handmatige gate op een
schone machine vóór release. Automatiseer wat kan, en **noteer eerlijk wat handmatig blijft** —
een device-sweep die niet te automatiseren is, is een gate, geen gat (policy §11.4).

## Wat níét per stack verschilt

- **Rood gezien, of het telt niet.** Een bugfix-test die je niet hebt zien falen tegen de oude
  code, bewijst niets — in elke taal. `patterns/test-rood-gezien.md`.
- **Kritieke paden eerst.** Geld, datacorruptie, verkeerde toegang, veiligheid. Voor een
  desktop-app zonder auth verschuift dat naar **wat de app naar buiten schrijft**: bestanden
  overschrijven, metadata het bestand in, een update installeren.
- **Geen coverage-padding.** Een test die alleen bestaat om een cijfer te liften, gaat weg —
  ongeacht wat de meter zegt.
- **Dependency-audit faalt de build.** In elke CI, in elke taal.

## Zie ook

- [`test-quality-policy.md`](test-quality-policy.md) — de norm zelf (autoritatief)
- [`../plans/vk-per-stack-plan.md`](../plans/vk-per-stack-plan.md) — hoe `qv:scan` de stack bepaalt
- [`../standards/stack-keuze.md`](../standards/stack-keuze.md) — welk fundament bij welk werktype
