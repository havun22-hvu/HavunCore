# Implementatieplan VP-11 t/m VP-17

> **Status:** Plan — wacht op gebruikersgoedkeuring voordat Fase 3 (code) start
> **Datum:** 16 april 2026
> **Bron:** Gemini AI review v3.0 (9,8/10) + Claude contra-review (~8,5/10)
> **Verbeterplan:** `docs/audit/verbeterplan-q2-2026.md`

## Scope

Zeven nieuwe verbeterpunten gegroepeerd per inspanning. Volgorde: **van kort naar lang**, met afhankelijkheden waar relevant. Geen prioriteit-onderscheid (alle moeten gebeuren), maar de eerste paar zijn quick-wins die meteen waarde leveren.

| Volgorde | VP | Inspanning | Deadline |
|----------|----|-----------|----------|
| 1 | VP-17 (runbook) | 30 min | al deels klaar (regel 6 actief) |
| 2 | VP-12 (doc-sync /end) | 1 uur | 31-05-2026 |
| 3 | VP-13 (kwartaal-droogtest schedule) | 1 uur | 31-07-2026 |
| 4 | VP-15 (deploy-bevoegdheden) | 2 uur | 31-05-2026 |
| 5 | VP-16 (mutation testing) | 4 uur | 30-09-2026 |
| 6 | VP-14 (CONTRACTS.md × 9) | 8 uur (verspreid) | 30-06-2026 |
| 7 | VP-11 (Alpine CSP migratie) | 8-12 uur | 30-06-2026 |

**Totaal geschat:** 25-30 uur ontwikkeltijd, te verdelen over meerdere sessies.

---

## VP-17 — AI Test-Repair Anti-Pattern Runbook

**Status:** Regel 6 al actief in `CLAUDE.md`. Memory entry bestaat (`feedback_no_test_repair.md`). Wat ontbreekt: voorbeeldsituaties als referentie.

### Aanpak
Eén runbook met 3 worked examples:
1. Test verwacht waarde X, code geeft Y → analyse-stappen om te bepalen of test of code fout is
2. Refactor maakt assertion verouderd → wanneer mag assertion mee-refactored
3. Externe afhankelijkheid wijzigt API → mock aanpassen vs business-rule herverificatie

### Files
- **Nieuw:** `docs/kb/runbooks/test-repair-anti-pattern.md`

### Klaar-criteria
Ander dev/AI kan runbook lezen en in 30s beslissen "fix code" vs "vraag user".

---

## VP-12 — Documentatie-Synchronisatie Check bij /end

**Probleem:** Tests die geschreven worden tijdens een sessie kunnen de business rules in MD-docs uitvoeren — maar als de docs niet ge-update worden ontstaat drift. Een doc kan zeggen "minimaal 18 jaar" terwijl de test nu 16 accepteert na een half-doorgevoerde change.

### Aanpak
Update `.claude/commands/end.md` met expliciete stap:

> **Stap N: Doc-Sync Check**
>
> Voor elke nieuwe of gewijzigde test in deze sessie:
> 1. Lees de assertion(s)
> 2. Zoek de bijbehorende business rule in `docs/kb/` of `CLAUDE.md`
> 3. Klopt de assertion 1-op-1 met wat de doc zegt?
> 4. Bij verschil → **vraag gebruiker** wat correct is, update de losende doc/test

Geen automatisering nodig — handmatige check is voldoende voor nu (drift is vaak subtiel; een script vangt het toch niet zonder false positives).

### Files
- **Aanpassen:** `.claude/commands/end.md` (één sectie toevoegen)

### Klaar-criteria
Bij elke `/end` wordt de check uitgevoerd; user herkent het als standaard.

---

## VP-13 — Periodieke Noodprotocol-Droogtest

**Status:** Eerste droogtest succesvol op 16-04-2026 met Thiemo. Mawin als 2e contact aangewezen. Wat ontbreekt: herhalingsschema zodat kennis niet verwatert.

### Aanpak
1. Schema vastleggen: **één droogtest per kalenderkwartaal**, gepland op een zondagmiddag (laagste werkdruk)
2. Roterende contactpersoon: Q3 Thiemo, Q4 Mawin, Q1 Thiemo, Q2 Mawin
3. Reminder: cron job in HavunCore die 7 dagen voor de droogtest een e-mail stuurt naar henkvu@gmail.com
4. Droogtest-checklist (vereenvoudigd protocol uit emergency-runbook.md):
   - Inloggen via SSH
   - `/start` in claude-code
   - `/rc` (remote control link genereren)
   - WhatsApp link naar henkvu (op afstand)
   - Henk bevestigt "ik kan meelezen"
   - Eindigt: 5 minuten oefening, geen echte fix nodig

### Files
- **Nieuw:** `docs/kb/runbooks/droogtest-schema-2026-2027.md` (data + roster)
- **Nieuw:** `app/Console/Commands/SendDroogtestReminder.php` (Laravel command)
- **Nieuw:** `app/Mail/DroogtestReminderMail.php`
- **Aanpassen:** `app/Console/Kernel.php` (cron schedule)

### Klaar-criteria
Eerste reminder werkt: 7 dagen voor 1e geplande Q3-droogtest komt e-mail binnen.

---

## VP-15 — Formele Deploy-Bevoegdheden tijdens Afwezigheid

**Context (verduidelijkt door gebruiker):**
- Thiemo + Mawin hebben SSH-access tot productieservers
- Werken in terminal CLI, raadplegen Claude CLI voor stappen
- **Voornaamste taak:** remote control starten zodat eigenaar mee kan kijken
- Eventuele actie gebeurt onder begeleiding eigenaar (live of via verzoek)

### Aanpak
Eén formeel document met de "wie-mag-wat-wanneer" matrix + concrete CLI-procedures:

```
Scenario A: Eigenaar bereikbaar binnen 1 uur
  → Noodcontact start `/rc` + WhatsApp link → eigenaar neemt over

Scenario B: Eigenaar onbereikbaar >1 uur, productie deels down
  → Noodcontact mag uitvoeren onder Claude CLI begeleiding:
    - php artisan down (maintenance mode aan/uit)
    - git pull (alleen op productie als hotfix-PR door eigenaar al gemerged)
    - php artisan migrate --force ❌ NIET zonder eigenaar
    - service php-fpm restart, nginx reload (mag wel)

Scenario C: Eigenaar onbereikbaar >24 uur, kritieke security bug
  → Noodcontact stuurt e-mail naar henkvu@gmail.com + WhatsApp + plaatst
    site in maintenance mode. WACHT op eigenaar voor inhoudelijke fix.
```

Plus: een toegangscontrole document (geen credentials, alleen WIE waar bij kan).

### Files
- **Nieuw:** `docs/kb/runbooks/deploy-bevoegdheden-afwezigheid.md`
- **Aanpassen:** `docs/kb/runbooks/noodcontactpersoon-protocol.md` (verwijzing toevoegen)
- **Nieuw:** `docs/kb/runbooks/wat-mag-noodcontact.md` (cli-cheat sheet voor Thiemo/Mawin — drukken-en-draaien)

### Klaar-criteria
Thiemo en Mawin kunnen het `wat-mag-noodcontact.md` openen en weten precies welk commando wel/niet mag in welk scenario, zonder Claude te raadplegen. Voor twijfelgevallen verwijst het document expliciet naar "vraag eerst aan Claude CLI" of "bel/app eigenaar".

---

## VP-16 — Kwartaalse Mutation Testing (Infection PHP)

**Doel:** Verifiëren dat tests werkelijk regressies vangen, niet alleen "statements raken". Een test die nooit faalt bij code-wijziging is waardeloos — mutation testing maakt dat zichtbaar.

### Onderzoek/aanpak
1. **Tool:** [Infection PHP](https://infection.github.io/) — de standaard voor PHP mutation testing
2. **Scope eerste POC:** Herdenkingsportaal payment-pijler (PaymentController, PaymentWebhookController, RealMollieService, MockMollieService, InvoiceService, PaymentTransaction model)
3. **Score-doel:** **MSI (Mutation Score Indicator) ≥ 75%** voor de payment-pijler
4. **Frequentie:** kwartaalse run via GitHub Action (handmatig + cron) → rapport in `docs/audit/mutation-YYYY-MM.md`

### Te onderzoeken voor de eigenaar
- Welke 3 mutators zijn voor PHP-payment-code het meest informatief? (PublicVisibility? Decrement? Throw?)
- Hoe lang duurt een Infection-run op HP's payment-pijler in CI?
- Welke baseline MSI is realistisch voor de eerste meting (raming: 60-70% — gaten zichtbaar)

### Voorstel
Plaats deze 3 vragen aan Gemini AI ter validatie van de aanpak voordat we Infection installeren — gebruiker geeft feedback wenselijk.

### Files
- **Nieuw:** `infection.json5` (in Herdenkingsportaal repo) — configuratie
- **Nieuw:** `composer.json` aanvullen met `infection/infection` dev-dep
- **Nieuw:** `.github/workflows/mutation-test.yml` (kwartaalse cron + handmatige trigger)
- **Nieuw:** `docs/kb/runbooks/mutation-testing-infection.md` (hoe lokaal draaien)
- **Nieuw:** `docs/audit/mutation-2026-Q3.md` (eerste rapport — leeg template)

### Klaar-criteria
- `composer install` op fresh HP-clone heeft Infection beschikbaar
- `vendor/bin/infection` op payment-pijler levert MSI
- Eerste rapport gegenereerd, hardop "% gaten" zichtbaar voor eigenaar

---

## VP-14 — CONTRACTS.md per App

**Doel:** Vastleggen welke business rules per app **nooit** mogen breken, los van implementatie. Een refactor die contract X breekt is per definitie onacceptabel — los van of de tests groen blijven.

### Aanpak (AI-studie + voorstel per app)
Per app een sessie waarin Claude:
1. Leest `CLAUDE.md` van de app
2. Leest de belangrijkste KB-runbooks van de app
3. Leest de top 10 controllers + models qua complexiteit/grootte
4. **Stelt een concept-CONTRACTS.md voor** (5-10 regels)
5. Eigenaar reviewt + accepteert/wijzigt

**Voorbeeld concept-contract regels:**

**Herdenkingsportaal CONTRACTS.md (concept — ter discussie):**
```
1. Een memorial in 'published' state mag NOOIT terugvallen naar 'draft'
   zonder expliciete admin-actie + audit log entry.
2. Een betaling van >€0,01 via Mollie MOET een PaymentTransaction record
   met mollie_payment_id én een Invoice record produceren.
3. Een memorial 'public' privacy_level mag voor anonieme bezoekers
   alleen tonen: naam, geboortedatum, sterfdatum, monumentafbeelding —
   NOOIT email, telefoonnummer, adres of betaalgegevens.
4. Een Mollie webhook mag NOOIT een transaction-status downgraden
   (paid → pending). Alleen upgraden of falen-flag zetten.
5. Een Arweave upload moet bewijs hebben (transaction_id) voordat de
   memorial 'published' wordt — geen eerste-publiceren-dan-uploaden.
... (5 meer per app)
```

**Schaal:** ~9 apps × 30 min ontwerp + 30 min review = ~9 uur, niet in één sessie.

### Files
- **Nieuw per app:** `<project>/CONTRACTS.md` (op project-root, naast CLAUDE.md)
- **Nieuw centraal:** `docs/kb/patterns/contracts-md-template.md` (HavunCore template)
- **Aanpassen:** `CLAUDE.md` per app: "Bij elke wijziging eerst CONTRACTS.md raadplegen — contracten zijn onveranderlijk zonder eigenaar-akkoord"

### Klaar-criteria
- 9 CONTRACTS.md bestanden in productie-repos
- Test in HavunCore (`integrity:check`) controleert dat CONTRACTS.md bestaat per project
- Cross-link in elke project-CLAUDE.md naar de eigen CONTRACTS.md

### Volgorde van uitrol
1. Template + HP CONTRACTS.md (publieke betalingen, hoogste risico)
2. JT (publiek + actief tijdens toernooien)
3. HavunAdmin (financiële integriteit)
4. Studieplanner + JSB (mobile, eindgebruiker)
5. SafeHavun + Infosyst + HavunVet (lager publiek risico)
6. HavunCore zelf (orchestrator)

---

## VP-11 — Alpine.js CSP Migratie (Herdenkingsportaal)

**Status:** `@alpinejs/csp` package al geïnstalleerd in HP main branch (zie eerdere git-status), import in `resources/js/app.js` al gewijzigd. Wat ontbreekt: alle 149 inline directives migreren naar `Alpine.data()` componenten.

### Risico
Frontend kan deels stuk gaan (dropdowns, navigatie, modals) als migratie incompleet is. Daarom GEFASEERD per directive-categorie + per view manuele rooktest.

### Aanpak
1. Inventariseren: 149 directives groeperen per patroon
   - Toggle-pattern: `x-data="{ open: false }"` + `@click="open = !open"`
   - Form-show pattern: `x-data="{ show: true }"`
   - Multi-state pattern: `x-data="{ open: false, colorOpen: false }"`
   - Custom function-pattern (al CSP-compatibel)
2. Per pattern één `Alpine.data()` registratie schrijven in `resources/js/app.js`
3. Per view directive vervangen
4. Per view rooktest: open in browser, klik elke dropdown/modal/toggle
5. Aan einde: Mozilla Observatory test → groen op CSP

### Files
- **Aanpassen:** `resources/js/app.js` — Alpine.data() registraties (één per pattern)
- **Aanpassen:** ~30 view files in `resources/views/` waar directives staan
- **Aanpassen:** `app/Http/Middleware/SecurityHeaders.php` — verwijder `'unsafe-eval'` uit script-src (regel 73)
- **Aanpassen:** `tests/Feature/MiddlewareTest.php` — verwijder `markTestSkipped()` (regel ~108)

### Klaar-criteria
- Aan einde: `assertStringNotContainsString("'unsafe-eval'", $csp)` slaagt
- Alle dropdowns/modals/toggles werken in browser-rooktest
- Mozilla Observatory geeft 100/100 voor HP

### Volgorde
1. Pattern-inventaris + Alpine.data() registraties (1u)
2. Conversie van eenvoudige toggle-patterns (~50 stuks, 2u)
3. Conversie van multi-state + form patterns (~50 stuks, 3u)
4. Conversie van complexe/aangepaste patterns (~50 stuks, 3u)
5. Browser rooktest + CSP test ontskippen (1u)

---

## Onderlinge Afhankelijkheden

```
VP-17 ─── (geen afhankelijkheid, eerst doen)
VP-12 ─── (geen afhankelijkheid)
VP-13 ─── (geen afhankelijkheid)
VP-15 ─── (geen afhankelijkheid)

VP-14 ─── (geen technische afhankelijkheid, kan parallel)
VP-16 ─── (parallel, maar Gemini-validatie eerst)
VP-11 ─── (kan parallel; package al geïnstalleerd)
```

Geen blocking dependencies — alles kan onafhankelijk uitgerold.

---

## Communicatie & Akkoord

Elke fase wordt afgesloten met:
- **Commit** met VP-nummer in message
- **Update** van VP-status in `verbeterplan-q2-2026.md`
- **Versie 3.1** van `werkwijze-beoordeling-derden.md` zodra alle 7 VPs DONE

Eigenaar geeft **expliciet akkoord** per VP voordat de volgende start. Bij twijfel: terug naar Fase 1 (docs verfijnen) volgens /mpc.

---

## Wat dit plan NIET dekt

- **Versie 4.0 van het audit-dossier:** komt automatisch wanneer alle 7 VPs gemerged zijn
- **Externe (menselijke) audit:** dossier is daar pas klaar voor na VP-14 + VP-15 (de twee blinde vlekken die Claude flagde)
- **Onderhoud:** dit plan beschrijft de eerste implementatie. De terugkerende activiteiten (kwartaalse mutation, droogtest, doc-sync) lopen daarna door.
