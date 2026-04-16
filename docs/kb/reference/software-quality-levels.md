# Software Kwaliteitsniveaus

> Van cowboy coding tot formele verificatie — waar sta je en waar wil je naartoe?

---

## Overzicht

| Niveau | Naam | Beschrijving | Havun status |
|--------|------|-------------|-------------|
| 1 | Cowboy Coding | Code schrijven, hopen dat het werkt | Verleden tijd |
| 2 | Versiebeheer | Git, commits, branches | ✅ Actief |
| 3 | Testing | Unit tests, guard tests, coverage | ✅ Actief |
| 4 | CI/CD | Automatische tests bij elke push | ✅ Actief |
| 5 | Docs-First | Documentatie stuurt de code | ✅ Actief |
| 6 | Auto-Herstel | Automatische foutdetectie en reparatie | ✅ Actief |
| 7 | Observability | Monitoring, alerting, performance tracking | ✅ Actief |
| 8 | Chaos Engineering | Bewust dingen kapot maken om zwaktes te vinden | ✅ Actief |
| 9 | Formal Verification | Wiskundig bewijzen dat code correct is | Niet nodig |

---

## Niveau 1: Cowboy Coding

**Wat:** Code schrijven zonder structuur, geen versiebeheer, geen tests. "Het werkt op mijn machine."

**Kenmerken:**
- Bestanden kopiëren als backup (`project_v2_final_FINAL.zip`)
- Rechtstreeks op de server bewerken
- Geen idee welke versie er draait
- Bugs fixen door meer code te schrijven
- Hopen dat het werkt na een wijziging

**Risico:** Eén fout = alles kapot, geen weg terug.

---

## Niveau 2: Versiebeheer

**Wat:** Git gebruiken om wijzigingen bij te houden. Je kunt altijd terug naar een vorige versie.

**Kenmerken:**
- Git commits met beschrijvende berichten
- Branches voor features
- Push naar GitHub (remote backup)
- `git log` toont de geschiedenis
- `git revert` als er iets fout gaat

**Tools:** Git, GitHub, GitLab, Bitbucket

**Wat het oplost:** Je kunt altijd terug. Je weet wie wat wanneer heeft gewijzigd.

**Wat het NIET oplost:** Je weet niet of de code nog werkt na een wijziging.

---

## Niveau 3: Testing

**Wat:** Geautomatiseerde tests die controleren of je code doet wat het moet doen.

**Soorten tests:**

| Type | Wat het doet | Voorbeeld |
|------|-------------|-----------|
| **Unit test** | Test één functie/methode | "berekenPrijs(3) geeft 15 terug" |
| **Feature test** | Test een complete flow | "Gebruiker kan inloggen en ziet dashboard" |
| **Guard test** | Test dat code/structuur bestaat | "checkPouleRegels methode bestaat nog" |
| **Smoke test** | Test dat pagina's laden | "Homepage geeft status 200" |
| **Regression test** | Voorkomt dat opgeloste bugs terugkeren | "Bug #123 mag niet meer optreden" |

**Coverage:** Percentage van je code dat door tests wordt geraakt.

| Coverage | Niveau | Wie doet dit |
|----------|--------|-------------|
| 0-20% | Gevaarlijk | Hobbyisten |
| 20-40% | Basis | Startups |
| 40-60% | Goed | Kleine bedrijven |
| 60-80% | Professioneel | Middelgrote bedrijven |
| 80-90% | Enterprise | Banken, SaaS platforms |
| 90%+ | Mission-critical | Luchtvaart, medisch, fintech |

**Havun status:** Actuele coverage per project → [`test-coverage-normen.md`](../runbooks/test-coverage-normen.md). Gemiddeld ~88%, 8 van 9 projecten boven 80%-norm.

**Tools:** PHPUnit (Laravel), Jest (JavaScript), pytest (Python)

**Wat het oplost:** Je weet direct als een wijziging iets kapot maakt.

**Wat het NIET oplost:** Tests draaien alleen als je ze handmatig start.

---

## Niveau 4: CI/CD (Continuous Integration / Continuous Deployment)

**Wat:** Tests draaien automatisch bij elke push naar GitHub. Kapotte code wordt direct gedetecteerd.

**Hoe het werkt:**
```
Push naar GitHub
    → GitHub Actions start automatisch
    → Installeert dependencies
    → Draait alle tests
    → Groen ✅ = code is OK
    → Rood ❌ = er is iets kapot, je krijgt een email
```

**Uitbreidingen:**
- **Automatische deploy** — na groene tests direct naar de server
- **Coverage check** — blokkeer push als coverage onder minimum zakt
- **Security audit** — check op bekende kwetsbaarheden in dependencies
- **Code style** — automatische formatting check

**Tools:** GitHub Actions, GitLab CI, Jenkins, CircleCI

**Wat het oplost:** Kapotte code kan niet ongemerkt op de server terechtkomen.

**Wat het NIET oplost:** CI test alleen wat je hebt geschreven. Geen tests = geen bescherming.

---

## Niveau 5: Docs-First

**Wat:** Documentatie is leidend. Code wordt geschreven op basis van wat er in de docs staat, niet andersom.

**Werkwijze:**
```
1. Beschrijf WAT je wilt bouwen (in MD docs)
2. Laat de docs goedkeuren
3. Schrijf code op basis van de docs
4. Tests verifiëren dat code de docs volgt
5. Docs worden bijgewerkt als de code verandert
```

**Voordelen:**
- Volgende ontwikkelaar (of AI-sessie) weet precies wat de bedoeling is
- Beslissingen worden vastgelegd met reden
- Minder "waarom is dit zo gebouwd?" vragen
- Kennisbank groeit automatisch mee

**Tools:** Markdown docs, KB systeem, DocIndexer, vector embeddings

**Wat het oplost:** Kennis gaat niet verloren tussen sessies/ontwikkelaars.

**Wat het NIET oplost:** Docs kunnen verouderen als ze niet worden bijgehouden.

---

## Niveau 6: Auto-Herstel

**Wat:** Het systeem detecteert en repareert fouten automatisch, zonder menselijke tussenkomst.

**Componenten:**

| Component | Wat het doet |
|-----------|-------------|
| **AutoFix** | AI analyseert productie-errors en past code fixes toe |
| **Syntax validatie** | `php -l` na elke fix, auto-rollback bij fouten |
| **Git sync** | Fixes worden automatisch gecommit en gepusht |
| **Rate limiting** | Max 1 fix per uur per unieke error |
| **Rollback** | Automatisch terughalen als de fix het erger maakt |
| **Notificaties** | Email bij elke fix (succes of falen) |

**Beschermingslagen:**

| Laag | Bescherming |
|------|-------------|
| 1. MD docs | Documenteer waarom iets bestaat |
| 2. DO NOT REMOVE / Shadow file | In-code markers of .integrity.json |
| 3. Tests + Linter-Gate | Regressietests + verplichte test-run |
| 4. CLAUDE.md + Recent Regressions | Projectregels + 7-dagen log |
| 5. Memory | Cross-sessie context |

**Wat het oplost:** Fouten worden 24/7 gedetecteerd en hersteld, ook als je slaapt.

**Wat het NIET oplost:** Alleen bekende fouttypen. Nieuwe, onverwachte problemen vereisen menselijk ingrijpen.

---

## Niveau 7: Observability

**Wat:** Volledig inzicht in hoe je applicatie presteert, niet alleen OF het werkt maar HOE GOED het werkt.

**Drie pijlers:**

### 1. Metrics (meten)
- **Responstijd** — hoe snel laden pagina's? (doel: <500ms)
- **Error rate** — hoeveel procent van de requests faalt? (doel: <1%)
- **Uptime** — hoe vaak is de site bereikbaar? (doel: 99.9%)
- **Throughput** — hoeveel requests per seconde?

### 2. Logging (vastleggen)
- **Gestructureerde logs** — niet alleen tekst maar doorzoekbare data
- **Error tracking** — elke error met volledige context (wie, wat, waar, wanneer)
- **Audit trail** — wie heeft wat gewijzigd

### 3. Tracing (volgen)
- **Request tracing** — volg een request door alle lagen (browser → nginx → PHP → database)
- **Slow query detection** — welke database queries zijn traag?
- **Bottleneck analyse** — waar zit de vertraging?

**Dashboard:**
- Real-time grafieken van responstijden
- Error timeline (wanneer gingen dingen fout?)
- Uptime percentage per site
- Alerts bij afwijkingen

**Tools:** Sentry, Grafana, Prometheus, New Relic, Datadog, UptimeRobot

**Wat het oplost:** Je ziet problemen VOORDAT gebruikers ze melden. Je weet niet alleen DAT iets kapot is, maar ook WAAROM en WAAR.

---

## Niveau 8: Chaos Engineering

**Wat:** Bewust onderdelen van je systeem kapot maken om te ontdekken hoe robuust het is.

**Voorbeelden:**
- Server uitzetten en kijken of de fallback werkt
- Database vertraging simuleren
- Netwerk verbinding verbreken
- AutoFix uitschakelen en kijken hoeveel errors er doorheen komen
- Willekeurig een service stoppen

**Werkwijze:**
```
1. Definieer "normaal gedrag" (baseline)
2. Formuleer hypothese: "als X kapot gaat, dan Y"
3. Voer experiment uit in staging (NOOIT direct in productie)
4. Meet het resultaat
5. Fix de zwakke plekken
```

**Netflix voorbeeld:** "Chaos Monkey" schakelt willekeurig servers uit in productie. Als Netflix dan nog werkt, is het systeem robuust genoeg.

**Jouw variant:** AutoFix uitzetten op staging, errors genereren, kijken of de fallbacks werken.

**Tools:** Chaos Monkey, Gremlin, LitmusChaos

**Wat het oplost:** Je ontdekt zwakke plekken VOORDAT ze in productie problemen veroorzaken.

---

## Niveau 9: Formal Verification

**Wat:** Wiskundig bewijzen dat software correct is. Niet testen of het werkt, maar BEWIJZEN dat het onmogelijk is om fout te gaan.

**Waar het gebruikt wordt:**
- Vliegtuigsoftware (Airbus)
- Medische apparatuur
- Nucleaire centrales
- Cryptografische protocollen
- Financiële transactiesystemen

**Waarom niet voor webapps:**
- Extreem tijdrovend (10x meer dan de code zelf)
- Vereist wiskundige expertise
- Niet praktisch voor snel veranderende applicaties
- De kosten wegen niet op tegen de risico's bij webapps

---

## Jouw Roadmap

```
Niveau 1-6: ✅ BEREIKT (enterprise coverage, auto-herstel, docs-first)
    │
    ▼
Niveau 7: ✅ BEREIKT (12 april 2026)
    │
    ├── ✅ Responstijd monitoring per endpoint (RequestMetrics middleware)
    ├── ✅ Error tracking met volledige context (ErrorLog + dedup)
    ├── ✅ Database query performance (slow queries > 100ms)
    ├── ✅ Uptime monitoring met alerts (server-side health check)
    ├── ✅ Dashboard met real-time metrics (6 API endpoints)
    └── ✅ Multi-project: alle 6 Laravel apps → centraal in HavunCore
    │
    ▼
Niveau 8: ✅ BEREIKT (12 april 2026)
    │
    ├── ✅ 5 chaos experimenten (health-deep, endpoint-probe, error-flood, db-slow, api-timeout)
    ├── ✅ Circuit breaker voor Claude API (auto-recovery)
    ├── ✅ Deep health endpoint (/api/health/deep)
    ├── ✅ Error flood deduplicatie gevalideerd
    └── ✅ Alle 6 projecten endpoint-probe: PASS
```

---

*Laatst bijgewerkt: 12 april 2026*
