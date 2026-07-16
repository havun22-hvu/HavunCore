---
title: "Runbook: assets bouwen bij deploy — de build die stil faalt"
type: runbook
scope: alle-projecten
last_check: 2026-07-16
---

# Assets bouwen bij deploy

> **De kern:** `public/build` staat in `.gitignore`. Zonder `npm run build` **op de server** landt
> geen enkele frontend-wijziging — en je merkt er niets van, want een gefaalde vite-build laat de
> vorige build gewoon staan. De site blijft 200 geven; je wijziging is alleen nergens.

Geldt voor elk Havun-project met Laravel + Vite (HavunAdmin, HavunClub, Herdenkingsportaal,
JudoToernooi, Infosyst, …). **Controleer de asset-hash, niet de HTTP-status.**

## De procedure

Draai de build als `www-data`. Root maakt de bestanden root-owned → 500's die zichzelf niet kunnen
loggen, én de vólgende build faalt op `EACCES`. Zie `standards/server-hygiene.md`.

```bash
P=/var/www/<project>/production
git -C "$P" pull --ff-only        # als root: safe.directory staat in root's gitconfig
chown -R www-data:www-data "$P/public/build" "$P/node_modules/.vite-temp" \
                           "$P/public/js" "$P/resources"
cd "$P" && sudo -u www-data npm run build
sudo -u www-data php artisan migrate --force      # alleen als er migrations zijn
sudo -u www-data php artisan optimize:clear
```

## Verifiëren — anders weet je het niet

```bash
# 1. Draait de build echt? (grep op 'built in' — niet op de exit code van npm)
sudo -u www-data npm run build 2>&1 | grep -v '^npm notice' | tail -3

# 2. Wordt de NIEUWE hash geserveerd?
curl -s https://<host>/login | grep -o 'assets/app-[A-Za-z0-9_-]*\.css'

# 3. Zit je nieuwe class/stijl er echt in?
curl -s "https://<host>/build/assets/app-XXXX.css" | grep -c '<jouw-selector>'
```

Stap 2 is de belangrijke: een oude hash = de build is niet gedraaid of gefaald.

## Twee vallen die dit maandenlang verborgen hielden

Beide gevonden op 16-07-2026 bij HavunAdmin, waar de gebouwde CSS al sinds **25 april** stilstond.

| Val | Symptoom | Fix |
|-----|----------|-----|
| **`node_modules` ouder dan `package.json`** | `ERR_MODULE_NOT_FOUND: Cannot find package 'vite-plugin-manifest-sri'` | `npm ci` — brengt node_modules in lijn met package.json; installeert niets nieuws |
| **`.vite-temp` / `public/build` root-owned** | `EACCES: permission denied, open '…/node_modules/.vite-temp/vite.config.js.timestamp-…'` | `chown -R www-data:www-data` op beide paden |

De tweede is de npm-variant van de bekende composer-quirk: één keer als root geïnstalleerd en de
map blijft root-owned, ook nadat de deploy weer netjes als `www-data` draait.

## Waarom dit zo lang onopgemerkt bleef

De deploy-regel in HavunAdmin's `CLAUDE.md` was `git pull && migrate && optimize:clear`. Dat is
compleet voor een backend-wijziging en **stil kapot** voor een frontend-wijziging. De site bleef
draaien op de april-build, dus niets viel op:

- geen 500, geen foutmelding, geen kapotte pagina
- alleen: nieuwe stijlen ontbreken en nieuwe views zien er "net iets anders" uit
- ontdekt pas toen een fix een Tailwind arbitrary variant (`[&>svg]:w-full`) nodig had die er
  simpelweg niet in zat

**Les:** controleer bij elke deploy-instructie of `npm run build` erin staat zodra het project
Vite gebruikt. Ontbreekt hij → het project deployt zijn frontend al niet meer, ongeacht sinds wanneer.

## Portfolio-scan: waar loopt de build achter?

Draai dit op de server. Het vergelijkt de datum van de gebouwde assets met de laatste commit die
views/css raakte:

```bash
ssh root@188.245.159.115 '
for d in $(find /var/www -maxdepth 3 -name vite.config.js 2>/dev/null | sed "s|/vite.config.js||"); do
  [ -d "$d/public/build/assets" ] || continue
  b=$(ls -lt --time-style=+%Y-%m-%d "$d/public/build/assets/" | awk "NR==2{print \$6}")
  v=$(git -C "$d" log -1 --format=%ad --date=short -- resources/views resources/css 2>/dev/null)
  [ -n "$b" ] && [ -n "$v" ] && [ "$v" \> "$b" ] && echo "$d: build=$b view=$v  <-- ACHTER"
done'
```

**Uitslag 16-07-2026** (signaal, géén diagnose — een view-wijziging hoeft geen nieuwe CSS-class te
bevatten, dus een oude build kán nog kloppen):

| Checkout | Build | Laatste view-commit |
|----------|-------|---------------------|
| herdenkingsportaal/**production** | 27-04 | 17-05 |
| herdenkingsportaal/staging | 02-05 | 17-05 |
| infosyst/**production** | 27-04 | 28-04 |
| studieplanner/**production** | 28-05 | 29-05 |
| vusista/staging | 14-07 | 16-07 |

Verifieer per project met de asset-hash-check hierboven vóór je concludeert dat er iets stuk is.
Let op: HP bouwt in zijn GitHub Actions, dus daar kan de mtime misleiden.

## Zie ook

- `standards/server-hygiene.md` — nooit blind `git clean -fd` op prod, ownership-regels
- `reference/productie-deploy-eisen.md` §3.2b — SRI op @vite-emitted assets
