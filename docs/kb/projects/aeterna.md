---
title: Aeterna — Project Overview
type: project
scope: aeterna
last_check: 2026-05-03
---

# Aeterna — Project Overview

> Soeverein desktop/mobile instrument voor het versturen van ADA op Cardano. Censuur-resistant by design.

## Status (2026-05-03)

Brainstorm-fase compleet. Volledig plan in `D:\GitHub\Aeterna\PLAN.md` v3. Project-skeleton (CLAUDE.md, CONTRACTS.md, GOVERNANCE.md, .claude/) compleet. Nog geen code geschreven. GitHub-repo publiek live: https://github.com/havun22-hvu/Aeterna

## Verhouding tot HavunCore

Aeterna staat **technisch los** van HavunCore-infra:
- Geen Hetzner-server, geen Laravel, geen MySQL, geen nginx-vhost
- Geen qv:scan-registratie in `config/quality-safety.php`
- Geen AutoFix
- Geen productie-deploy-eisen (SSL Labs, Mozilla Observatory, etc.) — dat slaat op websites, Aeterna is een desktop/mobile-app
- Geen koppeling met andere Havun-projecten of Herdenkingsportaal-data

Aeterna staat **filosofisch ingebed** in de HavunCore-werkwijze:
- MPC-model verplicht (MD → Plan → Code)
- Docs-first
- KB-zoekplicht via HavunCore (`docs:search`)
- Standards-blok in CLAUDE.md (wallet-variant van canonical template)
- Test-quality-policy (durable + zinvol > coverage-padding)
- Test-repair anti-pattern bewaakt
- Geen feature-creep buiten PLAN.md

Cross-project patterns die uit Aeterna ontstaan, komen NIET in Aeterna's eigen docs maar in HavunCore `docs/kb/patterns/`:
- Browser-only-blockchain-app pattern (uitgebreid: native-app-zonder-server pattern)
- Hardware-wallet-abstraction-layer pattern (async trait-based plugin-architectuur)
- Air-gapped-QR-signing pattern (BC-UR uniform resources)
- Reproducible-build-pipeline voor Tauri+Rust+Android pattern
- Multi-mirror distribution pattern (GitHub + F-Droid + Arweave + IPFS)
- On-chain update-pointer pattern met confirmation-threshold

## Architectuur — kort

- **Framework:** Tauri 2.0 cross-platform (Android primair, Windows secundair, één codebase)
- **Frontend:** React + TypeScript in system-WebView per OS, responsive
- **Backend:** Rust, async-first (tokio)
- **Cardano P2P:** pallas crate voor write-pad (tx-broadcast)
- **Cardano API-fallback:** multi-Koios voor read-pad (saldo + history) met failover
- **Wallet:** async `WalletBackend` trait — hot wallet (Argon2id + AES-256-GCM) + Ledger USB-OTG + watch-only in v1
- **Air-gap:** BC-UR multi-frame QR (Keystone/NGRAVE) vanaf v1.3 maar abstractie vanaf dag 1
- **Distributie:** eigen F-Droid repo + GitHub Releases + Arweave + IPFS + ZIP
- **Updates:** on-chain pointer-bericht vanaf hardcoded officieel adres met 10-block-confirmation threshold; nooit auto-install

## Onveranderlijke contracten (CONTRACTS.md, 12 stuks)

- C-01: Geen seed of private key verlaat ooit het toestel
- C-02: Geen single point of failure dat eenzijdig censureerbaar is
- C-03: Geen Google Play of Apple App Store distributie
- C-04: Hardware-wallet abstractie verplicht — geen vendor lock-in
- C-05: Reproducible build voor elke release
- C-06: Updates worden nooit automatisch geïnstalleerd
- C-07: Geen telemetry, geen analytics, geen 3rd-party tracking
- C-08: Bech32-validatie voor elk ontvangstadres
- C-09: FLAG_SECURE op alle schermen met seed of wachtwoord
- C-10: SecureRandom/OsRng voor seed-generatie, geen thread_rng
- C-11: Stake-key isolatie bij send-transacties
- C-12: Geen automatische updates, ooit

## Roadmap (PLAN.md sectie 15)

- v1: hot wallet + Ledger Nano X/S+ via USB-OTG + watch-only — 10-12 weken parttime + 2 buffer
- v1.1: Trezor (na hardware-aankoop)
- v1.2: BitBox02
- v1.3: Keystone air-gapped via BC-UR-QR
- v1.4: NGRAVE Zero
- v1.5: Tangem NFC (Android)
- v1.6: cardano-cli air-gap (geen hardware)
- v2: iOS, native token send, multisig, Mithril, opt-in tip-mechanisme

## Wanneer raadplegen

- Bij vragen over Aeterna-specifieke features of beslissingen
- Bij het overwegen van nieuwe cross-project patterns die uit Aeterna ontstaan
- Bij vragen over hoe HavunCore-werkwijze wordt toegepast op een non-Laravel-non-server-project

## Lokale paden

- Repo: `D:\GitHub\Aeterna\`
- Volledig plan: `D:\GitHub\Aeterna\PLAN.md`
- Onveranderlijke regels: `D:\GitHub\Aeterna\CONTRACTS.md`
- Decentralisatie-plan: `D:\GitHub\Aeterna\GOVERNANCE.md`
- Project-context: `D:\GitHub\Aeterna\.claude\context.md`

## GitHub

- Repo: https://github.com/havun22-hvu/Aeterna
- License: MIT
- Visibility: Public
- Eerste commit: 2026-05-03
