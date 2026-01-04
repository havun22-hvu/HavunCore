# End Session Command

> **VERPLICHT** bij elke sessie-afsluiting - laat het project netjes achter!

## 1. Review Smallwork.md (EERST!)

Lees `.claude/smallwork.md` en check elke entry:

```
Voor elke fix in smallwork.md:
  ├── Moet dit naar permanente docs?
  │     ├── Feature/functionaliteit → SPEC.md of FEATURES.md
  │     ├── Styling → STYLING.md
  │     ├── Business rule → relevante doc
  │     └── Technisch/eenmalig → blijft in smallwork
  │
  └── Verplaats indien nodig en vink af
```

## 2. MD Bestanden Netjes Achterlaten (KRITIEK!)

### Controleer en update:

```
{project}/CLAUDE.md           ← Zijn er nieuwe regels/restricties?
{project}/.claude/context.md  ← Is er nieuwe project kennis?
{project}/.claude/smallwork.md ← Is alles afgehandeld?
```

### Vraag jezelf:
- [ ] Wat hebben we besproken dat NIET gedocumenteerd is?
- [ ] Zijn er beslissingen genomen die vastgelegd moeten worden?
- [ ] Heeft de gebruiker iets uitgelegd dat opgeslagen moet worden?
- [ ] Zijn er nieuwe patterns/oplossingen die herbruikbaar zijn?

### Waar opslaan?

| Nieuwe kennis | Locatie |
|---------------|---------|
| Project-specifiek | `{project}/.claude/context.md` |
| Herbruikbaar pattern | `HavunCore/docs/kb/patterns/` |
| How-to procedure | `HavunCore/docs/kb/runbooks/` |
| Architectuur beslissing | `HavunCore/docs/kb/decisions/` |

## 3. Maak een Handover voor Volgende Sessie

Voeg toe aan het einde van `{project}/.claude/context.md` of maak `{project}/.claude/handover.md`:

```markdown
## Laatste Sessie: [DATUM]

### Wat is gedaan:
- [Taak 1]
- [Taak 2]

### Openstaande items:
- [ ] [Nog te doen 1]
- [ ] [Nog te doen 2]

### Belangrijke context voor volgende keer:
- [Relevante info die de volgende Claude moet weten]
- [Beslissingen die genomen zijn en waarom]

### Bekende issues/bugs:
- [Issue 1]
```

## 4. Update Doc Intelligence Index (indien beschikbaar)

Als het Doc Intelligence systeem actief is, indexeer de wijzigingen:

```bash
cd D:\GitHub\HavunCore
php artisan docs:index [project]
php artisan docs:detect [project]
```

Dit zorgt ervoor dat:
- Gewijzigde MD files opnieuw geïndexeerd worden
- Nieuwe inconsistenties gedetecteerd worden
- De volgende sessie up-to-date info heeft

## 5. Git Commit & Push

```bash
git add .
git commit -m "docs: Session handover [datum] + [korte beschrijving]"
git push origin master
```

## 6. Deploy naar Server (indien van toepassing)

```bash
ssh root@188.245.159.115
cd [project path]
git pull
php artisan config:clear && php artisan cache:clear
```

## 7. Branch Cleanup

```bash
git branch --merged | grep -v master | xargs git branch -d
```

## 8. USB Stick Bijwerken (HavunCore only)

```powershell
powershell -ExecutionPolicy Bypass -File "D:\GitHub\sync-to-usb.ps1"
```

## 9. Bevestig aan Gebruiker

```
📋 Sessie Samenvatting:
  - [Wat gedaan]
  - [Wat gedaan]

📝 Gedocumenteerd:
  - [Welke MD files bijgewerkt]

⏳ Openstaand:
  - [Nog te doen]

✅ Handover gemaakt voor volgende sessie
✅ Git gepusht
✅ [Server gedeployed / USB bijgewerkt]

Sessie afgerond. Typ 'exit' of Ctrl+D om te sluiten.
```

## NIET DOEN BIJ AFSLUITEN

❌ Afsluiten zonder MD files te checken
❌ Kennis "in je hoofd houden" - de volgende Claude weet het niet!
❌ Geen handover maken bij openstaande items
❌ Pushen zonder duidelijke commit message
