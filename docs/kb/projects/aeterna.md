---
title: Aeterna — Project Overview
type: project
scope: aeterna
last_check: 2026-05-16
---

# Aeterna — Project Overview

> Soeverein desktop/mobile instrument voor het versturen van ADA op Cardano. Censuur-resistant by design.

## Status (2026-05-16, sessie 30)

- v1.0.2 live op GitHub Releases — gesignede productie-APK (53 MB, arm64)
- 306 Rust-tests + 15 frontend-tests groen. 0 compiler warnings, 0 clippy, 0 critical audit
- PRs #3–#96 gemerged; alle PLAN.md-items §1–§38 klaar, geblokkeerd of gedocumenteerd
- Mirror-pipeline volledig: Arweave, IPFS, F-Droid (geautomatiseerd via GitHub Actions), GitHub Releases
- Productie-keystore in GitHub Secrets; officieel update-adres Preview actief
- Ledger probe command geïmplementeerd (PR #94); fysieke Nano X test pending
- GitHub-repo publiek: https://github.com/havun22-hvu/Aeterna

## Verhouding tot HavunCore

Aeterna staat **technisch los** van HavunCore-infra:
- Geen Hetzner-server, geen Laravel, geen MySQL, geen nginx-vhost
- Geen qv:scan-registratie, geen AutoFix, geen productie-deploy-eisen

Aeterna staat **filosofisch ingebed** in de HavunCore-werkwijze:
- MPC-model verplicht (MD → Plan → Code)
- Docs-first, KB-zoekplicht via HavunCore (`docs:search`)
- Standards-blok in CLAUDE.md (wallet-variant van canonical template)
- Test-quality-policy, test-repair anti-pattern bewaakt
- Geen feature-creep buiten PLAN.md

## Architectuur — kort

- **Framework:** Tauri 2.5 cross-platform (Android primair, Windows secundair, één codebase)
- **Frontend:** React 19 + TypeScript in system-WebView per OS
- **Backend:** Rust, async-first (tokio)
- **Cardano P2P:** pallas 0.30 voor write-pad (tx-broadcast via Ouroboros P2P)
- **Cardano API-fallback:** multi-Koios voor read-pad (saldo + history + UTXOs) met failover
- **Wallet:** async `WalletBackend` trait — hot wallet (Argon2id + AES-256-GCM) + Ledger USB-OTG + watch-only in v1
- **Mithril:** BLS chain-validatie via `mithril-common` v0.6 (Apache-2.0)
- **Distributie:** één APK, drie modi (zie C-03)
- **Updates:** on-chain pointer-bericht vanaf hardcoded officieel adres; nooit auto-install

## Distributie-architectuur (C-03 — drie modi)

Één APK, drie gebruikersmodi. Geen aparte builds.

**v1.0 (nu):** Sovereign-only, geen Play Store. GitHub Releases + F-Droid + Arweave + IPFS.

**v1.x:** Google Play Store als primair kanaal voor Lite/Advanced (mainstream Mary). Bij Play Store-uitval (bijv. juridische druk) triggert Pad A automatisch: PackageManager detecteert dat installer niet `com.android.vending` is → banner → gebruiker kiest bewust voor Sovereign → updates via eigen mirrors.

**Apple App Store:** absoluut verboden, zonder uitzondering.

Sovereign is niet de startpositie maar de fallback die altijd klaar staat. De sovereiniteit wordt relevant zodra politieke druk crypto aan banden wil leggen.

## Onveranderlijke contracten (CONTRACTS.md, 16 stuks)

- C-01: Geen seed of private key verlaat ooit het toestel
- C-02: Geen single point of failure dat eenzijdig censureerbaar is
- C-03: Één APK, drie modi (Lite/Advanced/Sovereign). Play Store voor v1.x (Lite/Advanced); Apple App Store absoluut verboden
- C-04: Hardware-wallet abstractie verplicht — geen vendor lock-in
- C-05: Reproducible build voor elke release
- C-06: Updates worden nooit automatisch geïnstalleerd
- C-07: Geen telemetry, geen analytics, geen 3rd-party tracking
- C-08: Bech32-validatie voor elk ontvangstadres
- C-09: FLAG_SECURE op alle schermen met seed of wachtwoord
- C-10: SecureRandom/OsRng voor seed-generatie, geen thread_rng
- C-11: Stake-key isolatie bij send-transacties
- C-12: Geen automatische updates, ooit
- C-13: SOCKS5 proxy-discipline (aeterna-http crate)
- C-14: Decoy vault structuur in wallet.bin
- C-15: CBDC-resistance roadmap (v2.x scope)
- C-16: Geen Google Play Services dependency

## Roadmap

- v1 (live): hot wallet + Ledger Nano X/S+ via USB-OTG + watch-only
- v1.x: Play Store submission (Lite/Advanced), Sovereign failover live
- v1.1: Tor SOCKS5 proxy (geïmplementeerd)
- v1.2: Decoy vault / panic PIN
- v1.3: BC-UR QR air-gap (Keystone/NGRAVE)
- v2: iOS, native token send, multisig, opt-in tip-mechanisme

## Lokale paden

- Repo: `D:\GitHub\Aeterna\`
- Volledig plan: `D:\GitHub\Aeterna\PLAN.md`
- Onveranderlijke regels: `D:\GitHub\Aeterna\CONTRACTS.md`
- Project-context: `D:\GitHub\Aeterna\.claude\context.md`
- Handover: `D:\GitHub\Aeterna\.claude\handover.md`
