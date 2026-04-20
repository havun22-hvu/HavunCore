---
title: K&V findings zichtbaar in observability dashboard
type: decision
scope: havuncore
date: 2026-04-20
---

# K&V findings als in-app notifications

## Context

`qv:scan` produceert een JSON-log per run in `storage/app/qv-scans/{date}/run-*.json`.
Handover 19/20-04 sprak over "Notifications: in-app (Observability event?) — NOOIT e-mail".
Tot nu toe zijn findings alleen zichtbaar via (a) de KB Markdown-render (`qv:log`), of
(b) de raw JSON-log. Geen "ik log in en zie onmiddellijk rode count".

## Keuze

**Geen nieuwe tabel.** `ObservabilityService::getDashboard()` krijgt een extra section
`quality_findings` die het meest recente scan-result leest uit `storage/app/qv-scans/`.
De dashboard-API is single source of truth — frontends (HavunAdmin, havuncore-webapp)
die de API al consumeren krijgen de findings automatisch in hun UI.

## Waarom geen tabel?

- Findings zijn **stateless snapshots**: elke scan regenereert de volledige waarheid.
- Trending / "wanneer voor het eerst gezien" is nice-to-have, niet blocker — kan later.
- "Resolved / accepted" flag is niet gevraagd. Een finding verdwijnt automatisch
  zodra de onderliggende cause is gefixt (volgende scan ziet 'm niet meer).
- YAGNI: geen migration, geen model, geen fingerprint-dedup-logica.

## Wat er in de dashboard-response komt

```json
"quality_findings": {
  "last_scan_at": "2026-04-20T15:48:12+00:00",
  "totals": { "critical": 0, "high": 4, "errors": 0 },
  "findings": [
    { "severity": "high", "project": "judotoernooi", "check": "forms",
      "title": "Form validation coverage 52%" },
    ...
  ]
}
```

- `last_scan_at`: mtime van de laatste run-JSON, zodat staleness zichtbaar is
- `totals`: aantallen per severity + errors (scanner-failures)
- `findings`: severity / project / check / title — géén volledige payload,
  frontend kan apart `GET /api/observability/quality-findings` raadplegen voor
  details als dat ooit nodig is

## Scope

1. `ObservabilityService::getQualityFindings()` — leest nieuwste run-JSON.
2. `ObservabilityService::getDashboard()` — voegt `quality_findings` toe.
3. Duurzame test: oud bestand ver buiten vandaag → staleness detecteerbaar.
4. Geen frontend-werk in deze commit (HavunAdmin/webapp consumen de dashboard-API al).

## Out of scope

- Nieuwe tabel `qv_findings` — later indien trending gewenst.
- `qv:resolve` command — later indien "accept risk" gewenst.
- Push-notificaties (push/websocket) — dashboard-refresh is genoeg.
