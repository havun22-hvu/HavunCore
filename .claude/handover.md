# Handover

> Laatste sessie info voor volgende Claude.

## Sessie: 12-13 april 2026 — CI groen, controller splits, coverage 82.2%, doc cleanup

### Resultaten deze sessie:

**CI: beide projecten GROEN**
- Herdenkingsportaal: 5361 tests, 0 failures, 82.2% coverage
- JudoToernooi: 3258 tests, 0 failures, CI groen

**Wat is gedaan:**
- Doc Intelligence: 1038 issues → 0 open
- CI workflows werkend: Imagick+Ghostscript, Python+OR-Tools, withoutVite, HavunCore symlink
- 220+ nieuwe tests geschreven (Arweave, PDF, Python solver, Stripe, AdminPayments, MemorialConcerns, Export/Condolence)
- 3 JudoToernooi fat controllers → 11 controllers
- Memorial model: 3 nieuwe traits (622→385 regels)
- 12 generic catches → specific exception types
- iDEAL → iDEAL | Wero teksten (beide projecten)
- Security: 8 → 1 kwetsbaarheid (firebase/php-jwt low, blocked door socialite)
- ArweaveService coverage: 18% → 86%

### Openstaande items — VOLGENDE SESSIE:

#### 1. Coverage 82.2% → 90% (Herdenkingsportaal)
**1304 regels nodig.** Coverage gap analyse beschikbaar als CI artifact.

**Stap 1: Split deze 5 bestanden naar <400 regels:**

| File | Regels | Gap | Splitvoorstel |
|------|--------|-----|---------------|
| MemorialUploadController | 1318 | 306 | foto/PDF/monument image uploads apart |
| PaymentController | 1318 | 157 | checkout/webhook/invoice apart |
| AdminController | 1286 | 115 | users/memorials/stats apart |
| AutoFixService | 1088 | 141 | analyse/repair/git apart |
| MemorialMonumentController | 757 | 104 | template/custom/preview apart |

**Stap 2: Tests schrijven voor de gesplitste bestanden**

**Stap 3: Resterende kleine gaten (test only):**

| File | Gap | Actie |
|------|-----|-------|
| HealthController | 99 | 0% coverage, simpele test |
| ArweaveProductionService | 91 | Http::fake tests |
| AdminPaymentsController | 117 | Meer edge cases |
| MemorialPublishController | 76 | Route tests |
| ProcessMemorialUpload job | 76 | Job dispatch tests |

#### 2. Bestandsgrootte norm: max 400 regels
- Claude AI werkt optimaal onder 400 regels per bestand
- Boven 800 regels: features verdwijnen, edits raken verkeerde plek
- Boven 1000 regels: "DO NOT REMOVE" comments nodig als bescherming
- Alle bestanden >400 regels inventariseren en plannen voor split

#### 3. Overig
- [ ] firebase/php-jwt v6→v7 (blocked door laravel/socialite ^6.4)
- [ ] Chromecast — Cast Developer Console (geparkeerd)
- [ ] Auth v5.0 — passwordless migratie (toekomst)

### Coverage gap analyse (CI artifact)
De coverage.xml is beschikbaar als GitHub Actions artifact op de laatste groene run.
Gebruik: `gh run download <ID> -n coverage-xml` om te downloaden.

### VP-02 deadline: 31 mei 2026 — Coverage 82.2%, doel 90%
