---
title: Lokale HTTPS-interceptie op Henks machine (Avast)
type: reference
scope: havun-breed
last_verified: 2026-07-16
---

# Lokale HTTPS-interceptie — niet de host, niet de registry

**Conclusie:** faalt `curl`/`npm`/Gradle op Henks Windows-machine met
`schannel: CRYPT_E_NO_REVOCATION_CHECK` (exit 35), dan is de oorzaak **Avast HTTPS-scanning
die het certificaat vervangt** — niet de server, niet de package-registry, niet het certificaat
van de site. Gemeten 16-07-2026.

## Herkennen

| Symptoom | Waar |
|---|---|
| `curl` exit 35 op `unpkg.com`, `raw.githubusercontent.com` — terwijl `github.com` 200 geeft | overal |
| Vitest/npm install blokkeert op "SSL-issue met de registry" | havuncore-webapp |
| Gradle-build faalt op cert-download | JudoScoreBoard, LastMatch (APK-build-blocker) |
| Site "niet beveiligd" in de browser terwijl de server een geldig Let's Encrypt-cert heeft | lastmatch.havun.nl (juni 2026) |

## Waarom dit hier staat

Dezelfde oorzaak is drie keer verschillend — en fout — gediagnosticeerd: "een npm-registry
SSL-issue" (stond jarenlang zo in de HavunCore-handover), "de PWA is onbereikbaar" (LastMatch,
terwijl de server prima draaide), en "het certificaat van de host deugt niet". Elke keer werd
er buiten de machine gezocht.

## Wat je doet

1. **Eerst een tweede host testen** vóór je concludeert dat de bron stuk is:
   `curl -o /dev/null -w "%{http_code}" https://github.com`. Krijg je daar wél 200, dan is het
   lokaal.
2. **Werkende route om een lib/asset toch binnen te krijgen:** ophalen via de server
   (`ssh root@188.245.159.115`), dan `scp` naar lokaal, en **de sha256 aan beide kanten
   verifiëren**. Zo is `public/js/qrcode.js` in HavunAdmin binnengekomen.
3. **Structurele fix = Avast HTTPS-scanning uitzetten.** Dat is Henks keuze; het deblokkeert
   ook de LastMatch-APK-build en Vitest in havuncore-webapp.

Verwant: `standards/claims-verifieren.md` (een claim over een externe oorzaak verifieer je bij
de bron, niet in een kopie).
