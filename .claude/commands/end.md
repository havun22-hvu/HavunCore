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

## 5. Linter-Gate: Test Verificatie (VERPLICHT bij code wijzigingen)

Als er code is gewijzigd in deze sessie, draai de tests en analyseer het resultaat:

```bash
# Laravel projecten:
php artisan test --log-junit storage/logs/test-results.xml 2>&1

# Expo/React Native projecten:
npm test -- --json --outputFile=test-results.json 2>&1
```

### Analyse (VERPLICHT):
1. **Alle tests groen?** → Door naar stap 6
2. **Tests falen?** → Fix de falende tests VOORDAT je commit
3. **Nieuwe code zonder tests?** → Schrijf minimaal guard tests

### Bij bug fix in deze sessie:
- Is er een regression test geschreven die de bug reproduceert?
- Zo nee: schrijf die NU, voor je commit

### Recent Regressions bijwerken:
Als er een regression is gevonden en gefixt, voeg toe aan `{project}/.claude/recent-regressions.md`:

```markdown
## [DATUM] - [korte beschrijving]
- **Wat:** [wat was kapot]
- **Oorzaak:** [waarom]
- **Fix:** [wat gefixt]
- **Test:** [welke test bewaakt dit nu]
- **Vervalt:** [datum + 7 dagen]
```

Verwijder entries ouder dan 7 dagen uit dit bestand.

### Integrity Check (indien `.integrity.json` bestaat):
```bash
# Valideer dat kritieke elementen nog aanwezig zijn
node scripts/check-integrity.js 2>&1 || php artisan integrity:check 2>&1
```

## 6. Git Commit & Push (KRITIEK - NIETS MAG ACHTERBLIJVEN!)

### Stap A: Commit ALLE code-wijzigingen EERST

```bash
# 1. Check wat er open staat
git status

# 2. Groepeer wijzigingen in logische, atomaire commits
#    - Per feature/fix een aparte commit
#    - Gebruik duidelijke commit messages (feat:/fix:/refactor:)
#    Voorbeeld:
#      git add src/controllers/UserController.php src/views/user.blade.php
#      git commit -m "feat: Add user profile page"

# 3. HERHAAL tot ALLE code-wijzigingen gecommit zijn
```

⚠️ **HARD RULE:** Na deze stap mag `git status` GEEN gewijzigde code-bestanden meer tonen. Alleen docs mogen nog open staan.

### Stap B: Commit docs/handover

```bash
git add .claude/context.md .claude/smallwork.md
git commit -m "docs: Session handover [datum] + [korte beschrijving]"
```

### Stap C: Push ALLES

```bash
git push
```

### Stap D: Verificatie (VERPLICHT)

```bash
# Dit MOET leeg zijn (behalve untracked files die bewust niet gecommit worden)
git status
git diff
```

⚠️ **Als er NOG wijzigingen open staan: NIET doorgaan. Eerst committen!**

## 7. Deploy naar Server (indien van toepassing)

### Bij publieke apps (HP, JT, HA): STAGING EERST!

```bash
ssh root@188.245.159.115

# Stap 1: Deploy naar STAGING
cd [project staging path]
git pull
php artisan migrate
php artisan config:clear && php artisan cache:clear

# Stap 2: Verifieer staging
# Open staging URL, test kritieke features
# Minimaal 1 uur wachten, bij grote wijzigingen 24 uur

# Stap 3: Deploy naar PRODUCTIE (pas na staging-verificatie)
cd [project production path]
git pull
php artisan config:clear && php artisan cache:clear
```

### Bij overige apps of alleen docs-wijzigingen:

```bash
ssh root@188.245.159.115
cd [project path]
git pull
php artisan config:clear && php artisan cache:clear
```

## 8. Branch Cleanup

```bash
git branch --merged | grep -v master | xargs git branch -d
```

## 9. USB / Op reis (HavunCore only)

**Nieuwe werkwijze:** USB bevat alleen credentials (vault) + startscript; geen code sync meer nodig. Code op reis via `git clone`/`git pull`.

- **Vault bijwerken (thuis):** Zorg dat `credentials.vault` op de USB de actuele `.env` en `context.md` per project bevat (handmatig of eigen script).
- **Runbook:** `docs/kb/runbooks/op-reis-workflow.md`

## 10. KOR-Omzetcheck (elk kwartaal)

**Alleen bij kwartaaleinde (maart, juni, september, december):**

Check of de omzet richting de KOR-drempel gaat (€20.000/jaar):

```
⚠️ KOR-drempelcheck:
   Drempel: €20.000/jaar
   Waarschuwing bij: €16.000 (80%)

   → Check Mollie dashboard: https://my.mollie.com
   → Check Stripe dashboard: https://dashboard.stripe.com
   → Tel op: Mollie + Stripe + overige inkomsten

   Status: [onder drempel / waarschuwing / boven drempel]
```

Als omzet > €16.000: meld aan gebruiker dat BTW-overgangsplan nodig is.
Zie: `docs/audit/verbeterplan-q2-2026.md` (VP-10)

## 11. Urenregistratie (VERPLICHT - belastingaangifte)

**Jij vult zelf de uren in.** Geef een zeer beknopt overzicht om de werkzaamheden te onderbouwen.
**GEEN commit details of technische beschrijvingen.** Alleen projectnaam + globaal onderwerp (max 3 woorden).

→ Kopieer naar `HavunCore/urenregistratie-2026.csv` (formaat: `Datum;Uren;Project;Onderdeel`). Projectnamen met hoofdletter: JudoToernooi, Infosyst, HavunClub, etc.

```
[YYYY-MM-DD]:
- [Project]: [globaal onderwerp, max 3 woorden]
- [Project]: [globaal onderwerp, max 3 woorden]
```

Voorbeeld:
```
2026-03-09:
- JudoToernooi: Stripe Connect, AutoFix
- HavunAdmin: StripeService fix
- HavunCore: KB bijwerken
```

## NIET DOEN BIJ AFSLUITEN

❌ Afsluiten zonder MD files te checken
❌ Kennis "in je hoofd houden" - de volgende Claude weet het niet!
❌ Geen handover maken bij openstaande items
❌ Pushen zonder duidelijke commit message
