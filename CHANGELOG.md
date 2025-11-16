# 📝 Changelog

Alle belangrijke wijzigingen aan HavunCore worden gedocumenteerd in dit bestand.

Het formaat is gebaseerd op [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
en dit project volgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Planned
- BunqService implementation
- GmailService implementation
- HasMemorialReference trait
- Config file publicatie
- PHPUnit test suite
- CI/CD pipeline (GitHub Actions)

---

## [0.2.0] - 2025-11-16

### Added - Invoice Sync Feature

#### Services
- **InvoiceSyncService** - Synchronisatie van facturen tussen Herdenkingsportaal en HavunAdmin
  - `prepareInvoiceData()` - Prepare invoice data from monument and payment
  - `sendToHavunAdmin()` - Send invoice to HavunAdmin API
  - `getInvoiceStatus()` - Get invoice status from HavunAdmin
  - `syncStatusFromHavunAdmin()` - Sync status back to Herdenkingsportaal

- **InvoiceSyncResponse** - Response object voor sync operaties
  - `isSuccessful()` - Check if sync succeeded
  - `isFailed()` - Check if sync failed
  - `getError()` - Get error message
  - `toArray()` - Convert to array

#### Provider
- **HavunCoreServiceProvider** - Laravel Service Provider met auto-discovery
  - Singleton registratie voor alle services
  - Automatische config binding voor API credentials

#### Documentation
- Cross-project sync architectuur beschreven in `D:\GitHub\havun-mcp\SYNC-ARCHITECTURE.md`
- Implementation guides voor HavunAdmin en Herdenkingsportaal via MCP messages

### Changed
- Service Provider nu volledig geïmplementeerd (was stub in 0.1.0)

### Dependencies
- Geen nieuwe dependencies (gebruikt bestaande Guzzle voor HTTP calls)

### Breaking Changes
- Geen (backward compatible met 0.1.0)

---

## [0.1.0] - 2025-11-15

### Added - Initial Release

#### Services
- **MemorialReferenceService** - Memorial reference extractie en validatie
  - `extractMemorialReference()` - Extract 12-char reference uit text
  - `isValidReference()` - Valideer reference format
  - `fromUuid()` - Genereer reference van volledige UUID
  - `formatReference()` - Format reference voor display (met hyphens)

- **MollieService** - Mollie payment integration
  - `createPayment()` - Creëer payment met memorial reference in metadata
  - `getPayment()` - Haal payment details op
  - `extractMemorialReference()` - Extract reference uit payment metadata
  - `listPayments()` - Haal recent payments op
  - `isPaid()` - Check of payment betaald is

#### Documentation
- [README.md](README.md) - Project overview en quick start
- [SETUP.md](SETUP.md) - Complete installatiegids
- [API-REFERENCE.md](API-REFERENCE.md) - Volledige API documentatie
- [INTEGRATION-GUIDE.md](INTEGRATION-GUIDE.md) - Praktische integratie voorbeelden
- [ARCHITECTURE.md](ARCHITECTURE.md) - Architectuur en design decisions
- [CHANGELOG.md](CHANGELOG.md) - Dit bestand

#### Package Configuration
- `composer.json` - Package definitie met PSR-4 autoloading
- `.gitignore` - Git ignore configuratie
- Laravel Service Provider stub (HavunCoreServiceProvider.php)

#### Project Structure
```
src/
├── Services/
│   ├── MemorialReferenceService.php
│   └── MollieService.php
└── HavunCoreServiceProvider.php
```

### Dependencies
- PHP ^8.1
- illuminate/support ^10.0|^11.0
- guzzlehttp/guzzle ^7.0

### Breaking Changes
N/A (eerste release)

---

## Version History

### [0.1.0] - 2025-11-15 (Current)
**Status:** Development/MVP
**Focus:** Core memorial reference logic + Mollie integration

**Key Features:**
- ✅ Memorial reference extraction (12-char UUID prefix)
- ✅ Mollie payment creation met metadata
- ✅ Transaction matching capability
- ✅ Complete documentation suite

**Missing:**
- ⏳ BunqService
- ⏳ GmailService
- ⏳ Unit tests
- ⏳ Config file

---

## Migration Guides

### Upgrading to 0.1.0 from scratch

**Stap 1: Installeer package**
```bash
# In je project (Herdenkingsportaal, HavunAdmin, etc.)
composer require havun/core
```

**Stap 2: Add environment variabelen**
```env
# .env
MOLLIE_API_KEY=test_xxxxxxxxxx
```

**Stap 3: Gebruik services**
```php
use Havun\Core\Services\MollieService;

$mollie = new MollieService(env('MOLLIE_API_KEY'));
$payment = $mollie->createPayment(...);
```

---

## Roadmap

### Version 0.2.0 (Planned - Q1 2025)
**Focus:** Banking integration

**Features:**
- [ ] BunqService implementation
  - [ ] List transactions
  - [ ] Extract memorial reference from description
  - [ ] Get account balance
  - [ ] Webhook handler
- [ ] Unit tests voor BunqService
- [ ] Update documentation

**Breaking Changes:** None planned

---

### Version 0.3.0 (Planned - Q1 2025)
**Focus:** Email integration

**Features:**
- [ ] GmailService implementation
  - [ ] OAuth2 authentication
  - [ ] Search emails by criteria
  - [ ] Download PDF attachments
  - [ ] Extract memorial reference from body
  - [ ] Mark emails as processed
- [ ] Unit tests voor GmailService
- [ ] Update documentation

**Breaking Changes:** None planned

---

### Version 0.4.0 (Planned - Q2 2025)
**Focus:** Developer experience

**Features:**
- [ ] HasMemorialReference trait voor Laravel models
- [ ] Config file (`config/havun.php`)
- [ ] Laravel Service Provider met auto-discovery
- [ ] Artisan commands voor sync
- [ ] Complete PHPUnit test suite
- [ ] GitHub Actions CI/CD

**Breaking Changes:**
- Mogelijk: Service constructors accepteren config array ipv aparte parameters

---

### Version 1.0.0 (Planned - Q2 2025)
**Focus:** Production ready

**Requirements voor 1.0.0:**
- ✅ Alle core services (Mollie, Bunq, Gmail)
- ✅ 100% test coverage
- ✅ Complete documentation
- ✅ Used in production (Herdenkingsportaal + HavunAdmin)
- ✅ No critical bugs
- ✅ Stable API (no breaking changes planned)

---

## Changelog Format

We gebruiken [Keep a Changelog](https://keepachangelog.com/) format:

### Categorieën:
- **Added** - Nieuwe features
- **Changed** - Wijzigingen in bestaande functionaliteit
- **Deprecated** - Features die binnenkort verwijderd worden
- **Removed** - Verwijderde features
- **Fixed** - Bug fixes
- **Security** - Security fixes

### Voorbeeld entry:
```markdown
## [0.2.0] - 2025-02-01

### Added
- BunqService voor banking integration
- Unit tests voor BunqService

### Changed
- MollieService constructor accepteert nu config array

### Fixed
- MemorialReferenceService regex nu case-insensitive
```

---

## Support

**Issues:** https://github.com/havun/HavunCore/issues
**Discussions:** https://github.com/havun/HavunCore/discussions

---

**Laatste update:** 2025-11-15
**Maintainer:** Henk van Velzen <havun22@example.com>
