# Decision 005: Studieplanner Architecture

**Datum:** 22 december 2025
**Laatste update:** 21 maart 2026
**Status:** Geïmplementeerd
**Project:** Studieplanner

## Context

Studieplanner is een studieplanningsapp voor leerlingen en mentors met:
- Vakken & taken beheer met automatische planning
- Timer met StudyLog tracking (achtergrond)
- Weekagenda met drag & drop
- Mentor-leerling koppeling met real-time status
- Premium statistieken (leersnelheid, streaks)

## Beslissing

**React Native + Expo frontend, eigen Laravel 12 backend, eigen APK distributie.**

### Huidige architectuur

| Component | Keuze | Reden |
|-----------|-------|-------|
| Frontend | React Native + Expo SDK 54 | Native alarms, timer op achtergrond, biometrie |
| Backend | Laravel 12 (eigen app) | Volledig controle, al op server |
| Database | MySQL (prod) / SQLite (dev) | Standaard Laravel |
| Real-time | Laravel Reverb (via HavunCore) | Open source, geen externe kosten |
| Auth | Magic link + biometrie | Geen wachtwoorden, veilig, gebruiksvriendelijk |
| Push | expo-notifications (native) | Betrouwbaar op achtergrond |
| Payments | bunq.me/Havun + XRP (€1/jaar) | €0 transactiekosten, geen commissie |
| Distributie | Eigen server APK | 0% commissie, geen Play Store review |
| State | React Context | Simpel genoeg voor deze app |
| i18n | i18next | Nederlands + Engels |

### Evolutie t.o.v. origineel plan (dec 2025)

| Aspect | Origineel | Nu |
|--------|-----------|-----|
| Real-time | Database polling | Laravel Reverb WebSocket |
| Auth | Pincode + magic link | Magic link only + biometrie |
| Push | Web Push (VAPID) | expo-notifications (native) |
| Frontend | Expo (basis) | Expo SDK 54 + React Navigation 7 |
| Backend | Laravel 11 | Laravel 12 |
| Chat | Polling (5 sec) | Niet geïmplementeerd (niet nodig gebleken) |
| Payments | Mollie iDEAL | bunq.me/Havun + XRP (€0 kosten vs 32% bij Mollie) |
| Updates | Geen OTA | expo-updates OTA + in-app APK download |

### Afgewezen alternatieven

| Optie | Reden afgewezen |
|-------|-----------------|
| PWA | Timer/alarms onbetrouwbaar op achtergrond |
| Firebase | Overkill, vendor lock-in |
| Google Play Store | 15-30% commissie, review delays |
| Redux/Zustand | React Context is voldoende voor deze app |
| Pusher | Externe dependency, kosten → Reverb is gratis |
| Mollie iDEAL | 32% transactiekosten op €1 product → bunq.me = €0 |
| Google Play Store | 15-30% commissie + review delays + jaarlijkse dev fee |

## HavunCore integratie

- Backup: Daily 05:00, 1 jaar retention
- Vault: Credentials centraal beheerd
- WebSocket: Reverb proxy voor mentor real-time updates

## Gevolgen

- Studieplanner is volledig standalone
- Geen externe service dependencies (behalve Brevo email)
- Betalingen 100% kosteloos: bunq.me (€0) + XRP (€0)
- Eigen distributiekanaal = volledige controle over updates (OTA + APK)
- Later uitbreidbaar naar iOS indien gewenst
