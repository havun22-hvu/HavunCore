---
title: CSP Nonce Migratie Plan
type: decision
scope: havuncore
last_check: 2026-04-22
---

# CSP Nonce Migratie Plan

> Alle inline `<script>` tags omzetten naar nonce-based CSP.
> Doel: SecurityHeaders.com score van A naar A+.

## Huidige staat

| Project | Inline scripts zonder nonce | Prioriteit |
|---------|---------------------------|-----------|
| JudoToernooi | 96 | Hoog (publieke site, betalingen) |
| Herdenkingsportaal | 84 | Hoog (publieke site, betalingen) |
| HavunAdmin | 41 | Midden (intern, auth) |
| Infosyst | 16 | Laag (intern) |
| SafeHavun | 13 | Laag (intern) |
| havun.nl | 0 | Klaar (Next.js, geen inline scripts) |
| **Totaal** | **250** | |

## Wat moet er gebeuren per project

### Stap 1: Nonce infrastructure (eenmalig per project)

1. SecurityHeaders middleware aanpassen:
   ```php
   // Genereer nonce per request
   $nonce = base64_encode(random_bytes(16));
   app()->instance('csp-nonce', $nonce);
   
   // CSP header: 'unsafe-inline' → 'nonce-{$nonce}'
   "script-src 'self' 'nonce-{$nonce}' ..."
   ```

2. Blade directive registreren in AppServiceProvider:
   ```php
   Blade::directive('nonce', function () {
       return '<?php echo "nonce=\"" . app("csp-nonce") . "\""; ?>';
   });
   ```

3. `style-src 'unsafe-inline'` mag BLIJVEN — Tailwind vereist dit en het is minder risicovol dan script-src.

### Stap 2: Scripts omzetten

Vervang in ALLE blade bestanden:
```html
<!-- OUD -->
<script>
    // code
</script>

<!-- NIEUW -->
<script @nonce>
    // code
</script>
```

### Stap 3: Testen

- Elke pagina laden na omzetting
- Browser console checken op "Refused to execute inline script" errors
- Als een script geblokkeerd wordt: nonce vergeten → toevoegen

### Stap 4: unsafe-inline verwijderen

Pas als ALLE scripts nonce hebben:
```php
// SecurityHeaders.php — verwijder 'unsafe-inline' uit script-src
"script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net ..."
```

## Uitvoeringsplan

| Fase | Project | Scripts | Geschatte tijd | Wanneer |
|------|---------|---------|---------------|---------|
| 1 | SafeHavun | 13 | 30 min | Bij eerstvolgende sessie |
| 2 | Infosyst | 16 | 30 min | Bij eerstvolgende sessie |
| 3 | HavunAdmin | 41 | 1 uur | Bij eerstvolgende sessie |
| 4 | Herdenkingsportaal | 84 | 2 uur | Aparte sessie |
| 5 | JudoToernooi | 96 | 2 uur | Aparte sessie |

**Begin met de kleinste projecten** — SafeHavun en Infosyst als test. Valideer dat de aanpak werkt voordat je de grote projecten aanpakt.

## Risico's

| Risico | Mitigatie |
|--------|----------|
| Script missen → pagina werkt niet | Test elke pagina na omzetting |
| Third-party scripts (CDN) | Hoeven geen nonce, staan al in CSP allow-list |
| Vite dev server scripts | `'nonce-...'` werkt ook in dev mode |
| Alpine.js x-data inline | Alpine werkt via attributen, niet `<script>`, geen probleem |
| @vite directive | Vite genereert eigen script tags, check of nonce nodig is |

## Wanneer klaar?

Na fase 5:
- `unsafe-inline` verwijderen uit CSP van alle projecten
- SecurityHeaders.com → A+ score op alle sites
- KB doc bijwerken met resultaten

## Opdracht voor Claude sessie

Bij het oppakken van een fase, zeg:
> "Lees `docs/kb/decisions/csp-nonce-migration-plan.md` en voer fase [X] uit voor [project]"

---

*Aangemaakt: 12 april 2026*
