---
title: Forms-coverage heuristiek (qv:scan)
type: runbook
scope: alle-projecten
last_check: 2026-06-24
---

# Forms-coverage heuristiek — occurrence vs usage

> De `forms`-check van `qv:scan` (`QualitySafetyScanner::formsCoverage()`) schat
> statisch hoeveel write-routes input-validatie hebben. Draait bij **élk**
> Havun-project — een wijziging raakt de quality-gate-uitkomst overal.

## De twee schattingen

Beide delen door dezelfde noemer (`# Route::(post|put|patch|delete)` over `routes/`)
en worden op 100% gecapt:

| Mode | Teller | Eigenschap |
|------|--------|-----------|
| `occurrence` (legacy) | `# extends FormRequest`-klassen + inline `->validate(` | Ondertelt: een **gedeelde** FormRequest op `store`+`update` telt als 1 klasse maar dekt 2 routes. |
| `usage` (**default**) | `# FormRequest type-hint-injectiepunten` (`function store(FooRequest $r)`) + inline `->validate(` | Route-evenredig: dezelfde gedeelde FormRequest telt 1× per route die hij bewaakt. |

De gating-mode staat in `config/quality-safety.php` → `forms_coverage_mode`
(`QV_FORMS_COVERAGE_MODE`). **Beide** getallen staan altijd in de finding-payload
(`coverage_occurrence_pct` / `coverage_usage_pct`) — dual-compute, zodat je een
project kunt verifiëren vóór je de gate het nieuwe getal laat vertrouwen.

### Usage-regex en de `*Request`-conventie

```
/function\s+\w+\s*\([^)]*\b[A-Z][A-Za-z0-9_]*Request\b\s+\$/
```

- `[^)]*` → de FormRequest mag op elke parameterpositie staan
  (`function update($id, FooRequest $r)`).
- De `*Request`-suffix is wat een FormRequest-type-hint onderscheidt van de base
  `Request $request` (die matcht niet — `Request` mist het vereiste prefix).
- **Aanname:** FormRequests volgen de Laravel-naamconventie `*Request`. Een
  FormRequest die níet zo heet (`class CreateTournament extends FormRequest`)
  wordt door `usage` gemist maar door `occurrence` wel gezien. Acceptabel voor
  deze heuristiek; bij twijfel inspecteer je `coverage_occurrence_pct` ernaast.

## Wat de heuristiek NIET ziet

1. **Input-loze write-routes** (destroy/toggle met enkel route-model-binding)
   zitten wél in de noemer → drukken de coverage kunstmatig. Corrigeren vereist
   route→handler-resolutie (bewust niet gedaan — te fragiel per project).
2. **Service-laag-validatie** (`$service->maak($request->all())` waarna de service
   valideert) telt als 0.

→ Een hoog routesaantal met veel input-loze acties of service-validatie kan
onder 60% scoren terwijl het materieel goed valideert. **Forceer dat niet groen
door de teller losser te maken** — verifieer eerst of het gat echt is (write-route
audit in het project zelf).

## Verificatie 2026-06-24 (lokale projecten, optie C uitrol)

| Project | wroutes | frCls | usage | inline | occ% | use% | verdict |
|---------|--------:|------:|------:|-------:|-----:|-----:|---------|
| havunadmin | 140 | 6 | 11 | 78 | 60 | 64 | clean |
| herdenkingsportaal | 146 | 6 | 7 | 82 | 60 | 61 | clean |
| studieplanner | 37 | 0 | 0 | 30 | 81 | 81 | clean |
| **judotoernooi** | 215 | 9 | 14 | 112 | 56 | **59** | **high** |
| infosyst | 32 | 0 | 0 | 45 | 100 | 100 | clean |
| safehavun | 15 | 2 | 6 | 10 | 80 | 100 | clean |
| havuncore | 34 | 13 | 13 | 17 | 88 | 88 | clean |

- **`use% ≥ occ%` overal, geen regressie** (geen project schuift clean→high/critical).
  Daarom is `usages` veilig als default.
- **JudoToernooi blijft `high` (59%)** — optie C corrigeert de gedeelde-FormRequest-
  ondertelling, maar JudoToernooi's gat zit in de noemer (input-loze routes) +
  mogelijke service-laag-validatie. Dat is **geen** HavunCore-fix: vereist een
  write-route-audit in een JudoToernooi-sessie (zijn 215 routes écht allemaal
  input-verwerkend?). Pas daarna is duidelijk of er validatie ontbreekt of dat de
  noemer eerlijk verkleind moet worden.

## Rollback / per-project

- Globaal terug naar legacy: `QV_FORMS_COVERAGE_MODE=occurrences`.
- Drempels: `thresholds.forms_warning_pct` (60) / `forms_critical_pct` (30).
