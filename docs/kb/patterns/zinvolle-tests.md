---
title: Pattern: Zinvolle Tests
type: pattern
scope: havuncore
last_check: 2026-04-22
---

# Pattern: Zinvolle Tests

> **Status:** Accepted — 20-04-2026
> **Context:** Henk heeft expliciet gemaakt dat tests alleen zinvol, blijvend en duurzaam mogen zijn. Coverage-padding is verboden: het haalt het coverage-% omhoog zonder kwaliteit toe te voegen, en wordt onderhoudsschuld bij elke refactor.

## Kernregel

Een test mag alleen bestaan als hij **minimaal één** van deze vier dingen doet:

1. **Contract** — test wat externe consumers zien (API-response shape, validatie-regels, error-messages die aan users getoond worden, HTTP-status codes bij fouten)
2. **Invariant** — test een regel die ALTIJD moet gelden (rate-limit werkt, optimistic locking detecteert conflict, security-header wordt gezet, session-cookie is `secure`, wachtwoord wordt gehasht voor opslag)
3. **Bug-regressie** — test dat een historisch-opgetreden bug niet terugkeert (gekoppeld aan incident-datum of issue-nummer in de naam)
4. **Domein-regel** — test business-logica die niet uit de code zelf is af te lezen (freemium blokkeert print-routes, `local-authority`-tabellen winnen bij sync-conflict, leeftijdscategorieën zijn hárde grenzen, kruisfinale-regels)

Als een test GEEN van deze vier doet → **niet schrijven** (of schrappen).

## Beslisboom: test schrijven, ja of nee?

```
Vraag 1: Welke specifieke bug vangt deze test die een code-reviewer zelf zou missen?
   → "Niets" → NIET schrijven
   → "Bug X" → ga verder naar Vraag 2

Vraag 2: Blijft deze test correct na 6 maanden realistische refactors
          (variabel hernoemen, extract method, split class)?
   → "Nee, die breekt bij elke refactor" → herformuleer richting public contract,
      of laat de test weg
   → "Ja, de test asserteert op gedrag, niet structuur" → ga verder naar Vraag 3

Vraag 3: Is het getest-gedrag al elders gedekt (hogere-niveau Feature-test,
          type-systeem, static analysis)?
   → "Ja" → NIET schrijven (dubbel werk)
   → "Nee" → SCHRIJVEN
```

## Wat WEL testen — per categorie

### Models

**Wel:**
- Custom static methods met DB-interactie die een regel afdwingen — `MagicLinkToken::generate()` (ruimt oude tokens op, TTL 15 min), `TvKoppeling::generateCode()` (vermijdt actieve codes), `SyncConflict::winnerFor()` (local-authority lijst)
- Query scopes mét business-betekenis — `unresolved()`, `forProject()`, `slowerThan()`. Dekken van *één* scope volstaat; dek niet 3 varianten van hetzelfde where-pattern.
- Mutator/accessor die transformeert — `Vrijwilliger::getWhatsAppUrl()` (06→+31 conversie, urlencode)
- Model events met side-effects — `ClubUitnodiging::creating` auto-token, `setWachtwoord()` (hash + geregistreerd_op)
- Hidden fields die niet in output mogen lekken — `wachtwoord_hash` in `$hidden` (test serialisatie)

**Niet:**
- `isX()`/`isY()` booleans die letterlijk `$this->x === 'value'` returnen — dat is zelf-documenterend
- Relationship-type assertions (`assertInstanceOf(Toernooi::class, $blok->toernooi)`) — de `belongsTo(Toernooi::class)` staat ERBOVEN in dezelfde file
- Fillable/cast roundtrips voor primitieve types — Laravel dekt dit framework-niveau
- Getters die 1-op-1 een property teruggeven

### Controllers

**Wel:**
- **Authorization** — anonymous krijgt 401/redirect, non-admin krijgt 403, ownership-check (kan niet andermans resource muteren)
- **Validation contract** — required-velden, format-regels die users zien in error-messages, unique-constraints met self-exclusie (user mag eigen email behouden bij update)
- **State-mutation** — POST/PUT/DELETE persisteren de juiste velden en triggeren side-effects (email versturen, cache flush)
- **Query-filtering** — `?status=X` filter werkt, `?unread=1` filter werkt; één per filter volstaat
- **Error-paden** — 404 voor unknown id met goed foutenbericht, 409 voor optimistic locking conflict

**Niet:**
- Happy-path zonder assertions op concrete state (`->assertOk()` zonder te checken wat er is gebeurd)
- View-naam assertions — fragiel, verandert bij rename zonder dat contract breekt. Assert liever op `viewData()` of rendered content die users zien
- HTTP-method verificatie — dat staat in de route-file
- Pagination-defaults (elke Laravel-paginate werkt `?page=1`)

### Middleware

**Wel:**
- **Branch decisions** — wel/niet `next()` aanroepen onder welke condities (free-tier blokkeert print, sessie ontbreekt → 403, disabled → passthrough)
- **Side-effects op response** — headers die gezet MOETEN worden (CSP, HSTS, X-Frame-Options), headers die gestript MOETEN worden (X-Powered-By, Server)
- **Nooit-crashen gedrag** — catch-all error-handling die request niet laat sneuvelen (observability mag failen zonder user-impact)
- **Config-gevoelig gedrag** — HSTS alleen in prod+https, CSP niet in local

**Niet:**
- Middleware-ketens testen (framework-gebied)
- Kopieer-pasta assertion op elke header-naam los (groepeer per request)

### Services

**Wel:**
- **Algorithmes** — percentiel-berekening, poule-verdeling, bracket-seeding, schedule-planning; input → output over representatieve cases (happy + edge: 1 judoka, exact aantal, overlopende categorieën)
- **Integratie-contracten met externe services** — `Http::fake` met reële response-shapes, failure-modes (timeout, 500, invalid JSON), retry-gedrag, circuit-breaker-gedrag
- **Transactie-grenzen** — DB-rollback bij fout, idempotency keys, webhook-dedup (duplicate call = dezelfde uitkomst)
- **Fallbacks** — wat gebeurt er als circuit open is, als upstream time-out'd, als config ontbreekt

**Niet:**
- Private method unit-tests via reflection (tenzij trait-helper zoals `HandlesWedstrijdConflict` waar het public-contract van de trait is)
- Triviale `->save()` / `->update()` wrappers

### FormRequests

**Wel:**
- **User-facing error-messages** — Nederlandse teksten die op het formulier komen ("De clubnaam is verplicht")
- **Cross-field regels** — `inschrijving_deadline ≤ datum`, `eliminatie_type` in-enum, `verdeling_prioriteiten.*` alleen toegestane waarden
- **Type-preprocessing** — `prepareForValidation` JSON-decode, trim, normalize
- **Min/max-constraints** met business-betekenis — `verwacht_aantal_judokas: 10-2000` (geen toernooi bestaat buiten deze range)

**Niet:**
- Framework-rules (`required` zelf) — Laravel is framework-getest
- Elke regel afzonderlijk — groepeer: "empty input faalt op required velden"

### Events (ShouldBroadcast/ShouldQueue)

**Wel:**
- **Broadcast-kanaal-naam** — fragiel API richting frontend, breekt stil. Wel testen: channel namen matchen wat frontend gebruikt
- **`broadcastAs` identifier** — idem, frontend-contract
- **Payload-shape keys-aanwezig** — keys bestaan in payload (niet de exacte waarden)
- **Constructor-side-effects** — heartbeat naar cache, audit-log

**Niet:**
- Exacte payload-waarden (`'toernooi_id' => 7`) — die zitten in de meegegeven constructor args
- Broadcast-framework-gedrag zelf

### Mail

**Wel:**
- **Subject + template-selectie per type** — `register` vs `password_reset` hebben verschillende subjects en links
- **Route-URL in content** — welke publieke URL wordt bezocht (contract met de landing-pages)
- **Sanitization van content** — user-generated input komt gestript in mail (geen XSS naar mail-clients)

**Niet:**
- Elke individuele view-variable
- `$with` defaults

### Commands

**Wel:**
- **Input-validation** — onbekende `--period=weekly` → exit 1
- **Side-effects** — DB-rows aangemaakt, cache gezet, log-warnings afgevuurd bij drempeloverschrijding
- **Idle/empty paths** — geen data → exit 0 zonder errors (cron-vriendelijk)
- **Time-window logic** — `now()->subHour()->startOfHour()` gebruikt `Carbon::setTestNow` voor deterministisch testen

**Niet:**
- Exacte console-output-strings (brekbaar bij vertaling)
- Argument-parsing van Laravel zelf

## Wat NOOIT testen

- **Getter/setter die één property reflecteert** — `return $this->x`
- **Boolean die `===` doet op 1 string** — `return $this->actie === 'in'`
- **Relationship-type assertions** — `assertInstanceOf(X::class, $m->relation)`
- **Fillable lists, cast arrays** — Laravel framework-territory
- **Config-constants** — `MAX_PER_PAGE = 50` assertions
- **Views renderen** via controller-calls als het alleen om Blade-compilatie gaat (geen echte assertion op gedrag)
- **Framework-gedrag** — `->middleware('auth')` werkt, routing matches, dispatchable events worden gedispatcht
- **Triviale DTO-shapes** die 1-op-1 uit constructor komen
- **Mock-only tests** waar de mock alles doet wat de test asserteert (test de mock niet het gedrag)

## Review-criterium voor bestaande tests

> **Als je dit testbestand zou schrappen, zakt de kwaliteit van de codebase?**

- **Ja** → houden
- **Nee** → verwijderen, ongeacht coverage-%

Variatie: *als deze test 6 maanden stil kapot zou gaan, merkt iemand het?* Als het antwoord "nee" is, is de test niet zinvol.

## Relatie met coverage-%

Coverage is een **proxy**, geen doel. 80% met zinvolle tests > 95% met padding. Hoe lager de padding-ratio, hoe hoger de regressie-bescherming per test-uur onderhoud.

Bij conflict: "meer coverage" verliest van "meer zinvolle tests":
- Liever 75% Lines met alleen contract/invariant/regressie-tests
- Dan 90% Lines waarvan 20% triviale getters

Coverage-padding heeft drie verborgen kosten:
1. **False security** — 90% zegt niets als 30% van die tests alleen getters raakt
2. **Onderhoud** — elke refactor breekt brittle tests → rood dashboard, desensibilisatie voor échte fails
3. **Suite-snelheid** — padding-tests duren net zo lang als zinvolle, CI wordt traag

## Voorbeelden van "goed" vs "slecht"

### Slecht (padding)
```php
#[Test]
public function is_in_returns_true_for_in_action(): void
{
    $checkin = CoachCheckin::create(['actie' => 'in', /* ... */]);
    $this->assertTrue($checkin->isIn());
}
```
Waarom slecht: `isIn()` is letterlijk `return $this->actie === 'in'`. Geen bug te vangen, breekt alleen als iemand de naam-conventie verandert (wat een bewuste breaking change zou zijn).

### Goed (invariant)
```php
#[Test]
public function is_uit_counts_both_uit_and_uit_geforceerd(): void
{
    // Business-regel: geforceerde uitcheck TELT als uitcheck voor aanwezigheid-statistieken.
    // Als iemand ooit `isUit()` aanpast naar alleen `=== 'uit'`, breken de statistieken stil.
    $forced = CoachCheckin::create(['actie' => 'uit_geforceerd', /* ... */]);
    $this->assertTrue($forced->isUit(),
        'Geforceerde uitcheck moet als uit tellen voor statistieken');
}
```
Waarom goed: dekt een domein-regel (geforceerd telt als uit) die niet letterlijk uit de methode-naam is af te lezen. Vangt een regressie als iemand refactort zonder die regel te kennen.

---

### Slecht (brittle implementation-detail)
```php
#[Test]
public function dispatch_logs_to_broadcast_log_channel(): void
{
    Log::shouldReceive('channel')->with('broadcast-log')->once();
    MyEvent::dispatch(1, 2);
}
```
Waarom slecht: pint exacte channel-naam vast, terwijl dat een interne implementatiekeuze is. Breekt bij elke log-kanaal-hernoeming.

### Goed (invariant)
```php
#[Test]
public function dispatch_never_crashes_when_broadcaster_is_down(): void
{
    // Invariant: broadcast is best-effort, user-request mag NOOIT sneuvelen.
    Broadcast::shouldReceive('event')->andThrow(new ConnectionException);

    // Geen expectException → test zakt als exception ontsnapt
    MyEvent::dispatch(1, 2);
    $this->assertTrue(true, 'Dispatch moet silent zijn bij broadcaster-fail');
}
```

---

### Slecht (exacte payload-waarden)
```php
$this->assertSame(['toernooi_id' => 7, 'mat_id' => 3, 'type' => 'score',
    'data' => ['score' => 'ippon'], 'timestamp' => '...'], $payload);
```
Waarom slecht: breekt zodra iemand één key hernoemt of de order wijzigt.

### Goed (shape + contract)
```php
$this->assertArrayHasKey('toernooi_id', $payload);
$this->assertArrayHasKey('type', $payload);
$this->assertSame('score', $payload['type'], 'type-key is public contract naar frontend');
```

## Schrappen van bestaande padding-tests

Bij het opruimen van padding-tests:

1. **Verwijder het hele testbestand** als álle tests padding zijn (bv. pure getter-tests)
2. **Verwijder individuele tests** binnen een bestand dat wél zinvolle tests bevat
3. **Consolideer** 3-varianten-van-dezelfde-scope naar 1 test
4. **Commit apart** met message `refactor(tests): verwijder padding-tests in X — zie zinvolle-tests.md`
5. **Coverage-drop is OK** — dat is het punt: we ruilen fake-coverage in voor echte regressie-bescherming

Bij twijfel: bewaar de test één sessie, markeer met `// REVIEW: padding?` comment, en beslis daarna.

## Zie ook

- `docs/kb/runbooks/test-coverage-normen.md` — norm (80%) en meten
- `docs/kb/decisions/enterprise-quality-standards.md` — waarom we überhaupt testen
- `docs/kb/runbooks/test-repair-anti-pattern.md` — VP-17, nooit assertions aanpassen om falende test groen te krijgen
- `C:\Users\henkv\.claude\projects\D--GitHub-HavunCore\memory\feedback_durable_tests_only.md` — Henk's originele feedback
