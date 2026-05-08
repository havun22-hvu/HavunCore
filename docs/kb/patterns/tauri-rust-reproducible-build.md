---
title: "Pattern: Tauri 2.0 + Rust Reproducible Build (F-Droid Strip-Compare-Sign)"
type: pattern
scope: havuncore
last_check: 2026-05-03
origin: aeterna v1, week 2 skeleton
---

# Pattern: Tauri 2.0 + Rust Reproducible Build

> Bouw bit-voor-bit identieke binaries vanuit publieke source-code via een frozen Docker-environment + F-Droid's strip → compare → sign methodiek. Toepasbaar voor elke app waar derden moeten kunnen verifiëren dat de gedistribueerde binary uit de publieke source komt.

## Wat is het?

Een CI-pipeline die per release:

1. **Build** in een frozen Docker-container met gepinde toolchain
2. **Strip** signatures uit de output-binary om alleen intrinsieke inhoud over te houden
3. **Compare** hashes van twee onafhankelijke build-runs (matrix-strategy in GitHub Actions)
4. **Sign** alleen wanneer de compare slaagt — anders blocker

Hiermee kunnen derden de release reproduceren door dezelfde Docker-container te draaien op een eigen machine en SHA-256 te vergelijken met de gepubliceerde hash.

## Wanneer gebruiken?

- Crypto-wallets, wallets, sleutel-handlers (security-kritiek)
- Apps die als "verifieerbare open source" worden gepubliceerd op F-Droid
- Distributie-modellen waar app-stores worden vermeden (no Google Play, no App Store)
- Apps met een on-chain hash-publicatie-pad (bv. Cardano-tx, Ethereum-event)
- Cross-platform Tauri-apps (Windows + Android + Linux)

## Kernprincipes

1. **Frozen environment** — Docker base-image met SHA-digest-pinning, niet `:latest`
2. **Pinned toolchain** — `rust-toolchain.toml` met exacte channel-versie, geen `stable`
3. **Pinned dependencies** — `Cargo.lock` gecommit, dependency-additie per audit-moment
4. **Reproducible timestamps** — `SOURCE_DATE_EPOCH` zetten, `TZ=UTC`, geen `chrono::Local`
5. **Strip-then-compare** — APK V2/V3 signing-metadata varieert per signing-run; vergelijk gestripte hashes, niet getekende
6. **Two independent builds** — matrix-strategy in CI dwingt af dat dezelfde input bit-voor-bit hetzelfde output geeft op verschillende runners
7. **Hash-publicatie op meerdere kanalen** — niet alleen GitHub Releases, ook Arweave + on-chain + F-Droid metadata

## Implementatie

### 1. `rust-toolchain.toml`

```toml
[toolchain]
channel = "1.85.0"
profile = "minimal"
components = ["rustfmt", "clippy"]
targets = [
    "x86_64-pc-windows-msvc",
    "aarch64-linux-android",
]
```

### 2. Docker base-image (pinned by digest)

```dockerfile
FROM ubuntu:24.04@sha256:<paste-digest-uit-CI-output>

ENV DEBIAN_FRONTEND=noninteractive
ENV SOURCE_DATE_EPOCH=1735689600
ENV TZ=UTC

RUN apt-get install -y --no-install-recommends \
    build-essential curl git pkg-config libssl-dev \
    openjdk-21-jdk-headless unzip \
 && rm -rf /var/lib/apt/lists/*

# Pinned Rust + Android NDK + SDK (exacte versies in env-args)
ARG ANDROID_NDK_VERSION=27.2.12479018
ARG ANDROID_PLATFORM=36
# ... (zie origin-doc Aeterna 09-week2-skeleton-blueprint.md voor volledige variant)
```

### 3. F-Droid strip-compare-sign script

```bash
#!/usr/bin/env bash
set -euo pipefail

strip_apk() {
    local in="$1" out="$2"
    cp "$in" "$out"
    zip -d "$out" 'META-INF/*.RSA' 'META-INF/*.SF' 'META-INF/*.DSA' || true
    zipalign -p 4 "$out" "${out}.aligned"
    mv "${out}.aligned" "$out"
}

strip_apk "$1" /tmp/build-1-stripped.apk
strip_apk "$2" /tmp/build-2-stripped.apk

HASH_1=$(sha256sum /tmp/build-1-stripped.apk | cut -d ' ' -f 1)
HASH_2=$(sha256sum /tmp/build-2-stripped.apk | cut -d ' ' -f 1)

[ "$HASH_1" = "$HASH_2" ] || { echo "REPRODUCIBILITY FAIL"; exit 1; }
echo "Reproducibility OK: $HASH_1"
```

### 4. GitHub Actions matrix-build voor compare

```yaml
jobs:
  build:
    runs-on: ubuntu-24.04
    strategy:
      matrix:
        run: [1, 2]
    steps:
      - uses: actions/checkout@v4
      - run: docker build -f .build/reproducible.Dockerfile -t myapp:frozen .
      - run: docker run --rm -v "$PWD:/workspace" myapp:frozen <build-cmd>
      - uses: actions/upload-artifact@v4
        with: { name: apk-${{ matrix.run }}, path: out/*.apk }

  compare:
    needs: build
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/download-artifact@v4
        with: { name: apk-1, path: build1/ }
      - uses: actions/download-artifact@v4
        with: { name: apk-2, path: build2/ }
      - run: .build/strip-compare-sign.sh build1/*.apk build2/*.apk
```

### 5. Wekelijkse regression-check

```yaml
schedule:
  - cron: '0 6 * * 0'   # Elke zondag — build de laatste tag opnieuw
                        # en vergelijk met de gepubliceerde hash
```

## Veelgemaakte valkuilen

- **`:latest` Docker-tag** — niet pinning = niet reproducible. Altijd `@sha256:` digest.
- **`cargo build` met `cargo update`** — `Cargo.lock` overschrijft = drift. Altijd `--locked`.
- **Variabele timestamps in build-output** — `chrono::Local::now()`, `SystemTime::now()` in source-code. Vervang door `SOURCE_DATE_EPOCH` of compile-time-constants.
- **APK-signing in dezelfde build-step** — verschillende signing-runs produceren verschillende metadata. Strip eerst, vergelijk, sign daarna.
- **NDK-versie via `latest`** — Android NDK breekt regelmatig backwards-compatibiliteit. Pin op exacte versie.
- **Build-tooling-cache** — `~/.gradle/caches`, `~/.cargo/registry` kunnen non-deterministische output veroorzaken. Mount fresh in Docker.

## Hash-publicatie naar meerdere kanalen

Een reproducible-build is alleen zinvol als de hash op meerdere onafhankelijke kanalen wordt gepubliceerd zodat een gecompromitteerd kanaal niet alle andere kan vervalsen:

1. **GitHub Release notes** — gewoon, makkelijk vindbaar
2. **Arweave** — eenmalig betaald, permanent, gecensureerd-resistent
3. **On-chain (Cardano/Ethereum metadata-tx)** — vanaf een hardcoded officieel adres
4. **F-Droid metadata** — eigen + officiële repo

Cross-check tussen kanalen door derden = hoge zekerheid.

## Referenties

- F-Droid reproducible-builds: <https://f-droid.org/docs/Reproducible_Builds/>
- reproducible-builds.org/docs/jvm/: APK-specifieke tooling
- SOURCE_DATE_EPOCH-spec: <https://reproducible-builds.org/specs/source-date-epoch/>
- Tauri 2.0 mobile build-docs (versie 2.5+)
- Origin: Aeterna `docs/09-week2-skeleton-blueprint.md`

## Wanneer NIET dit pattern gebruiken?

- Interne tools zonder externe verificatie-eis (overhead niet nodig)
- Apps waar app-store-distributie de enige distributiekanaal is (Google Play tekent zelf, je verliest controle)
- Prototypes / POC's (te zware pipeline voor early development)
- Apps zonder security-kritieke binary (een statische website, een Laravel-monolith — hier is de source-code zelf de "binary")
