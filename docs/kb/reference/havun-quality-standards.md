# Havun Kwaliteits- & Veiligheidsnormen (VERPLICHT)

> **Dé centrale norm voor alle Havun projecten.**
> Claude MOET dit document raadplegen bij ELKE code wijziging.
> Enterprise-maatstaven — geen uitzonderingen zonder expliciete toestemming.

---

## VERPLICHTE WERKWIJZE

**Claude MOET:**

1. **Docs-first werken** — geen code zonder MD docs/plan
2. **Coverage >80% halen** voor alle nieuwe interne code (enterprise niveau)
3. **ALLE veiligheidstechnieken toepassen** bij externe input/invoer (zie secties 2-10)
4. **De checklist doorlopen** voordat een feature "klaar" is
5. **Tests schrijven** voor elke nieuwe functie VOORDAT code gecommit wordt
6. **Bestaand werk respecteren** — projecten onder 80% worden verbeterd, niet geblokkeerd

**Bij twijfel:** dit document is leidend, NIET Laravel defaults of persoonlijke voorkeur.

---

## Snel overzicht

Een Havun project voldoet aan de normen als:

- ✅ Test coverage **>80%** voor alle interne code (niveau enterprise)
- ✅ **Input validatie** via Form Requests bij elke user input
- ✅ **Rate limiting** op alle API endpoints en login
- ✅ **CSRF bescherming** op alle forms (Laravel default)
- ✅ **Custom exceptions** voor gestructureerde foutafhandeling
- ✅ **Circuit breakers** bij externe services (Mollie, Reverb, Ollama)
- ✅ **Fallback** bij kritieke externe diensten
- ✅ **Audit trail** voor kritieke acties (wie, wat, wanneer)
- ✅ **Health check endpoints** (`/health`)
- ✅ **Security headers** (CSP, HSTS, X-Frame-Options)
- ✅ **CI/CD pipeline** met tests + security audit bij elke push
- ✅ **AutoFix** voor 24/7 productie monitoring (optioneel)
- ✅ **5 beschermingslagen** tegen regressie

---

## 1. Test Coverage (>80%)

**Norm:** Minimaal 80% line coverage voor alle interne logica.

| Coverage | Niveau | Acceptabel? |
|----------|--------|-------------|
| 0-20% | Gevaarlijk | ❌ Blokkeer deploy |
| 20-40% | Basis | ⚠️ Werk-in-progress |
| 40-60% | Goed | ⚠️ Verbeter stap voor stap |
| 60-80% | Professioneel | ✅ Acceptabel |
| **80-90%** | **Enterprise** | ✅ **Norm** |
| 90%+ | Mission-critical | ✅ Ideaal |

**Havun status (16-04-2026):**

| Project | Coverage | Status |
|---------|----------|--------|
| SafeHavun | 94,22% | ✅ Mission-critical |
| JudoScoreBoard | 93,42% | ✅ Mission-critical |
| Infosyst | 91,51% | ✅ Mission-critical |
| HavunVet | 90,87% | ✅ Mission-critical |
| JudoToernooi | 89,84% | ✅ Enterprise |
| HavunAdmin | 89,75% | ✅ Enterprise |
| HavunCore | 87,4% | ✅ Enterprise |
| Studieplanner | 82,67% | ✅ Enterprise |
| Herdenkingsportaal | 79,05% | ⚠️ Bijna enterprise (1% te gaan) |

**Wat moet getest worden:**
- Business logica (berekeningen, validaties, workflows)
- Eloquent models (relaties, scopes, methodes)
- Controllers (happy path + error paths)
- Services (alle publieke methodes)
- Policies (autorisatie)
- Middleware (guards, rate limits)

**Wat hoeft NIET getest:**
- Blade templates (wel smoke tests voor pagina's)
- Migration files
- Config files
- Third-party code

**Details:** `docs/kb/patterns/regression-guard-tests.md`

---

## 2. Input Validatie (User + External)

### User Input (Forms, API)

**Norm:** ALLE user input wordt gevalideerd via Laravel Form Requests.

```php
// GOED — Form Request met Nederlandse messages
class JudokaStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'naam' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'gewicht' => 'required|numeric|min:0|max:300',
            'geboortejaar' => 'required|integer|min:1900|max:' . date('Y'),
        ];
    }

    public function messages(): array
    {
        return [
            'gewicht.max' => 'Gewicht mag niet hoger zijn dan 300 kg',
        ];
    }
}

// FOUT — direct validatie in controller
public function store(Request $request) {
    $data = $request->all(); // ❌ geen validatie
}
```

### External Input (API calls, Webhooks)

**Norm:** Externe input wordt behandeld als onbetrouwbaar en gevalideerd + gesanitized.

```php
// Mollie webhook — valideer payment ID + fetch vanuit API
$paymentId = $request->input('id');
if (!preg_match('/^tr_\w+$/', $paymentId)) {
    abort(400, 'Invalid payment ID');
}
$payment = Mollie::api()->payments->get($paymentId); // Fetch vanuit bron
```

### File Uploads

**Norm:** Validatie op type, size, én content.

```php
$request->validate([
    'foto' => 'required|image|mimes:jpeg,png|max:5120', // 5MB
]);
```

### SQL Injection preventie

**Norm:** Eloquent ORM of parameter binding. NOOIT raw SQL met user input.

```php
// GOED
User::where('email', $email)->first();
DB::select('SELECT * FROM users WHERE email = ?', [$email]);

// FOUT
DB::select("SELECT * FROM users WHERE email = '$email'"); // ❌
```

### XSS preventie

**Norm:** Blade auto-escaping (`{{ $var }}`). Gebruik `{!! !!}` ALLEEN voor vertrouwde HTML.

### CSP Nonce (VERPLICHT bij nieuwe code)

**Norm:** Alle NIEUWE inline `<script>` tags MOETEN een nonce attribuut krijgen.

```php
// GOED — nieuwe scripts altijd met @nonce
<script @nonce>
    // jouw code
</script>

// FOUT — geen nonce
<script>
    // jouw code
</script>
```

**Waarom:** CSP `unsafe-inline` is een beveiligingsrisico. Met nonce kan een hacker geen eigen scripts injecteren. Wanneer alle scripts nonce hebben → `unsafe-inline` verwijderen uit CSP → SecurityHeaders A+ score.

**Bestaande code:** Stap voor stap omzetten bij aanraking. Niet alles tegelijk.

---

## 3. Rate Limiting

**Norm:** Elke API endpoint, login, form submission heeft rate limiting.

```php
// AppServiceProvider
RateLimiter::for('api', fn($r) => Limit::perMinute(60)->by($r->ip()));
RateLimiter::for('login', fn($r) => Limit::perMinute(5)->by($r->ip()));
RateLimiter::for('form-submit', fn($r) => Limit::perMinute(10)->by($r->ip()));
RateLimiter::for('webhook', fn($r) => Limit::perMinute(100)->by($r->ip()));
RateLimiter::for('public-api', fn($r) => Limit::perMinute(30)->by($r->ip()));
```

---

## 4. Error Handling

**Norm:** Tests voor eigen code, opvangmethodes voor buitenwereld.

| Type | Methode |
|---|---|
| Eigen code fouten | Tests (voorkom de fout) |
| API/HTTP calls | Try/catch + timeout + retry |
| Database | Try/catch + transactie + rollback |
| File systeem | Try/catch + permission check |
| User input | Form Request validation |
| Zware operaties | Queue (async) |
| Externe dienst vaak down | Circuit breaker + fallback |

### Custom Exception hiërarchie (verplicht)

```
\Exception
└── {Project}Exception (base — userMessage + context)
    ├── MollieException        (error codes, betalingen)
    ├── ImportException         (row-level tracking)
    └── ExternalServiceException (timeout, connection)
```

**Details:** `docs/kb/patterns/error-handling-strategies.md`

---

## 5. Circuit Breaker & Fallback

**Norm:** Externe diensten krijgen circuit breaker bescherming.

```php
// Na 3 failures: skip calls voor 30 sec
$circuitBreaker = new CircuitBreaker('mollie');
$circuitBreaker->call(
    fn() => $this->mollieCall(),
    fn() => throw MollieException::apiError($endpoint, 'Service unavailable')
);
```

**Fallback voorbeelden:**
- Ollama down → TF-IDF (DocIndexer)
- Reverb down → event in DB, broadcast later
- Email provider down → queue + retry

---

## 6. Authentication & Authorization

**Norm:** Laravel auth + CSRF + passkeys (waar mogelijk).

### Authenticatie methoden (Havun standaard)
- **Email + wachtwoord** (minimaal)
- **Magic link** (email-based passwordless)
- **QR login** (desktop → smartphone)
- **WebAuthn/Passkey** (biometrisch, hoogste veiligheid)

### Autorisatie
**Norm:** Laravel Policies voor ELKE model met CRUD operaties.

```php
// Policy check in controller
$this->authorize('update', $memorial);

// Of in middleware
Route::middleware('can:update,memorial')->put(...)
```

### Wachtwoord eisen
- Minimaal 8 tekens
- Laravel `Password::defaults()` rules
- Bcrypt hashing (Laravel default)

### Session Security
- `SESSION_SECURE_COOKIE=true` op HTTPS
- `SESSION_HTTP_ONLY=true`
- `SESSION_SAME_SITE=lax`

---

## 7. Security Headers

**Norm:** Alle projecten hebben SecurityHeaders middleware.

```php
$response->headers->set('X-Frame-Options', 'SAMEORIGIN');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('X-XSS-Protection', '1; mode=block');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Permissions-Policy', 'geolocation=(), camera=()');
$response->headers->set('Content-Security-Policy', "default-src 'self'; ...");
```

---

## 8. Audit Trail

**Norm:** Kritieke acties worden gelogd (wie, wat, wanneer).

```php
ActivityLogger::log($toernooi, 'verplaats_judoka', "Jevi verplaatst", [
    'model' => $judoka,
    'properties' => ['van_poule' => 3, 'naar_poule' => 7],
]);
```

**Welke acties:**
- Login, logout, password reset
- Betalingen (aanmaken, voltooien, falen)
- Data wijzigingen (update, delete)
- Autorisatie wijzigingen (rol toekennen)
- Admin acties

---

## 9. Health Checks & Monitoring

**Norm:** Elk project heeft minimaal `/health` endpoint.

```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'checks' => [
            'database' => DB::connection()->getPdo() ? 'ok' : 'fail',
            'disk' => disk_free_space('/') > 1e9 ? 'ok' : 'low',
            'cache' => Cache::set('test', 1) ? 'ok' : 'fail',
        ],
    ]);
});
```

Uitgebreid: `/health/detailed` (auth required).

---

## 10. CI/CD Pipeline

**Norm:** GitHub Actions bij elke push.

**Verplichte checks:**
- ✅ Tests (`php artisan test`)
- ✅ Coverage threshold (>80% of minimum per project)
- ✅ Security audit (`composer audit`)
- ✅ Integrity check (`.integrity.json` als aanwezig)

**Status per project:**

| Project | CI | Coverage check | Security |
|---------|-----|---------------|----------|
| HavunCore | ✅ | ✅ | ✅ |
| HavunAdmin | ✅ | ✅ | ✅ |
| Herdenkingsportaal | ✅ | ✅ | ✅ |
| JudoToernooi | ✅ | ✅ | ✅ |
| Studieplanner | ✅ | - | - |
| SafeHavun | ✅ | ✅ | ✅ |
| Infosyst | ✅ | ✅ | ✅ |
| HavunClub | ✅ | ✅ | ✅ |

---

## 11. AutoFix (optioneel)

**Norm:** Kritieke productieprojecten krijgen AutoFix.

**Actief op:** JudoToernooi, Herdenkingsportaal

**Werking:**
- 500-error in productie → Claude AI analyse → fix → syntax check → rollback bij fout → git commit+push → notificatie
- Max 2 pogingen per error, rate limit 1 per uur per uniek error
- Branch-model: fixes in `hotfix/autofix-*` branches + PR

**Details:** `docs/kb/reference/autofix.md`

---

## 12. 5 Beschermingslagen

**Norm:** Alle projecten hanteren het 5-laags beschermingssysteem.

| Laag | Wat | Wanneer |
|------|-----|---------|
| 1. MD docs | Documenteer WAAROM iets bestaat | Nieuwe feature |
| 2. DO NOT REMOVE / .integrity.json | In-code markers of shadow file | Kritieke elementen |
| 3. Tests + Linter-Gate | Regressie/guard/smoke tests | Altijd |
| 4. CLAUDE.md + Recent Regressions | Projectregels + 7-dagen log | Project-brede patterns |
| 5. Memory | Cross-sessie context | Project-overstijgend |

---

## Checklist voor nieuwe feature

Voordat een feature wordt gemerged:

- [ ] Docs beschrijven wat de feature doet (docs-first)
- [ ] Form Request voor user input
- [ ] Rate limiting waar van toepassing
- [ ] Policy voor autorisatie
- [ ] Unit tests voor business logica
- [ ] Feature tests voor happy path + error paths
- [ ] Guard tests voor kritieke elementen
- [ ] Coverage blijft boven drempel
- [ ] Custom exceptions voor externe calls
- [ ] Circuit breaker bij nieuwe externe dienst
- [ ] Audit log voor kritieke acties
- [ ] Security headers intact
- [ ] CI pipeline groen
- [ ] Integrity check (indien shadow file)

---

## Verwijzingen

| Onderwerp | Document |
|-----------|----------|
| Test patterns | `docs/kb/patterns/regression-guard-tests.md` |
| Error handling | `docs/kb/patterns/error-handling-strategies.md` |
| Kwaliteitsniveaus | `docs/kb/reference/software-quality-levels.md` |
| AutoFix | `docs/kb/reference/autofix.md` |
| Integrity check | `docs/kb/patterns/integrity-check.md` |
| Werkwijze | `docs/kb/runbooks/claude-werkwijze.md` |
| JT Stability (uitgebreid) | `D:\GitHub\JudoToernooi\laravel\docs\3-DEVELOPMENT\STABILITY.md` |

---

## Voor Claude sessies in andere projecten

Bij elke vraag over veiligheid, validatie, coverage, of kwaliteit:

```bash
# 1. Zoek in de KB
cd D:\GitHub\HavunCore && php artisan docs:search "havun quality standards"

# 2. Of lees direct:
cat D:\GitHub\HavunCore\docs\kb\reference\havun-quality-standards.md

# 3. Pas de norm toe op het huidige project
```

**Als het project er niet aan voldoet:** meld het + maak een plan om ernaartoe te werken. NIET blokkeren van bestaand werk.

---

*Laatst bijgewerkt: 10 april 2026*
