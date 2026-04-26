---
title: Runbook: APK Security & Robustness Check
type: runbook
scope: havuncore
last_check: 2026-04-25
---

# Runbook: APK Security & Robustness Check

> **Frequentie:** Voor elke release naar Play Store / publieke distributie + kwartaal-check.
> **Geldt voor:** JudoScoreBoard, Studieplanner-mobile, toekomstige Android-apps.
> **Aanpak:** Handmatig (zoals SSL Labs / Mozilla Observatory) — geen automatisering.

## Online test-tools

| Tool | URL | Wat | Gratis? |
|------|-----|-----|:------:|
| **MobSF (online)** | https://mobsf.live | Statisch + dynamisch, OWASP Mobile Top 10, secrets, permissies | ✅ |
| **VirusTotal** | https://www.virustotal.com | 70+ AV-engines, basale APK info | ✅ |
| **Hybrid Analysis** | https://www.hybrid-analysis.com | Sandbox-detonation, gedrag | ✅ |
| **APKLab** | https://apklab.io | Componenten, dependencies | ✅ |

**Standaard:** MobSF (compleetste rapport).

## Procedure (handmatig)

1. **Build release APK** (niet debug):
   ```bash
   # JudoScoreBoard
   cd D:\GitHub\JudoScoreBoard\android && ./gradlew assembleRelease
   # → android/app/build/outputs/apk/release/app-release.apk

   # Studieplanner
   cd D:\GitHub\Studieplanner\android && ./gradlew assembleRelease
   # → android/app/build/outputs/apk/release/app-release.apk
   ```

2. **Upload naar https://mobsf.live**
   - Sleep `.apk` op de pagina
   - Wacht ~2 min op statische analyse

3. **Beoordeel het rapport** (zie checklist hieronder)

4. **Documenteer bevindingen** in dit runbook of in `docs/audit/apk-scan-{datum}.md`

## Verplichte checklist (per release)

### Score & ratings
- [ ] **MobSF Security Score** ≥ 50 (lager = HIGH-risk findings)
- [ ] Geen **critical** of **high severity** findings zonder accept-rationale

### Permissies
- [ ] Geen **dangerous** permissies die je niet gebruikt
- [ ] Geen **signature/system** permissies (alleen voor system apps)
- [ ] Privacy-permissies (location, contacts, microphone) alleen als feature dat vereist

### Code quality
- [ ] Geen **hardcoded API keys, tokens, passwords** in strings.xml of source
- [ ] Geen **debug code** in release build (`Log.d`, `console.log`)
- [ ] Geen **test endpoints** of `localhost`-references

### Network security
- [ ] **Cleartext traffic disabled** (`android:usesCleartextTraffic="false"`)
- [ ] **Network Security Config** present (`@xml/network_security_config`)
- [ ] **Certificate pinning** voor kritieke endpoints (optioneel maar aanbevolen)
- [ ] Alle API-calls via HTTPS

### Code obfuscation
- [ ] **ProGuard/R8** enabled in release build (`minifyEnabled true`)
- [ ] **Resource shrinking** enabled (`shrinkResources true`)

### WebView (indien gebruikt)
- [ ] **JavaScript disabled** waar mogelijk
- [ ] Geen `addJavascriptInterface` met user content
- [ ] Geen `setAllowFileAccess(true)` op untrusted content

### Backup & data leakage
- [ ] `android:allowBackup="false"` in AndroidManifest (tenzij encrypted backup)
- [ ] Geen gevoelige data in logs / external storage
- [ ] SharedPreferences zonder cleartext credentials

### Signing
- [ ] Release APK is **gesigneerd** met productie keystore
- [ ] Keystore backup buiten git (zie `.claude/context.md` op USB vault)

## Wat doen bij findings

| Severity | Actie |
|----------|-------|
| Critical / High | Niet releasen totdat opgelost |
| Medium | Fix in volgende release, document in handover |
| Low / Info | Beoordeel — accept of negeer |

## Bekende structurele zwakheden in onze stack

### React Native / Expo apps
- **JS bundle is leesbaar** in `assets/index.android.bundle` — **geen secrets in JS-code**
- Gebruik `react-native-config` met `.env` (niet in bundle) of native modules voor secrets
- API tokens via auth-flow (login → ontvang token), nooit hardcoded

### Native Android (toekomst)
- BuildConfig fields kunnen uitgelezen worden — geen secrets in `BuildConfig`
- Gebruik Android Keystore voor key storage

## Geschiedenis van scans

| Datum | App | Versie | Score | High | Medium | Notities |
|-------|-----|--------|:-----:|:----:|:------:|----------|
| 2026-04-25 | JudoScoreBoard | (eerste scan) | TBD | - | - | Baseline |
| 2026-04-25 | Studieplanner | (eerste scan) | TBD | - | - | Baseline |

> Update na elke scan. Score lager? → uitzoeken wat verslechterd is.

---

*Aangemaakt: 25 april 2026 — eerste APK-baseline ronde nog te doen.*
