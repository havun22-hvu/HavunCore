# Regression Guard Tests Pattern

> **Probleem:** Kritieke code wordt herhaaldelijk per ongeluk verwijderd of gebroken bij refactors.
> **Oplossing:** PHPUnit tests die niet alleen functionaliteit testen, maar ook bewaken dat code/structuur aanwezig blijft.

## Wanneer inzetten

| Situatie | Actie |
|----------|-------|
| Bug 1x gefixt, simpele fix | Geen test nodig |
| Bug 2x teruggekomen | Regression test |
| Code 3x+ per ongeluk verwijderd | **Regression guard test** |
| Kritieke koppeling tussen backend en frontend | **Structural guard test** |

## Drie typen

### 1. Regression Test (functionaliteit)

Test dat de fix werkt. Standaard unit test.

```php
#[Test]
public function weight_exceeded_returns_gewicht_problem(): void
{
    // Arrange: maak poule met te groot gewichtsverschil
    $poule = $this->maakPouleMetJudokas([25.0, 29.0]); // 4kg verschil, max 3kg

    // Act
    $problemen = $poule->checkPouleRegels();

    // Assert
    $this->assertNotEmpty($problemen);
    $this->assertEquals('gewicht', $problemen[0]['type']);
}
```

### 2. Guard Test (structuur)

Test dat kritieke methoden/keys bestaan. Vangt onbedoelde verwijdering.

```php
#[Test]
public function checkPouleRegels_method_exists_on_poule_model(): void
{
    $this->assertTrue(
        method_exists(Poule::class, 'checkPouleRegels'),
        'CRITICAL: Poule::checkPouleRegels() method is missing!'
    );
}

#[Test]
public function buildPouleResponse_includes_problemen_key(): void
{
    $controller = app(PouleController::class);
    $method = new \ReflectionMethod($controller, 'buildPouleResponse');
    $method->setAccessible(true);

    $result = $method->invoke($controller, $poule);

    $this->assertArrayHasKey('problemen', $result,
        'CRITICAL: buildPouleResponse() must include "problemen" key.'
    );
}
```

### 3. Structural Guard (view/JS integriteit)

Test dat Blade views kritieke patronen bevatten. Vangt silently-broken frontend.

```php
#[Test]
public function blade_view_contains_critical_js_patterns(): void
{
    $content = file_get_contents(resource_path('views/pages/poule/index.blade.php'));

    $this->assertStringContainsString(
        'function updatePouleStats(pouleData)',
        $content,
        'CRITICAL: updatePouleStats() JS function is missing'
    );

    $this->assertStringContainsString(
        'pouleData.problemen',
        $content,
        'CRITICAL: JS must read problemen from server response'
    );
}
```

## Naamgeving

```
tests/Unit/Models/{Model}{Feature}Test.php
tests/Feature/{Feature}GuardTest.php
```

Voorbeelden:
- `PouleCheckRegelsTest.php` — regression + guard voor poule regels
- `PaymentResponseGuardTest.php` — guard dat payment response keys bevat

## Combinatie met DO NOT REMOVE

Guard tests werken het best in combinatie met `DO NOT REMOVE` comments:

```php
// In de code:
// DO NOT REMOVE: problemen must always be recalculated
$problemen = $poule->checkPouleRegels();

// In de test:
$this->assertArrayHasKey('problemen', $result,
    'CRITICAL: buildPouleResponse() must include "problemen" key.'
);
```

De comment waarschuwt de ontwikkelaar, de test vangt het als het toch gebeurt.

## Escalatiepad

```
Bug gefixt
  → Komt terug? → Regression test
    → Komt WEER terug? → + Guard test + DO NOT REMOVE
      → Komt NOG EENS terug? → + CLAUDE.md regel + Memory entry
```

## Checklist bij nieuwe guard test

- [ ] Test dat de methode/functie EXISTS (method_exists of assertStringContainsString)
- [ ] Test dat het WERKT (functionele assertions)
- [ ] Test dat het RESOLVED na de fix (remove/change → probleem weg)
- [ ] Test dat de KOPPELING intact is (response bevat key, view bevat JS)
- [ ] CRITICAL message in assertion zodat het duidelijk is wat er mis is

## Voorbeeld uit praktijk

**JudoToernooi — Poule regels check (5x vergeten, mrt 2026)**

Probleem: Na drag/remove van judoka werden gewichts- en leeftijdswaarschuwingen niet bijgewerkt.
Oorzaak: De check-code werd telkens verwijderd bij refactors.

Oplossing: `PouleCheckRegelsTest.php` met 13 tests:
- 1 method-exists guard
- 6 functionele regression tests (gewicht, leeftijd, gecombineerd)
- 3 resolve-na-remove tests
- 1 buildPouleResponse guard (problemen key)
- 1 structural guard (blade view patronen)
- 1 edge case (leeg/1 judoka)

Resultaat: Als iemand de code aanpast en iets breekt, faalt minstens 1 test.

## Waarom dit KRITIEK is (multi-session context)

Bij Havun projecten draaien regelmatig 10+ parallelle Claude sessies die onafhankelijk code schrijven. Geen sessie weet wat de andere doet. Dit leidt tot:
- Sessie 3 breekt wat sessie 1 net gefixt heeft
- ~60% van de tijd gaat naar herstelwerkzaamheden
- Features die werkten zijn na een refactor ineens kapot

**Code coverage** en **regression guard tests** zijn daarom geen luxe maar noodzaak:
- Tests vangen direct als een andere sessie iets breekt
- Coverage toont waar de gaten zitten (welke code niet beschermd is)

### Code coverage opzetten

```bash
# PCOV installeren (sneller dan Xdebug voor coverage)
# In php.ini:
# extension=pcov
# pcov.enabled=1

# Coverage rapport genereren
php artisan test --coverage

# Coverage met minimum threshold
php artisan test --coverage --min=60
```

### Minimum coverage targets

Havun-norm: **80%** line coverage per project (target 90%+).
Actuele coverage per project → [`test-coverage-normen.md`](../runbooks/test-coverage-normen.md).

### VERPLICHT bij elke sessie

```
1. php artisan test          ← VOOR je begint
2. [maak wijzigingen]
3. php artisan test          ← NA je wijzigingen
4. [schrijf nieuwe tests]
5. php artisan test          ← Alles groen? Dan pas committen
```
