# Start Session Command

> **VERPLICHT** bij elke nieuwe Claude sessie

## Stap 0: Sync lokale code + AutoFix detectie (VERPLICHT)

AutoFix kan code wijzigen op de server en automatisch pushen.
Pull altijd eerst de laatste wijzigingen voordat je begint:

```bash
cd [project directory] && git pull
```

Als er merge conflicts zijn: meld aan gebruiker, NIET zelf oplossen.

### AutoFix commits detecteren

Na de pull, check of er AutoFix commits zijn binnengekomen:

```bash
git log --oneline --since="3 days ago" --grep="autofix("
```

Als er AutoFix commits gevonden worden, toon aan de gebruiker:

```
🔧 AutoFix commits gedetecteerd sinds laatste sessie:

  - autofix(BlokController): Added null check for $poule->judokas (#42)
  - autofix(PouleService): Fixed undefined variable in scoring (#43)

Deze bestanden zijn automatisch gefixt op de server.
Zal ik de KB-secties voor deze bestanden markeren voor review?
```

**Bij "ja":** Lees de gewijzigde bestanden, check of de fixes consistent zijn met de KB docs, en meld inconsistenties.
**Bij "nee":** Ga verder met de sessie.

## Stap 0b: Dependency Security Audit (VERPLICHT)

Na de git pull, draai een security audit op dependencies:

```bash
# PHP projecten:
composer audit 2>/dev/null && echo "✓ Geen bekende PHP kwetsbaarheden" || echo "⚠️ PHP kwetsbaarheden gevonden — toon details aan gebruiker!"

# Node.js projecten (indien package.json aanwezig):
npm audit --omit=dev 2>/dev/null && echo "✓ NPM packages veilig" || echo "⚠️ NPM kwetsbaarheden gevonden!"

# Verouderde packages (maandelijks, of bij /start als >30 dagen sinds laatste check):
composer outdated --direct 2>/dev/null | head -20
```

Als er **kritieke kwetsbaarheden** zijn:
```
🔴 SECURITY: Kritieke kwetsbaarheden gevonden!

  - [package] [versie] → [CVE details]

⚠️ Dit moet EERST opgelost worden voordat we verder gaan.
Wil je de kwetsbaarheden nu oplossen?
```

Bij **lage/medium** kwetsbaarheden: melden, maar sessie mag doorgaan.

## Stap 1: Lees de project documentatie (VERPLICHT)

Lees deze bestanden in volgorde en bevestig aan de gebruiker:

```
1. CLAUDE.md                    ← Project regels en context
2. .claude/context.md           ← Project-specifieke details
3. .claude/rules.md             ← Security regels (indien aanwezig)
```

## Stap 2: Kennisbank (KB-first, NIET alles laden)

**NIET** de volledige werkwijze-doc laden. Gebruik de KB on-demand:

```bash
# Zoek ALTIJD in de KB voordat je code leest of schrijft:
cd D:\GitHub\HavunCore && php artisan docs:search "zoekterm"

# Gebruik --type voor gerichte resultaten:
php artisan docs:search "mollie betaling" --type=service    # alleen services
php artisan docs:search "login auth" --type=controller       # alleen controllers
php artisan docs:search "memorial lifecycle" --type=docs     # alleen MD docs
php artisan docs:search "poule indeling" --type=model        # alleen models
```

**Na elke KB search:** vermeld de bron → "Volgens [bestand]: [citaat]"
**Geen resultaat?** Meld: "KB bevat geen info over [X]. Documenteren?"

## VERPLICHT: Havun Kwaliteitsnormen (enterprise)

Bij ELKE code wijziging gelden deze normen uit `docs/kb/reference/havun-quality-standards.md`:

- **Coverage >80%** voor nieuwe code (enterprise niveau)
- **Form Requests** voor ALLE user input
- **Rate limiting** op API endpoints, login, webhooks
- **Custom exceptions** bij externe calls (geen generieke \Exception)
- **Circuit breaker** bij nieuwe externe diensten
- **Policies** voor autorisatie
- **Audit log** voor kritieke acties
- **CSRF + Security headers** standaard actief
- **CSP nonce** op ALLE nieuwe inline `<script>` tags (`<script @nonce>`)
- **Docs-first** — plan in MD voor code

**Lees vóór elke feature/refactor:**
```bash
cd D:\GitHub\HavunCore && php artisan docs:search "havun quality standards"
# Of direct:
cat D:\GitHub\HavunCore\docs\kb\reference\havun-quality-standards.md
```

**De 5 Onschendbare Regels:**
1. NOOIT code schrijven zonder KB + kwaliteitsnormen te raadplegen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor én na wijzigingen (coverage >80%)
5. ALTIJD toestemming vragen bij grote wijzigingen

## Stap 3: Check Doc Intelligence issues

```bash
cd D:\GitHub\HavunCore
php artisan docs:issues [huidig project]
```

> **Let op:** project is een positional argument, niet een --flag.
> Voorbeeld: `php artisan docs:issues havunclub`

Als er openstaande issues zijn, toon ze aan de gebruiker:

```
⚠️ Documentatie issues gevonden:

🔴 [HIGH] Inconsistent: Prijs verschilt tussen SPEC.md en PRICING.md
   → Welke is correct?

🟡 [MED] Duplicate: Mollie setup staat in 2 bestanden
   → Consolideer naar één locatie?

Wil je deze eerst oplossen of later?
```

## Na Stap 1–3: Korte bevestiging

Geef een KORTE bevestiging:

```
✓ MD files gelezen:
  - CLAUDE.md (X regels)
  - context.md (X regels)
  - claude-werkwijze.md (werkwijze + docs-first + PKM)

📋 Dit project: [korte beschrijving]
⚠️ Verboden: [belangrijkste restricties]
📄 DOCS-FIRST: Ik schrijf alleen code zoals het in de docs staat.
📊 Doc issues: [X open issues / geen issues]

Klaar om te beginnen. Wat wil je doen?
```

## Stap 4: ONTHOUD deze principes

### Bij ELKE vraag:
1. Is dit groot (feature/styling/tekst) of klein (bug/typo)?
2. **GROOT** → `docs:search` → Meld wat er staat → Wacht op bevestiging → Update docs → Code
3. **KLEIN** → Log in `.claude/smallwork.md` → Fix → Klaar

### Bij twijfel: zoek in KB
```bash
cd D:\GitHub\HavunCore && php artisan docs:search "onderwerp"
```

### NIET DOEN
- Direct code schrijven zonder docs/KB te checken
- Grote MD docs volledig laden (gebruik KB search)
- Code schrijven terwijl docs inconsistent zijn
