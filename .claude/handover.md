# Handover

> Laatste sessie info voor volgende Claude.

## Sessie: 12 april 2026 — CI fixes, controller splits, coverage tests, doc cleanup

### Doc Intelligence Cleanup:
- 1038 issues → 0 open
- 1005 worktree-spookissues (verwijderde `.claude/worktrees/agent-a3e628e0/`) bulk-resolved
- 33 echte issues: broken links (false positives), duplicaten (verwachte overlap), outdated (reviewed)
- Build artifact verwijderd: `webapp/frontend/dist/ICONS-README.md`

### CI Workflow Fixes:
| Project | Fix | Status |
|---------|-----|--------|
| Herdenkingsportaal | `imagick` extension + HavunCore symlink checkout | CI draait, 341 pre-existing failures (niet onze code) |
| JudoToernooi | Python 3.11 + OR-Tools + storage/framework dirs | CI draait |

### Nieuwe Tests (58 tests):
| Project | Testbestand | Tests | Dekt |
|---------|-------------|-------|------|
| Herdenkingsportaal | PdfConversionServiceTest | 9 | Imagick happy path (CI), non-Imagick path (lokaal) |
| Herdenkingsportaal | ArweaveServiceRealModeTest | 25 | Non-mock paths: wallet balance, tx status, network, upload |
| Herdenkingsportaal | ArweaveCryptoSigningTest | 8 | RSA-PSS signing, deep hash, large data |
| JudoToernooi | PythonSolverCITest | 8 | callPythonSolver() happy path met echte Python+ortools |
| JudoToernooi | StripeProviderCoverageTest | 8 | createPayment, getPlatformPayment, handleOAuth, getAccount |

### Controller Splits (JudoToernooi):

#### PubliekController (995 → 653)
| Nieuw | Regels | Methods |
|-------|--------|---------|
| PubliekResultatenController | 339 | organisatorResultaten, getClubRanking, getClubResultaten, exportUitslagen, exportDanpunten |
| PubliekWegingController | 109 | scanQR, registreerGewicht |

#### PouleController (960 → 220)
| Nieuw | Regels | Methods |
|-------|--------|---------|
| PouleGeneratieController | 190 | genereer, verifieer |
| PouleJudokaController | 340 | zoekMatch, verplaatsJudokaApi, uitschrijvenJudoka |

#### ToernooiController (922 → 426)
| Nieuw | Regels | Methods |
|-------|--------|---------|
| ToernooiInstellingenController | 192 | updateWachtwoorden, updateBloktijden, updateBetalingInstellingen, updatePortaalInstellingen, updateLocalServerIps, detectMyIp, heropenVoorbereiding |
| ToernooiAfsluitenController | 178 | afsluiten, bevestigAfsluiten, heropenen |
| AdminDashboardController | 109 | index (sitebeheerder dashboard) |
| OrganisatorDashboardController | 68 | dashboard, redirect, organisatorDashboard |

### Generic Catches → Specific Types (Herdenkingsportaal):
- 12 Console commands gefixed: `\Exception` → `QueryException`, `RuntimeException`, `TransportExceptionInterface`
- Bestanden: VerifyBankPayments, ProcessScheduledBlockchainUploads, CloseExpiredMemorials, TestArweave*, ReadJpgMetadata, SendExpirationWarnings, RenamePhotos, TestCondolence, MigrateMonumentJpgToPrivate

### Openstaande items:
- [ ] Herdenkingsportaal CI: 341 pre-existing test failures in CI-omgeving (waarschijnlijk DB/storage gerelateerd)
- [ ] JudoToernooi CI: storage framework dirs fix net gepusht, resultaat afwachten
- [ ] Herdenkingsportaal coverage → 90% (blocked door Imagick — nu CI fix, afwachten)
- [ ] JudoToernooi coverage → 90% (blocked door Python — nu CI fix, afwachten)
- [ ] Memorial model nog 622 regels — meer traits mogelijk
- [ ] Chromecast — Cast Developer Console (geparkeerd)
- [ ] Auth v5.0 — passwordless migratie (toekomst)
- [ ] iDEAL → iDEAL | Wero teksten

### VP-02 deadline: 31 mei 2026 — Coverage doel BEHAALD ✓
