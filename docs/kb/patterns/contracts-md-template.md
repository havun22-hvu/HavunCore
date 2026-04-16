# Pattern: CONTRACTS.md per Project

> **Probleem:** Tests kunnen drift verbergen — ze blijven groen terwijl een refactor stilletjes een business-rule schendt. Een AI die failing tests "fixt" door assertions aan te passen kan zo een hele testsuite waardeloos maken zonder dat iemand het opvalt.
> **Oplossing:** Per app een `CONTRACTS.md` met **5-10 onveranderlijke regels** die nooit mogen breken — los van implementatie, los van tests. Bij elke wijziging moet Claude eerst checken of de wijziging één van deze regels schendt.

## Wanneer toepassen

- Elk project waar foutgevoelig gedrag kritiek is voor klanten/geld/wet
- Verplicht voor projecten met publieke endpoints, betalingen, of gevoelige data
- HavunCore vereist een CONTRACTS.md per project (controle via integrity:check)

## Structuur

```markdown
# CONTRACTS — <ProjectNaam>

> Onveranderlijke regels van dit project. NIEMAND mag deze regels overtreden — ook AI niet.
> Bij elke wijziging eerst raadplegen. Wijzigen mag alleen na schriftelijk akkoord van eigenaar.

## Wat is een contract?

Een contract is een gedragsregel die los staat van de implementatie. De code mag refactoren, de tests mogen wijzigen — maar het externe gedrag dat dit contract beschrijft mag NIET wijzigen.

## Contracten

### C-01: <kort herkenbare titel>

**Regel:** <1-2 zinnen die het gewenste gedrag beschrijven>

**Waarom:** <1 zin reden — wettelijk, financieel, gebruiker-vertrouwen, etc.>

**Bewijs in code:** <welke test(s) bewaken dit; welke files implementeren het>

**Bij twijfel:** STOP, raadpleeg eigenaar.

---

### C-02: ...

(5-10 contracten in totaal)

---

## Wat NIET in CONTRACTS.md hoort

- Implementatie-details ("we gebruiken Eloquent" — kan refactoren)
- UI-keuzes ("kleur is goud" — esthetisch, kan veranderen)
- Performance ("response < 200ms" — gebruik SLO/uptime-doc)
- Codestijl, patterns, conventies (gebruik patterns/-folder)

## Wat WEL in CONTRACTS.md hoort

- Onomkeerbare data-acties ("publicatie kan niet teruggedraaid")
- Geld/financiën ("BTW altijd 21% tenzij anders gemarkeerd")
- Privacy/AVG ("publieke memorial toont nooit e-mailadressen")
- Beveiliging ("webhook valideert altijd via Mollie API")
- Domein-invariants ("max 8 judoka's per poule")
- Wettelijke verplichtingen ("invoice bevat altijd KvK + BTW-nummer")

## Cross-references

- `<project>/CLAUDE.md` — moet linken naar CONTRACTS.md met regel "raadpleeg eerst"
- `docs/kb/runbooks/test-repair-anti-pattern.md` — wat te doen bij conflict tussen test/code/contract
- `docs/audit/verbeterplan-q2-2026.md` — VP-14 (oorsprong)

## Levensduur

Contracts wijzigen alleen als de business het bewust besluit. Een wijziging is een aparte beslissing met:
- Datum + reden in commit-message
- Eigenaar-akkoord (geen AI-autonome wijziging)
- Update van bewakende tests

## Voorbeeld (Herdenkingsportaal)

Zie `D:\GitHub\Herdenkingsportaal\CONTRACTS.md` voor een live invulling.
