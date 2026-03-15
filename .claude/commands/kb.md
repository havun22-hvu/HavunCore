# Kennisbank Zoeken

> Zoek in de centrale HavunCore kennisbank naar informatie over de vorige vraag/opmerking.

## Instructies

1. **Update de index eerst** (zodat recente wijzigingen meegenomen worden):

```bash
cd D:\GitHub\HavunCore && php artisan docs:index all
```

2. Lees de **vorige vraag of opmerking** van de gebruiker in dit gesprek
3. Bepaal de zoekterm(en) uit die vraag
4. Zoek in de kennisbank:

```bash
cd D:\GitHub\HavunCore && php artisan docs:search "ZOEKTERM" --limit=5
```

5. Geef een samenvatting van wat je vond:
   - Welke documenten relevant zijn
   - Korte preview van de inhoud
   - Of er inconsistenties zijn

6. Beantwoord de oorspronkelijke vraag met de gevonden informatie

## Voorbeeld

**Gebruiker:** Hoe werkt de Mollie integratie?
**Gebruiker:** /kb

**Claude:**
- Updatet de index (`docs:index all`)
- Zoekt op "mollie" in kennisbank
- Vindt relevante docs
- Beantwoordt de vraag met die info
