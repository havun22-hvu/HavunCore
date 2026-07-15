---
title: Grote schoonmaak server + repos (15-07-2026)
type: plan
scope: havuncore
last_check: 2026-07-15
---

# Grote schoonmaak — plan

**Opdracht Henk (15-07):** "alles moet opgeruimd worden, niets laten staan, schone lei" +
"had niet zover mogen komen, je mag de werkinstructies aanpassen om dit te voorkomen".

**Uitgangspunt:** `git clean -fd` op prod zou een **outage** veroorzaken. De "dirty" bestanden
zijn grotendeels live content. Schoonmaken = het onderscheid maken, niet alles wissen.

## Wat er is (geïnventariseerd 15-07)

| Locatie | Wat | Oordeel |
|---|---|---|
| `studieplanner/public/downloads` | **874 MB** APK's (latest, v1.0.2, v1.0.3) | Live download → **gitignore**, niet wissen |
| `studieplanner/public/ota` | **34 MB** Expo OTA-bundles | Live updates → **gitignore** |
| `studieplanner/public/favicon.png` | asset | Uitzoeken: in git of gitignore |
| `havuncore/webapp/public/` | gebouwde PWA + apk's | Deploy-output (rsync) → **gitignore** |
| `herdenkingsportaal/public/fonts/` | `PlayfairDisplay-Regular.ttf`, 300 KB, 24 apr | **Verweesd** — zie correctie hieronder |
| `safehavun/public/landing.html` | 4 regels: "Gratis" → "Binnenkort beschikbaar" | **Bewuste tekstwijziging, alleen op server** → naar git (Henk bevestigt) |
| `havunclub/public/aeterna-latest.apk` | APK van een ander project | Uitzoeken: wordt hij geserveerd? Zo nee → weg |
| `infosyst` (14 dirty) | `CLAUDE.md`, `mpc.md`, `.gitignore`-churn | Achterhaalde drift → **reset** |
| nginx `havuncore.havun.nl.bak.2026-07-02` | dode vhost | Veroorzaakt de conflicting-server-name-warnings → **weg** |
| nginx `staging.havunvet.havun.nl` | HavunVet = obsoleet (Henk, 02-07) | **weg** |
| **29 stashes** (zie onder) | HP-prod 8, HP-staging 6, SP 5, VPD 3, SH 2, JT-prod 2, HA 1, HC 1, JT-staging 1, JT-oud 1 | Per stuk beoordelen |
| `/var/www/judotoernooi` | **verweesde checkout** (maart 2026): 1209 "dirty" = deletions, bestanden verhuisd naar `repo-prod`/`repo-staging`. `.git` bleef achter | Rommel → weg (ná de stash) |
| `/var/www/vpdupdate` | **63 dirty** + 3 stashes. `users.json` is getrackt mét bcrypt-hashes | Uitzoeken; hangt samen met de security-schuld |
| `/var/www/havun.nl` | 3 dirty | Uitzoeken (portfolio, Next.js) |
| `havunclub/staging` | 1 dirty | Uitzoeken |
| Munus `kb-audit-latest.md` | 1 regel datum-bump | **committen** |

> **Correctie op de eerste telling:** ik scande alleen `/var/www/*/production` en miste daardoor
> JudoToernooi (`repo-prod`), VPDUpdate, havun.nl, de staging-checkouts en de verweesde checkout.
> 8 vervuilde checkouts bleken er 12, en 17 stashes bleken er 29. Dat is precies waarom de
> `/start`-check nu op **alle** `.git`-dirs onder `/var/www` moet zoeken, niet op een pad-patroon.

## Agenda

### A1 — Stashes beoordelen (eerst, want hier kan werk in zitten)
Per stash: is de inhoud **al in origin** gecommit? Test:
`git stash show -p stash@{i} | git apply --check -R` → slaagt = al toegepast = veilig te droppen.
Zo niet: bundelen naar lokaal, beoordelen, en pas droppen na Henks akkoord.
Verdachte kandidaten (substantieel): HP `{3}` (257 ins, QR login CSRF), HP `{6}` (165 ins, Arweave
deep hash), SP `{1}` (198 ins, Observability), SP `{4}` (student invite system), HC `{0}` (136/220).

### A2 — Live content veiligstellen
- **SafeHavun landing.html** → server-versie is nieuwer dan git. Via bundle naar lokaal + committen.
  De inhoud ("Gratis" eruit) is een **business-keuze** → Henk bevestigt vóór commit.
- **Herdenkingsportaal fonts — CORRECTIE (geverifieerd 15-07):** hoort **niet** in git. Het font
  wordt nergens geladen: de views halen Playfair Display van de **Google Fonts CDN**, en de
  beeldgeneratie (`HandlesMemorialImages`) gebruikt GD's ingebouwde font (`$font = 5`). Nul
  referenties naar `/fonts/` in de hele codebase. Staat er sinds 24 april ongebruikt = restant van
  een experiment. → Naar `/var/backups/` verplaatsen (niet `rm`), zoals eerder met `.env.bak`.
  *Les: ook "dit hoort duidelijk in git" moet je verifiëren.*

### A3 — Deploy-output gitignoren (checkout schoon zónder iets te wissen)
`.gitignore` per project: `public/downloads/`, `public/ota/`, webapp `public/`.
**Niets verwijderen** — alleen uit de git-status halen.

### A4 — Echt weg
- nginx: 2 dode vhosts (na `nginx -t` + reload).
- infosyst: achterhaalde CLAUDE.md/mpc.md-drift → `git checkout --`.
- Munus: committen.

### A5 — Preventie in de werkinstructies (de kern van Henks opmerking)
Dit had niet mogen gebeuren. Oorzaken en tegenmaatregelen:
1. **Deploy-output stond niet in `.gitignore`** → elke deploy maakte de checkout "dirty", en niemand
   wist meer wat echt was. → A3 lost dit op; regel: **wat een deploy of upload produceert, hoort in
   `.gitignore`** — dan is "dirty" altijd een signaal.
2. **Stashes als afvalbak bij deploys.** `deploy-havun.sh` en handmatige deploys stashen bij drift en
   ruimen nooit op. → Regel: een pre-deploy-stash is **tijdelijk**; los hem op in dezelfde sessie
   (toepassen of droppen met reden). Nooit laten liggen.
3. **Server-side wijzigingen die nooit terugkomen** (SafeHavuns landing.html). Prod kan niet pushen
   (by design). → Regel: wijzig **nooit** content direct op prod; kan het niet anders, dan diezelfde
   sessie via bundle terug naar git.
4. **Niemand keek.** → `/start` checkt nu de prod-checkouts.

Vastleggen in: `docs/kb/standards/server-hygiene.md` + `/start` + `/end`.

## UITGEVOERD (15-07-2026) — eindstand

| Was | Nu |
|---|---|
| **29 stashes** verspreid over 10 checkouts | **0** — alle 30 patches geback-upt in `/var/backups/havun-stashes-2026-07-15` |
| **3× nginx conflicting-server-name warning** | **0** — 2 dode vhosts weg (backup in `/var/backups/havun-nginx-2026-07-15`) |
| Verweesde checkout `/var/www/judotoernooi/.git` | Weg. **Losse historie** (geen merge-base met origin, 2432 oude commits) → volledig gebundeld: `judotoernooi-oude-historie-tot-maart2026.bundle` (103 MB, `bundle verify` = complete history) |
| 12 vervuilde checkouts | 6, en die wachten enkel op de volgende deploy |

**Gered — bestond nergens anders (prod kan niet pushen):**

| Wat | Waar nu |
|---|---|
| SafeHavun landing.html ("Gratis" → "Binnenkort") | `SafeHavun` master |
| Infosyst `Infosyst-Import-Python.zip` (de `.exe` was wél getrackt) | `Infosyst` master |
| havun.nl `ecosystem.config.js` (PM2, poort 3003) | `Havun` master |
| Studieplanner `favicon.png` — layout verwijst ernaar, git had 'm niet | `Studieplanner-api` master |
| Studieplanner stash{1}+{4} (UserSettings, Observability, syncSettings) | branch `rescue/prod-stashes-2026-07-15` |
| VPDUpdate pin-endpoints/setup-pin/search | branch `rescue/prod-untracked-2026-07-15` |
| HP biometric-wiring (route naar een gecommitte, onbereikbare view) | branch `rescue/prod-stash-2026-07-15` |
| JudoScoreBoard 99 MB APK uit stash | `/var/backups/.../judoscoreboard-stash-99MB.apk` |

**Nog open — vereist Henk:**
- `havunclub/public/aeterna-latest.apk` (26 MB, APK van een ander project, 4 mei). Geen nginx-regel,
  maar Laravel serveert `public/` → de link `havunclub.havun.nl/aeterna-latest.apk` kan gedeeld zijn
  met Aeterna-testers. Niet verwijderd: dezelfde APK staat als `aeterna-snapshot.apk` in de webapp.
- **VPDUpdate `users.json` is getrackt mét live bcrypt-hashes + TOTP-secrets** → staat in de
  GitHub-historie. Untracken raakt de deploy (verse clone heeft dan geen bestand) = eigen taak.
- De 3 rescue-branches: beoordelen en dan opruimen.
- De 6 resterende dirty checkouts verdwijnen vanzelf bij hun volgende deploy.

## Grenzen

- Server-config en prod-data = **Henks go per stap** (rules.md). Dit plan vraagt die go per punt.
- **Vusista niet aanraken:** daar draait een parallelle sessie (PhotoEditor, 17:35-17:38).
- Niets wissen waarvan de enige kopie op de server staat, vóór het in git zit.
