# Kennisbank Zoeken

> Zoek in de centrale HavunCore kennisbank naar informatie over de vorige vraag/opmerking.

## Instructies

1. Lees de **vorige vraag of opmerking** van de gebruiker in dit gesprek
2. Bepaal de zoekterm(en) uit die vraag
3. Zoek in de kennisbank:

```bash
cd D:/GitHub/HavunCore && php artisan docs:search "ZOEKTERM" --limit=5
```

4. Geef een samenvatting van wat je vond:
   - Welke documenten relevant zijn
   - Korte preview van de inhoud
   - Of er inconsistenties zijn

5. Beantwoord de oorspronkelijke vraag met de gevonden informatie

## Voorbeeld

**Gebruiker:** Hoe werkt de Mollie integratie?
**Gebruiker:** /kb

**Claude:**
- Zoekt op "mollie" in kennisbank
- Vindt relevante docs
- Beantwoordt de vraag met die info
