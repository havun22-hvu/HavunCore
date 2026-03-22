# Runbook: Android App bouwen met Expo (React Native)

> Standaard procedure voor het bouwen van een Android app met Expo.
> Gebaseerd op: Studieplanner (eerste Havun Expo app, januari 2026).

## Wanneer gebruiken?

- Nieuwe mobiele app naast een bestaande Laravel backend
- Scorebord, dashboard, of andere real-time Android app
- Lokaal bouwen en testen met Android Studio emulator

## Vereisten

- Node.js 18+
- npm
- Expo CLI: `npm install -g eas-cli`
- Expo account (havun22) — inloggen via `eas login`
- **Android Studio** (lokale builds + emulator testen)
- Laravel API backend (bestaand of nieuw)

## Stap 1: Expo project aanmaken

```bash
npx create-expo-app@latest [projectnaam] --template blank-typescript
cd [projectnaam]
```

## Stap 2: Essentiële dependencies installeren

```bash
# Navigation
npx expo install @react-navigation/native @react-navigation/bottom-tabs @react-navigation/stack react-native-screens react-native-safe-area-context

# Storage
npx expo install @react-native-async-storage/async-storage

# Updates (OTA + APK check)
npx expo install expo-updates expo-application expo-file-system expo-intent-launcher

# Optioneel maar aanbevolen
npx expo install expo-notifications expo-local-authentication date-fns i18next react-i18next
```

## Stap 3: app.json configureren

```json
{
  "expo": {
    "name": "[AppNaam]",
    "slug": "[appnaam]",
    "version": "1.0.0",
    "scheme": "[appnaam]",
    "android": {
      "package": "nl.havun.[appnaam]",
      "versionCode": 100,
      "adaptiveIcon": {
        "foregroundImage": "./assets/adaptive-icon.png",
        "backgroundColor": "#1a1a2e"
      },
      "permissions": []
    },
    "updates": {
      "url": "https://u.expo.dev/[project-id]",
      "checkAutomatically": "ON_LOAD",
      "runtimeVersion": { "policy": "appVersion" }
    }
  }
}
```

**Let op:**
- `versionCode` MOET omhoog bij elke APK build
- `scheme` = deep link prefix (bijv. `judoscoreboard://`)
- `package` = uniek Android package name

## Stap 4: eas.json configureren

```json
{
  "cli": { "version": ">= 5.0.0", "appVersionSource": "local" },
  "build": {
    "development": {
      "developmentClient": true,
      "distribution": "internal",
      "channel": "development"
    },
    "preview": {
      "android": { "buildType": "apk" },
      "distribution": "internal",
      "channel": "preview"
    },
    "production": {
      "android": { "buildType": "apk" },
      "channel": "production"
    }
  }
}
```

## Stap 5: Project structuur

```
[projectnaam]/
├── app.json                    # Expo config
├── eas.json                    # Build profiles
├── package.json                # Dependencies
├── App.tsx                     # Root component (OTA + update check)
├── assets/
│   ├── icon.png               # App icon (1024x1024)
│   ├── adaptive-icon.png       # Android adaptive icon
│   └── splash-icon.png         # Splash screen
├── src/
│   ├── constants/
│   │   ├── config.ts          # API URL + endpoints
│   │   └── theme.ts           # Kleuren, fonts
│   ├── navigation/
│   │   └── RootNavigator.tsx  # Auth flow + tabs
│   ├── screens/               # Schermen
│   ├── components/            # Herbruikbare componenten
│   ├── services/
│   │   ├── api.ts            # Fetch wrapper + auth
│   │   └── updateChecker.ts  # APK update logic
│   ├── store/                 # React Context (state management)
│   ├── types/                 # TypeScript interfaces
│   └── utils/                 # Helpers
└── .expo/                     # Cache (in .gitignore)
```

## Stap 6: API Service (communicatie met Laravel)

```typescript
// src/constants/config.ts
const DEV_API_URL = 'http://10.0.2.2:8007';  // Android emulator → localhost
const PROD_API_URL = 'https://api.[project].havun.nl';

export const config = {
  apiUrl: __DEV__ ? DEV_API_URL : PROD_API_URL,
};

// src/services/api.ts
class ApiService {
  private token: string | null = null;

  setToken(token: string) { this.token = token; }

  async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(`${config.apiUrl}${endpoint}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...(this.token ? { 'Authorization': `Bearer ${this.token}` } : {}),
        ...options.headers,
      },
    });

    if (!response.ok) throw new Error(`API ${response.status}`);
    return response.json();
  }
}

export const api = new ApiService();
```

**BELANGRIJK:** Android kan `localhost` NIET bereiken! Gebruik:
- Emulator: `http://10.0.2.2:[poort]`
- Fysiek device: gebruik PROD_API_URL of je lokale IP
- APK builds: ALTIJD PROD_API_URL

## Stap 7: APK Update Checker

```typescript
// src/services/updateChecker.ts
import * as Application from 'expo-application';

export async function checkForUpdate(): Promise<UpdateInfo | null> {
  const currentCode = Number(Application.nativeBuildVersion);
  const response = await fetch(`${config.apiUrl}/api/app/version`);
  const server = await response.json();

  if (server.versionCode > currentCode) {
    return server;  // { version, versionCode, downloadUrl, releaseNotes }
  }
  return null;
}
```

### Laravel backend endpoint

```php
// routes/api.php
Route::get('/app/version', function () {
    return response()->json([
        'version' => config('app.mobile_version'),
        'versionCode' => (int) config('app.mobile_version_code'),
        'downloadUrl' => config('app.mobile_download_url'),
        'forceUpdate' => (bool) config('app.force_update'),
        'releaseNotes' => config('app.release_notes', ''),
    ]);
});

// config/app.php
'mobile_version' => env('MOBILE_VERSION', '1.0.0'),
'mobile_version_code' => env('MOBILE_VERSION_CODE', 100),
'mobile_download_url' => env('MOBILE_DOWNLOAD_URL', ''),
'force_update' => env('FORCE_UPDATE', false),
```

## Stap 8: Bouwen en distribueren

### Lokaal ontwikkelen (Android Studio emulator)

```bash
# Start Expo dev server + open in Android emulator
npx expo start --android
```

**Android Studio emulator setup:**
1. Open Android Studio → Virtual Device Manager
2. Maak een device aan (bijv. Pixel 7, API 34)
3. Start de emulator
4. `npx expo start --android` detecteert de emulator automatisch

**Emulator tips:**
- `10.0.2.2` = localhost van je PC (voor API calls)
- Ctrl+M = Expo dev menu openen
- `adb devices` = check of emulator verbonden is

### Lokale APK build (Android Studio)

```bash
# Genereer native Android project
npx expo prebuild --platform android

# Open in Android Studio
# File → Open → [project]/android/

# Build APK: Build → Build Bundle(s) / APK(s) → Build APK(s)
# Output: android/app/build/outputs/apk/release/app-release.apk
```

**Voordeel lokale build:** Geen EAS cloud nodig, snellere iteratie, geen wachtrij.

### EAS Cloud Build (alternatief)

```bash
# Preview APK (testen)
npx eas build --platform android --profile preview
# Download APK van EAS link → test op device

# Production APK
npx eas build --platform android --profile production
```

### Production APK distribueren

```bash
# 1. Version bump in app.json (version + versionCode)
# 2. Build (lokaal of EAS)
# 3. Upload naar server
scp [app].apk root@188.245.159.115:/var/www/[project]/production/public/downloads/[app]-latest.apk
# 4. Update .env: MOBILE_VERSION, MOBILE_VERSION_CODE
```

## Two-tier Update Strategie

| Type | Wat | Hoe | Wanneer |
|------|-----|-----|---------|
| **OTA** | JS bundle (UI, logica, teksten) | `eas update --channel production` | Kleine fixes, geen nieuwe permissions |
| **APK** | Native code, permissions, dependencies | Nieuwe APK build + upload | Nieuwe features met native dependencies |

## APK Distributie (zonder Play Store)

1. APK hosten op eigen server: `/public/downloads/[app]-latest.apk`
2. Download link op website of in-app
3. Gebruiker installeert via sideloading
4. Play Protect waarschuwing is normaal (niet in Play Store)

## Checklist nieuwe Expo app

- [ ] `npx create-expo-app` met TypeScript template
- [ ] `app.json` configureren (package, scheme, permissions)
- [ ] `eas.json` aanmaken (3 build profiles)
- [ ] Project structuur opzetten (src/ mappen)
- [ ] API service + config.ts aanmaken
- [ ] Navigation setup (React Navigation)
- [ ] Update checker implementeren
- [ ] Laravel `/api/app/version` endpoint
- [ ] Eerste preview build draaien
- [ ] APK testen op fysiek device
- [ ] Production build + upload naar server
- [ ] Download pagina op website

## Bekende gotchas

| Issue | Oorzaak | Fix |
|-------|---------|-----|
| `localhost` niet bereikbaar | Android sandboxing | Gebruik `10.0.2.2` (emulator) of PROD_URL |
| APK update niet gedetecteerd | `versionCode` niet omhoog | Verhoog versionCode in app.json |
| EAS build faalt | Node versie mismatch | Check `.nvmrc` of `engines` in package.json |
| Deep links werken niet | `scheme` niet ingesteld | Stel in `app.json` en rebuild |
| Play Protect waarschuwing | Niet in Play Store | Normaal bij sideloading, informeer gebruiker |
| Emulator kan API niet bereiken | Verkeerde URL | `10.0.2.2:[poort]` i.p.v. `localhost:[poort]` |
| `npx expo prebuild` faalt | Oude native bestanden | Verwijder `android/` map en prebuild opnieuw |

## Android Studio Setup (eenmalig)

### Installatie
1. Download Android Studio van developer.android.com
2. Installeer met standaard opties
3. SDK Manager: installeer API 34 (Android 14) + Build Tools
4. AVD Manager: maak een Pixel 7 device (API 34)

### Environment variables (Windows)

```
ANDROID_HOME = C:\Users\[user]\AppData\Local\Android\Sdk
Path += %ANDROID_HOME%\platform-tools
Path += %ANDROID_HOME%\emulator
```

### Verificatie

```bash
adb devices          # Toont verbonden devices/emulators
emulator -list-avds  # Toont beschikbare AVDs
```

## Huidige Expo Apps

| App | Package | Poort | Status |
|-----|---------|-------|--------|
| Studieplanner | nl.havun.studieplanner | 8010 | Production |
| JudoScoreBoard | nl.havun.judoscoreboard | 8011 | In development |

---

*Gebaseerd op: Studieplanner v1.0.2 + JudoScoreBoard v1.0.0*
*Laatst bijgewerkt: 22 maart 2026*
