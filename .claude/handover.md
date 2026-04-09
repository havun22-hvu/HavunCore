# Handover

> Laatste sessie info voor volgende Claude.

## Laatste Sessie: 09 april 2026 — Coverage boost Herdenkingsportaal + Doc cleanup

### Coverage Overzicht (einde sessie):

| Project | Tests | Lines | Doel 80% |
|---------|-------|-------|----------|
| HavunCore | 740 | **98.4%** | ✅✅ RUIM GEHAALD |
| Infosyst | 769 | **83.3%** | ✅ GEHAALD |
| Herdenkingsportaal | 2889 | **69.0%** | ❌ 11% te gaan |
| JudoToernooi | 1074 | **27.5%** | ❌ 53% te gaan |
| HavunAdmin | 393 | **?** | ❌ 14 falende tests |
| HavunVet | ? | ? | ❌ Niet gestart |
| SafeHavun | ? | ? | ❌ Niet gestart |

### Wat is gedaan vandaag:

**HavunCore**
- BERTVANDERHEIDE project verwijderd (obsoleet) — alle referenties uit docs
- Doc Intelligence: 1710 open issues resolved (meeste van oude worktrees)
- `.claude/worktrees/` toegevoegd aan DocIndexer excludePaths

**Herdenkingsportaal** — 2607→2889 tests, 53.5%→**69.0%** lines
- 10 controllers zonder tests (SitemapController t/m AdvertiserController)
- Auth controllers (Passkey, PinAuth, TwoFactor, Socialite) + TwoFactorAuthService
- Chat systeem (ChatController, ChatContentFilter, ChatStyleAnalyzer, ChatKnowledgeService)
- Crypto & Arweave (CryptoMonitoringService, ArweaveProductionService)
- Ad systeem (AdBannerController, AdImpressionController)
- Model gaps (Memorial methods, GuestbookEntry, MemorialFile)
- Remaining services (PostcodeService, PdfConversionService)
- Auth flow (register, login, email verification)
- **FUNCTIONELE payment tests** (11 tests) — webhook side effects: transaction status, user upgrade, memorial publish, invoice creation, email, HavunAdmin sync, idempotency
- Simplify review na stap 1
- GuestbookCoverage4Test throttle fix (429 errors)

### Bugs gevonden (niet gefixt):
- **PaymentTransaction $fillable ONTBREEKT crypto velden** — `markPaymentDetected()` en `markPaymentConfirmed()` in CryptoMonitoringService gebruiken `$payment->update()` met velden die niet in fillable staan (payment_detected_at, blockchain_transaction_id, actual_crypto_amount, etc.). Updates worden silently genegeerd. **FIX NODIG.**
- **UserSubscription model ONTBREEKT** — tabel `user_subscriptions` bestaat, maar model `App\Models\UserSubscription` is nooit aangemaakt. `User::getActiveSubscription()` en `hasActiveSubscription()` zijn niet testbaar.

### Hoe coverage verder verhogen (69% → 80%):

**Numeriek:** 88 bestanden op 0% coverage — meeste zijn artisan commands en mail classes. Maar gebruiker wil geen cosmetische tests.

**Functioneel belangrijke gaps:**
1. **InvoiceService** (61.4%) — generateCertificatePdf() is volledig untested
2. **EmailService** (60%) — meerdere notificatie methods untested
3. **ArweaveService** (59.4%) — blockchain upload logica
4. **AutoFixService** (71.7%) — autofix side effects
5. **Console commands** (30+ op 0%) — alleen testen als ze productie-kritiek zijn
6. **MemorialController** (70%) — nog 30% uncovered, maar veel private helpers met GD/Imagick afhankelijkheden

**Strategie feedback van gebruiker:** "Niet cosmetisch % hoog maken, functioneel en praktisch. Het is geen reclameblok." Focus op tests die echte bugs vangen.

### AutoFix branch probleem:
- AutoFix maakt continu hotfix branches aan tijdens development sessies
- Commits landen op verkeerde branch → cherry-pick naar main nodig
- **15 autofix branches opgeruimd deze sessie**
- Overweeg: AutoFix tijdelijk uitschakelen of interval vergroten tijdens dev

### Openstaande items:
- [ ] PaymentTransaction $fillable fixen (crypto velden)
- [ ] UserSubscription model aanmaken
- [ ] Chromecast — Cast Developer Console
- [ ] 4 bugs: HavunVet WorkLocation + Owner type, Infosyst enums, HavunAdmin fresh()
- [ ] Auth v5.0 — passwordless migratie
- [ ] iDEAL → iDEAL | Wero teksten
- [ ] GitGuardian incident resolven

### Belangrijke context:
- VP-02 deadline: 31 mei 2026
- Doc issues: 0 open
