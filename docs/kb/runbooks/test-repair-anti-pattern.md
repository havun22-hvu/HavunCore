# Runbook: Wat te doen bij een falende test

> **Wanneer toepassen:** Bij elke `phpunit`/`jest`/`pytest` run waarbij één of meer tests rood worden.
> **Vereisten:** Toegang tot de codebase + de relevante MD-docs in `docs/kb/`.
> **Klaar-criteria:** De juiste partij (code, test, of doc) is gefixt na een bewuste afweging — geen "stille assertion-update".
> **Gerelateerd:** `CLAUDE.md` regel 6 (de 6 onschendbare regels), `feedback_no_test_repair.md` (cross-sessie memory).

## Waarom dit runbook bestaat

Een AI (en mens) onder tijdsdruk neigt naar de snelste fix: assertion aanpassen tot de test groen is. Dat is een **anti-pattern**: het maakt het hele test-vangnet waardeloos. Een test die zich aanpast aan de code in plaats van de code te dwingen aan de business rule te voldoen, dekt niets meer af.

Dit runbook is de procedure die je doorloopt **voor je iets aanpast aan een falende test**.

## De 5 stappen

### Stap 1: Lees de assertion

Wat wordt er precies gecontroleerd?

- Welke verwachte waarde?
- Welke vergelijking (gelijk, in lijst, regex, type)?
- Welke context (welke setup-data, welke user-state)?

Schrijf in 1 zin op: *"Deze test verwacht dat \[X] gebeurt wanneer \[Y]."*

### Stap 2: Lees de productiecode die getest wordt

Welke functie/method/route wordt geraakt? Wat doet die nu echt?

Schrijf in 1 zin op: *"De code doet nu \[Z]."*

### Stap 3: Lees de business rule in een MD-doc

Zoek in deze volgorde:
1. `<project>/CONTRACTS.md` — onveranderlijke contracten (als die bestaat — zie VP-14)
2. `<project>/CLAUDE.md` — projectregels
3. `docs/kb/runbooks/` — procedures
4. `docs/kb/patterns/` — herbruikbare oplossingen
5. `docs/kb/reference/` — specs en normen

Schrijf in 1 zin op: *"De doc zegt dat \[W] de gewenste gedrag is."*

### Stap 4: Vergelijk en classificeer

Nu heb je 3 zinnen. Welk scenario is het?

| Scenario | Test verwacht | Code doet | Doc zegt | → Wat fixen |
|----------|---------------|-----------|----------|-------------|
| **A. Code-bug** | X | Y (afwijkt) | X | **Code** — herstel naar verwachte gedrag |
| **B. Verouderde test** | X | Y | Y (al ge-update) | **Test** — alleen na user-akkoord + verifieer dat doc-update bewust was |
| **C. Verouderde doc** | X | Y | X (zegt nog X) | **STOP** — vraag user welke gedrag correct is |
| **D. Drift** | X | Y | Z (heel anders) | **STOP** — vraag user; mogelijk meerdere wijzigingen door elkaar |

### Stap 5: Actie

- **Scenario A:** fix de code, hertest. Klaar.
- **Scenario B:** vraag user *"De test verwacht X maar de doc beschrijft Y als gewenste gedrag. Mag ik de assertion bijwerken naar Y?"* — pas alleen na **expliciet "ja"** aan.
- **Scenario C/D:** **NIET zelf beslissen**. Vraag user welke gedrag correct is, update de juiste doc + code/test in de juiste volgorde.

## Concrete voorbeelden

### Voorbeeld 1 — Code-bug (Scenario A)

**Test (Herdenkingsportaal):**
```php
public function test_memorial_published_cannot_revert_to_draft(): void {
    $memorial = Memorial::factory()->published()->create();
    $memorial->update(['memorial_state' => 'draft']);
    $this->assertSame('published', $memorial->fresh()->memorial_state);
}
```

**Faalt:** assertion ziet `'draft'` i.p.v. `'published'`.

**Diagnose:**
1. *Test verwacht:* memorial in 'published' state mag niet terug naar 'draft'.
2. *Code doet:* update past de waarde gewoon door, geen guard.
3. *Doc (CONTRACTS.md HP):* "Een memorial in 'published' state mag NOOIT terugvallen naar 'draft' zonder admin-actie + audit log."

**Match:** Test ↔ doc. Code is buggy.

**Actie:** Fix de Memorial model (guard in `setMemorialStateAttribute()` of policy). NIET de assertion aanpassen.

---

### Voorbeeld 2 — Verouderde test (Scenario B)

**Test (HavunAdmin):**
```php
public function test_invoice_total_includes_btw_at_21_percent(): void {
    $invoice = Invoice::factory()->create(['amount_excl_vat' => 100.00]);
    $this->assertEquals(121.00, $invoice->amount_incl_vat);
}
```

**Faalt:** assertion ziet `109.00`.

**Diagnose:**
1. *Test verwacht:* 21% BTW.
2. *Code doet:* berekent 9% BTW (sinds een wijziging vandaag).
3. *Doc (`docs/kb/reference/btw-tarieven.md`):* "Per 01-04-2026 is BTW voor digitale herdenkingsdiensten verlaagd naar 9% (Belastingdienst-besluit BLKB2026/123)."

**Match:** Code ↔ doc. Test is verouderd.

**Actie:** Vraag user *"De BTW-doc is recent geüpdatet naar 9% (besluit BLKB2026/123). Mag ik de assertion bijwerken van 121.00 naar 109.00?"* Pas alleen aan na "ja".

---

### Voorbeeld 3 — Verouderde doc (Scenario C — STOP)

**Test (JudoToernooi):**
```php
public function test_poule_max_8_judokas(): void {
    $poule = Poule::factory()->create();
    Judoka::factory()->count(9)->create(['poule_id' => $poule->id]);
    $this->assertCount(8, $poule->fresh()->judokas);
}
```

**Faalt:** assertion ziet 9 judoka's in poule.

**Diagnose:**
1. *Test verwacht:* maximaal 8 judoka's per poule.
2. *Code doet:* poule accepteert 9+ judoka's, geen guard.
3. *Doc (`docs/kb/runbooks/poule-indeling.md`):* "Een poule bevat maximaal 8 judoka's voor optimale tijdsduur." Maar in `CLAUDE.md` van JT staat: "Poules van 6-12 judoka's toegestaan, afhankelijk van toernooi-config."

**Match:** Tegenstrijdige docs. Code volgt geen van beide.

**Actie:** **STOP — fix niets eigenstandig.** Vraag user:
> "De docs zijn tegenstrijdig: poule-indeling.md zegt max 8, CLAUDE.md zegt 6-12. De code accepteert 9. Welke gedrag is correct? Daarna kunnen we de docs harmoniseren en de juiste assertion + guard schrijven."

## Wanneer je deze procedure mag overslaan

| Situatie | Mag je test direct aanpassen? |
|----------|-------------------------------|
| Test die jij **net** zelf schreef in deze sessie en nog niet gecommit is | **Ja** — je weet dat dit bedoeld was als experiment |
| Test-helper/setup-fout (bv. factory ontbreekt veld) | **Ja** — geen business assertion, alleen plumbing |
| Strict-types/typo in PHPUnit-syntaxis (bv. `assertEquals` vs `assertSame`) | **Ja** — geen business-betekenis verandert |
| Assertion controleert **echt iets** over gedrag/data/visibility | **Nee** — volg de 5 stappen |

## Anti-patterns om te vermijden

❌ "De test faalt sinds de refactor — laat me de verwachte waarde bijwerken."
✅ "De test faalt sinds de refactor — was die refactor de bedoeling? Wat zegt de doc?"

❌ "Ik voeg gewoon `[200, 302, 404, 500]` toe aan de assertContains zodat hij niet meer faalt."
✅ "Welke status is correct in dit scenario? Als beide legitiem zijn, splits in 2 tests met expliciete setup."

❌ "De assertion verwacht 'paid', code geeft 'PAID' — ik maak `strtoupper()` in de assertion."
✅ "Is de status hoofdletter-gevoelig? Wat zegt de PaymentTransaction-spec? Casing-inconsistentie wijst meestal op een diepere bug."

## Cross-references

- `CLAUDE.md` — regel 6 (de onschendbare regel die dit runbook formaliseert)
- `docs/audit/verbeterplan-q2-2026.md` — VP-17 (oorsprong)
- `docs/audit/werkwijze-beoordeling-derden.md` — sectie 12 (risico-erkenning)
- `docs/kb/patterns/regression-guard-tests.md` — hoe tests OP TE bouwen die niet drift gevoelig zijn
