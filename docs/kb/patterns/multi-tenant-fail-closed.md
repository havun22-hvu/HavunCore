---
title: Multi-tenant scheiding die dicht faalt
type: pattern
scope: havuncore
last_check: 2026-07-19
---

# Multi-tenant scheiding die dicht faalt

> **Probleem:** klanten in één database die elkaars gegevens niet mogen zien. Een
> vergeten controle levert dan een datalek op.
> **Oplossing:** één global scope die **niets** teruggeeft als de tenant onbekend is,
> plus dezelfde afdwinging bij schrijven.

## Wanneer toepassen

Elk project waar meerdere klanten in dezelfde tabellen zitten: ledenadministraties,
verenigingen, webshops per merk.

## De twee fouten die je wilt voorkomen

**1. Falen naar open.** De meest gemaakte fout:

```php
if ($tenant === null) {
    return;              // ← geen filter = ALLE klanten zichtbaar
}
```

Ontbreekt de context door een bug, een vergeten middleware of een queue-job, dan krijg je
alles in plaats van niets. Goed is:

```php
if ($tenant === null) {
    $builder->whereRaw('1 = 0');   // liever leeg dan andermans gegevens
    return;
}
```

**2. Alleen lezen beschermen.** Een leesscope houdt niet tegen dat klant B gegevens *in*
klant A plaatst. Bij VeenLedenadministratie kon zo een verzorger met eigen wachtwoord
worden aangemaakt binnen het gezin van een andere judoschool — een schrijfgat dat via de
login een compleet leesgat werd.

Dwing de tenant daarom **af** bij het opslaan, en vul hem niet alleen aan:

```php
static::saving(function (Model $model) {
    $tenant = app(TenantContext::class)->id();
    if ($model->organization_id !== null && (int) $model->organization_id !== $tenant) {
        throw new TenantMismatchException(...);
    }
    $model->organization_id = $tenant;
});
```

## Modellen zonder eigen tenant-kolom

Adressen, mandaten en abonnementen hangen via een relatie aan de klant. Die zijn zonder
extra scope gewoon opvraagbaar met een id uit de URL. Scope ze via de **parent** in plaats
van een gekopieerde `organization_id` — een kopie kan uit de pas lopen met de bron.

Controleer bij het opslaan ook de foreign key: hoort die parent wel bij de actieve tenant?

## Valkuilen

| Valkuil | Gevolg |
|---|---|
| `TenantContext` als `singleton` | in `queue:work` en Octane erft een job de tenant van de vorige — gebruik `scoped` |
| Pivot-tabellen | die hebben geen model, dus geen scope; `attach()` accepteert vreemde id's |
| `saveQuietly()`, `insert()`, `upsert()` | slaan model-events over, dus ook de afdwinging |
| `whereHas('parent')` | past ook de SoftDeletes van de parent toe: een verwijderd lid maakt zijn eigen mandaat onvindbaar |
| Auth-model met scope | de gebruiker moet opgehaald worden vóórdat de tenant bekend is — kip-ei |

## Testen: mutatietest

Een scope die niet getest is, is er niet. Haal de beveiliging tijdelijk weg en kijk of er
een test faalt. Bij VeenLedenadministratie bleken drie van de zeven beveiligingen
volledig ongetest — precies de fail-closed takken.

Minimale set per model met klantgegevens:

- klant B kan record van A niet ophalen via `find($id)`
- klant B kan record van A niet wijzigen
- zonder context is het resultaat leeg
- nieuwe records krijgen automatisch de juiste klant
- klant B kan niets aanmaken bij klant A

## Herkomst

VeenLedenadministratie, juli 2026. De oude app deed dit met 14 losse middlewares die elk
met de hand `user_id` vergeleken; op één route was dat vergeten, waardoor elke ingelogde
gebruiker de bankrekening van een andere judoschool kon overschrijven.
