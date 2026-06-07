---
title: "ADR: Health-alerts van e-mail naar in-app meldingen"
type: decision
scope: havuncore
last_check: 2026-06-07
---

# ADR: Health-alerts van e-mail naar in-app meldingen

**Datum:** 7 juni 2026
**Status:** Geaccepteerd & geïmplementeerd (Fase 1 live)

## Context

De server-side health-check (`havun-health-check.sh`, cron `*/5`) mailde up/down-alerts
via SendGrid. Dat brak stil: SendGrid zat op `Maximum credits exceeded`, dus alerts
kwamen niet aan. Bij het onderzoek (reverb 2,5 dag down zonder melding, 4-6 juni) bleek
ook dat reverb niet bewaakt werd en JudoToernooi op een dode URL stond.

## Beslissing

Geen eigen e-mail meer voor health-alerts. In plaats daarvan:

1. **In-app meldingen** in de HavunCore-webapp — 🔔 bel + paneel, real-time via Socket.io.
   Algemene/server-meldingen bovenaan, daarna gegroepeerd per project.
2. **Classificatie bij de bron**: `scope=server` (algemeen) vs `scope=project` (gebonden aan
   een app). Het script bepaalt dit per check.
3. **Externe vangnet = UptimeRobot** voor totale serveruitval (dan werkt in-app ook niet).
4. **Gefaseerd**: Fase 1 = centraal in HavunCore-webapp (live). Fase 2 = project-meldingen
   óók in de betreffende app zelf, via `GET /api/health-alerts?project=<naam>`.

## Waarom

- Mail was fragiel (provider-limiet, stil falen) en Henk wil sowieso geen mail.
- In-app meldingen zijn zichtbaar waar je al kijkt (de status-PWA), zonder externe afhankelijkheid.
- UptimeRobot dekt het enige gat dat in-app niet kan dekken (server volledig plat).
- Volgt het bestaande AutoFix-patroon (DB-tabel i.p.v. mail).

## Implementatie

- `health_alerts` tabel (één rij per `key`: down=upsert open, up=resolve, up-op-gezond=no-op).
- `HealthAlert` model, `health:alert` artisan command, `HealthAlertController`
  (`GET /api/health-alerts`, `POST /{id}/dismiss`).
- Script roept `php artisan health:alert` aan i.p.v. mail; `havun-health-alert.php` = DEPRECATED.
- Real-time: command pingt webapp-backend (`/api/internal/notify`, localhost-only) → `io.emit`.
- nginx: `health-alerts` toegevoegd aan de Laravel-allowlist van `havuncore.havun.nl`.
- Webapp: `useHealthAlerts`-hook + `NotificationBell` in de Header (geen polling).

## Gevolgen

- E-mail-alerting is dood; niet reactiveren tenzij expliciet gevraagd (raakt `.env` → overleg).
- Nieuwe checks: voeg een `check_*`/`emit_alert`-aanroep toe in `havun-health-check.sh` met
  juiste `--scope`/`--project`.
- Zie ook: `runbooks/uptime-monitoring.md`, `runbooks/reverb-troubleshoot.md` (§6).
