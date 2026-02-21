# 5 Beschermingslagen tegen Herhaalde Fouten

> **Probleem:** Claude verwijdert of wijzigt UI-elementen, logica of features die bewust zijn toegevoegd. Dit kost de gebruiker telkens tijd om te herstellen.
> **Oplossing:** 5 lagen van bescherming, van licht tot zwaar.

## Waarom dit nodig is

Elke nieuwe Claude sessie begint zonder context over eerdere sessies. Zonder bescherming maakt Claude steeds dezelfde fouten:
- UI-elementen verwijderen die bewust zijn toegevoegd
- "Opschonen" van code die cruciaal is
- Patronen negeren die in eerdere sessies zijn afgesproken
- Features kapot maken bij refactoring

## De 5 Lagen

### Laag 1: MD Documentatie (effort: laag)

**Wat:** Documenteer in `.claude/` of `CLAUDE.md` WAAROM iets bestaat.

**Wanneer:** Bij features die niet vanzelfsprekend zijn, of die eerder per ongeluk zijn verwijderd.

**Voorbeeld:**
```markdown
<!-- In .claude/context.md of CLAUDE.md -->
## Belangrijke UI-elementen
- De "Inschrijven" knop op de landing page MOET altijd zichtbaar zijn
- De breadcrumb navigatie is bewust toegevoegd voor UX
```

**Kracht:** Claude leest CLAUDE.md aan het begin van elke sessie.

---

### Laag 2: DO NOT REMOVE Comments (effort: zeer laag)

**Wat:** Plaats `<!-- DO NOT REMOVE -->` of `// DO NOT REMOVE` comments direct bij kritieke code.

**Wanneer:** Bij UI-elementen, CSS classes, of logica die eerder onterecht verwijderd is.

**Voorbeeld:**
```html
<!-- DO NOT REMOVE - Registration buttons are essential for user conversion -->
<div class="flex gap-4">
    <a href="/register">Registreren</a>
    <a href="/login">Inloggen</a>
</div>
```

```php
// DO NOT REMOVE - Rate limiting prevents API abuse
if ($this->isRateLimited($key)) {
    return;
}
```

**Kracht:** Direct zichtbaar in de code, Claude ziet dit bij het lezen van bestanden.

---

### Laag 3: Tests (effort: medium)

**Wat:** Schrijf tests die breken als kritieke elementen worden verwijderd.

**Wanneer:** Bij features die meerdere keren kapot zijn gegaan, of bij bedrijfskritieke functionaliteit.

**Voorbeeld:**
```php
// Feature test
public function test_landing_page_shows_registration_buttons(): void
{
    $response = $this->get('/');
    $response->assertSee('Registreren');
    $response->assertSee('Inloggen');
}
```

```javascript
// Dusk/browser test
test('registration buttons are visible on landing page', () => {
    cy.visit('/');
    cy.get('[data-testid="register-btn"]').should('be.visible');
    cy.get('[data-testid="login-btn"]').should('be.visible');
});
```

**Kracht:** Objectief bewijs dat iets kapot is. Claude draait tests na wijzigingen.

---

### Laag 4: CLAUDE.md Regels (effort: eenmalig)

**Wat:** Voeg expliciete regels toe aan het project's CLAUDE.md die voor ALLE sessies gelden.

**Wanneer:** Bij patronen die project-breed belangrijk zijn, niet bij individuele elementen.

**Voorbeeld:**
```markdown
## Rules
- Check altijd DO NOT REMOVE comments voordat je views/templates wijzigt
- Verwijder NOOIT UI-elementen zonder expliciete instructie van de gebruiker
- Bij refactoring: behoud ALLE bestaande functionaliteit
```

**Kracht:** Wordt aan het begin van elke sessie gelezen, geldt voor alle taken.

---

### Laag 5: Memory (effort: zeer laag)

**Wat:** Sla cross-session context op in Claude's memory files.

**Wanneer:** Bij project-overstijgende patronen, veelgemaakte fouten, of beslissingen die niet in CLAUDE.md passen.

**Voorbeeld:**
```markdown
<!-- In memory/MEMORY.md -->
## Veelvoorkomende Issues
- JudoToernooi: landing page knoppen NIET verwijderen bij refactoring
- Herdenkingsportaal: dark mode classes ALTIJD meenemen
```

**Kracht:** Persistent across sessions, automatisch geladen.

---

## Wanneer welke laag?

| Situatie | Minimale laag | Aanbevolen |
|----------|--------------|------------|
| Feature voor het eerst gebouwd | Laag 1 (docs) | Laag 1 + 2 |
| Feature 1x per ongeluk verwijderd | Laag 2 (comment) | Laag 2 + 4 |
| Feature 2x+ per ongeluk verwijderd | Laag 3 (test) | Laag 2 + 3 + 4 |
| Project-breed patroon | Laag 4 (CLAUDE.md) | Laag 4 + 5 |
| Cross-project patroon | Laag 5 (memory) | Laag 4 + 5 |

## Vuistregel

> **Hoe vaker een fout voorkomt, hoe meer lagen je toepast.**
> Begin met laag 1-2 (goedkoop), escaleer naar 3-5 als het probleem terugkeert.

---

*Laatste update: 21 februari 2026*
