# Pattern: Error Handling Strategies

> Wanneer tests, wanneer try/catch, wanneer iets anders?

## Vuistregel

- **Jouw code** → tests (voorkom de fout)
- **Buitenwereld** → opvangmethode (vang de fout op)

## Overzicht

| Methode | Wanneer | Voorbeeld | Havun gebruik |
|---------|---------|-----------|---------------|
| **Tests** | Eigen code/logica | Unit test, guard test | PHPUnit, Jest |
| **Try/catch** | Code die kan falen door externe factoren | API call, file read | Ollama calls, Mollie |
| **Timeout** | Externe dienst reageert mogelijk niet | HTTP request | `Http::timeout(30)` bij Ollama |
| **Retry** | Tijdelijke fout, volgende keer werkt het wel | API 503, database lock | AutoFix max 2 pogingen |
| **Circuit breaker** | Dienst herhaaldelijk down | Na 3 fails → stop proberen | AutoFix 1x per uur per error |
| **Fallback** | Hoofdoptie faalt, alternatief beschikbaar | Primaire dienst down | Ollama → TF-IDF in DocIndexer |
| **Rate limiting** | Te veel requests van buiten | API endpoint bescherming | Laravel throttle, Express rateLimit |
| **Validation** | User input onbetrouwbaar | Formulieren, API input | Laravel Form Requests |
| **Queue** | Mag later, hoeft niet direct | Zware operaties | Arweave upload, email verzenden |
| **Rollback** | Actie faalt halverwege | Database transactie, deploy | AutoFix syntax check → rollback |

## Wanneer NIET try/catch

```php
// FOUT — maskeert een bug in je eigen code
try {
    $prijs = $product->berekenPrijs();
} catch (\Exception $e) {
    $prijs = 0; // Bug verborgen!
}

// GOED — schrijf een test
public function test_bereken_prijs_werkt()
{
    $product = Product::factory()->create(['basis_prijs' => 10]);
    $this->assertEquals(10, $product->berekenPrijs());
}
```

## Wanneer WEL try/catch

```php
// GOED — externe API kan altijd falen
try {
    $response = Http::timeout(30)->post('https://api.mollie.com/v2/payments', $data);
} catch (\Exception $e) {
    Log::error('Mollie onbereikbaar: ' . $e->getMessage());
    return redirect()->back()->with('error', 'Betaaldienst tijdelijk niet beschikbaar');
}
```

## Retry pattern

```php
// Probeer 3x met exponential backoff
$maxRetries = 3;
for ($i = 0; $i < $maxRetries; $i++) {
    try {
        $result = Http::timeout(10)->get($url);
        if ($result->successful()) break;
    } catch (\Exception $e) {
        if ($i === $maxRetries - 1) throw $e;
        sleep(pow(2, $i)); // 1s, 2s, 4s
    }
}
```

## Circuit breaker pattern

```php
// Na 3 opeenvolgende fouten: stop met proberen voor 5 minuten
$cacheKey = "circuit:{$service}";
$failures = Cache::get($cacheKey, 0);

if ($failures >= 3) {
    Log::warning("Circuit open voor {$service} — skip");
    return null; // Gebruik fallback
}

try {
    $result = $service->call();
    Cache::forget($cacheKey); // Reset bij succes
    return $result;
} catch (\Exception $e) {
    Cache::put($cacheKey, $failures + 1, 300); // 5 min TTL
    throw $e;
}
```

## Fallback pattern

```php
// Primair: Ollama embeddings. Fallback: TF-IDF
$embedding = null;
try {
    $embedding = $this->ollamaEmbed($content);
} catch (\Exception $e) {
    Log::warning('Ollama niet beschikbaar, fallback naar TF-IDF');
}

if (!$embedding) {
    $embedding = $this->tfidfEmbed($content); // Altijd beschikbaar
}
```

## Beslisboom

```
Is het JOUW code?
├─ Ja → Schrijf een TEST
│
└─ Nee (externe factor) → Welk type?
   ├─ API/HTTP call → Try/catch + timeout + retry
   ├─ Database → Try/catch + transactie + rollback
   ├─ File systeem → Try/catch + permission check
   ├─ User input → Validation rules
   ├─ Zware operatie → Queue (async)
   └─ Dienst vaak down → Circuit breaker + fallback
```

## Havun Implementatie

Alle patterns zijn geïmplementeerd in de Havun projecten:

### Custom Exception Hiërarchie (JudoToernooi)
```
Exception
└── JudoToernooiException (base — met userMessage + context)
    ├── MollieException        (error codes 1001-1005)
    ├── ImportException         (row-level tracking)
    └── ExternalServiceException (timeout, connection, process)
```

### Circuit Breaker (JudoToernooi + Herdenkingsportaal)
```
CLOSED (normaal) → 3 failures → OPEN (block calls, 30 sec)
                                    → HALF_OPEN (test call)
                                    → success → CLOSED
```
Gebruikt bij: Mollie API, Reverb WebSocket broadcasts

### SafelyBroadcasts (JudoToernooi)
3-laags bescherming voor Reverb/WebSocket:
1. Circuit Breaker → fail-fast na 3 failures
2. Try-catch → exceptions worden gelogd
3. Log throttling → max 1 logmelding per minuut per event

### Result Object Pattern
```php
$result = Result::success($data);   // of Result::failure('fout')
if ($result->isSuccess()) { ... }
return $result->toResponse();       // JSON met success/error
```

### Guard Clauses
Early return i.p.v. geneste if-statements. Null-safe operator (`?->`) voor chains.

### Error Notification Service
Kritieke errors automatisch naar HavunCore API (fire-and-forget of sync).

### Rate Limiting
| Endpoint type | Limiet |
|---|---|
| API | 60/min |
| Public | 30/min |
| Forms | 10/min |
| Login | 5/min |
| Webhooks | 100/min |

### Health Check Endpoints
- `/health` — basis (database, disk, cache)
- `/health/detailed` — uitgebreid (response times, drivers, config)

### Volledig overzicht
Zie: `D:\GitHub\JudoToernooi\laravel\docs\3-DEVELOPMENT\STABILITY.md` (728 regels, 11 secties)

## Combinatie met Test Coverage

| Bescherming | Wat | Coverage |
|---|---|---|
| Tests | Voorkom fouten in eigen code | 79-94% |
| Exception hiërarchie | Categoriseer fouten | Alle externe calls |
| Circuit breaker | Voorkom cascade failures | Mollie, Reverb |
| Fallback | Alternatief bij falen | Ollama → TF-IDF |
| Rate limiting | Bescherm tegen overbelasting | Alle API endpoints |
| Health checks | Detecteer problemen vroeg | Continu monitoring |
| AutoFix | Herstel fouten automatisch | 24/7 productie |
| Audit trail | Wie deed wat wanneer | Alle kritieke acties |

**Samen met 79-94% test coverage (gemiddeld ~88% over 9 projecten) vormt dit een enterprise-grade beveiligingslaag.**

---

*Laatst bijgewerkt: 8 april 2026*
