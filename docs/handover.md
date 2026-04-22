# Handover (auto-generated)

> **Auto-gegenereerd door `php artisan docs:handover`** op Wed, Apr 22, 2026 1:45 PM.
> Bewerk dit bestand niet handmatig — wijzigingen worden overschreven.
> Voor session-detail zie `.claude/handover.md`. Voor V&K-architectuur zie
> `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md`.

## Recente activiteit (laatste 7 dagen)

| Datum | Hash | Bericht |
|-------|------|---------|
| 2026-04-22 | `32f2fb6` | chore(qv): refresh KB snapshot — 2 HIGH (beide bekend, geen actie) |
| 2026-04-22 | `e5890de` | ci(infection): switch baseline cron from quarterly to monthly |
| 2026-04-22 | `f245965` | docs: punt 1+2 afgerond — aiproxy SQLite + observability beide @ 95% gate |
| 2026-04-22 | `95127e0` | fix(infection): cover remaining observability env-bound mutators |
| 2026-04-22 | `c32b97e` | fix(infection): extend observability ignores for residual CI false-positives |
| 2026-04-22 | `02b23a2` | fix(infection): per-mutator ignores for unkillable false-positives |
| 2026-04-22 | `5c40959` | docs: 22-04 middag-blok — observability tests + CI-floor gate decision |
| 2026-04-22 | `effb04f` | fix(ci): observability gate to CI floor (61% actual) — env-noise on Windows |
| 2026-04-22 | `1d6de53` | chore(simplify): cleanup observability test additions |
| 2026-04-22 | `a52f0b9` | test(observability): +4 tests kill all CastInt/Round/Limit escapes (100% MSI) |
| 2026-04-22 | `0dc60c7` | ci(infection): ramp aiproxy-mysql 85 -> 95 + docs (CI now fully green) |
| 2026-04-22 | `d039040` | fix(ci): full-scope baseline covered-MSI 70 -> 65 to match floor |
| 2026-04-22 | `03e65b3` | fix(ci): observability gate 65 -> 55 — actual floor is 59.09% |
| 2026-04-22 | `743c4c9` | chore(simplify): hoist mysql DB_* vars to job level (single source) |
| 2026-04-22 | `ac3b909` | fix(ci): mysql shell-env override + observability gate to floor |
| 2026-04-22 | `fc57601` | fix(ci): drop phpunit.mysql.xml — Laravel reads .env not phpunit <env> |
| 2026-04-22 | `de0a0ca` | fix: revert ObservabilityService Severity-rollout + scope mysql job |
| 2026-04-22 | `94148ac` | fix(ci): mutation-test runs were missing --exclude-group=doc-intelligence |
| 2026-04-22 | `01b0475` | docs: mark aiproxy MySQL-fixture LIVE in CI (gate at 85%, ramp to 90) |
| 2026-04-22 | `d0ac2e6` | chore(simplify): trim mysql-fixture commentary + drop redundant ready-loop |
| 2026-04-22 | `fc0985f` | ci(infection): add aiproxy MySQL real-driver MSI gate at 85% |
| 2026-04-22 | `684ccab` | docs(handover): night-autonomy session summary — K&V system done |
| 2026-04-22 | `27a3e03` | chore(infection): actually bump minMsi 60 -> 70 (file update) |
| 2026-04-22 | `9581581` | docs(kb): plan for MySQL fixture to close AIProxy MSI 81→90 gap |
| 2026-04-22 | `9d36745` | chore(infection): full-scope baseline 74% + bump minMsi 60 -> 70 |
| 2026-04-22 | `2e8a5c5` | chore(infection): bump minMsi 48 -> 60 + write 22-04 baseline doc |
| 2026-04-22 | `20d29bc` | docs(handover): pad 6 marked n/a + CI matrix job live |
| 2026-04-22 | `1b4ffe5` | ci(mutation): per-path matrix as PR gate + pad 6 documented as n/a |
| 2026-04-22 | `aed9891` | docs(infection): pad 3 AutoFix MSI 87%, 6 of 7 critical paths at target |
| 2026-04-22 | `9bc30df` | test(autofix): +24 tests + type-fix — MSI 28% -> 87% — target 85% passed (pad 3) |
| 2026-04-21 | `ce82607` | chore(critical-paths): trim redundant preamble in test docblocks |
| 2026-04-21 | `6daa059` | test(critical-paths): +15 tests — MSI 84.85% -> 88.89% |
| 2026-04-21 | `9ab20a9` | docs(infection): 4 critical paths at target — Vault 91%, AIProxy 81%, Device Trust 100%, Observability 100% |
| 2026-04-21 | `1e3a78c` | test(vault): +13 tests — MSI 85% -> 91% — target 90% passed (pad 1) |
| 2026-04-21 | `15a79b9` | docs(infection): pad 5 Observability -> MSI 100% geregistreerd |
| 2026-04-21 | `40541fd` | refactor(test): DRY ObservabilityServiceTest row-fabrication helpers |
| 2026-04-21 | `f23b17d` | test(observability): +24 tests — MSI 68,91% -> 100% (pad 5) |
| 2026-04-21 | `0906ade` | refactor(device-trust-test): move Carbon::setTestNow reset to tearDown |
| 2026-04-21 | `e9b5748` | test(device-trust): +10 tests, source-fix — MSI 83% -> 100% (pad 4) |
| 2026-04-21 | `4538531` | docs(infection): AIProxy Runs 3-7 recorded — MSI 58% -> 81% + false-positive floor |
| 2026-04-21 | `65b14f5` | test(ai-proxy): +8 tests + hardening — MSI 58% -> 81% on AIProxyService |
| 2026-04-21 | `86d602e` | docs(infection): record AIProxy Run 2 — 48% -> 58% MSI + remaining plan |
| 2026-04-21 | `95fa044` | test(ai-proxy): 5 mutation-quick-wins — MSI 48% -> 58% on AIProxyService |
| 2026-04-21 | `14bb43a` | docs(infection): setup plan for mutation-baseline |
| 2026-04-21 | `6e8b5b5` | feat(qv-scan): exclude Laravel boilerplate ExampleTest.php from test-erosion |
| 2026-04-21 | `c284087` | chore(claude): remove stale lock + outdated handover-restart |
| 2026-04-21 | `e901876` | docs(handover): padding-sanitization cross-project — 75 tests weg, 0 behavior verloren |
| 2026-04-21 | `8128d70` | docs(handover): HA padding-sanitization 21-04 ochtend — 49 tests weg |
| 2026-04-21 | `2813815` | docs(handover): 21-04 nacht autonomous push — 95 tests, 158 refs groen |
| 2026-04-21 | `eedd1cf` | test: migrate @dataProvider docblock to attribute (PHPUnit 12 prep) |
| 2026-04-21 | `58ba034` | test(device-trust): cover getUserDevices + getAccessLogs |
| 2026-04-21 | `6f97036` | docs(critical-paths): JT Pad 6 (LocalSyncAuth) + Pad 7 (ScoreboardToken) |
| 2026-04-21 | `b69bcd0` | docs(critical-paths): HP Pad 5 — AutoFixServiceTest bestaat (20 tests), TODO verhelderd |
| 2026-04-21 | `7c1b288` | test(vault): dedicated EnsureAdminToken middleware guard |
| 2026-04-21 | `69ac40d` | docs(critical-paths): JT Pad 1 — CheckDeviceBindingTest added, 15 refs |
| 2026-04-21 | `6b22995` | docs(critical-paths): HP Pad 2 — PublishFlowTest added, 25 refs / 25 ok |
| 2026-04-21 | `e6380de` | docs(critical-paths): JT Pad 5 — tenant-isolation tests added, 14 refs |
| 2026-04-21 | `261d9ce` | feat(critical-paths): JS/TS support + Studieplanner (mobile) doc |
| 2026-04-21 | `335cbc8` | docs(critical-paths): JT Pad 3 — ScoreRegistrationTest placeholders unlocked |
| 2026-04-21 | `b4aeeda` | docs(critical-paths): HP Pad 2 — MemorialLifecycleTest added, 24 refs / 24 ok |
| 2026-04-21 | `6692a93` | docs(critical-paths): HA Pad 4 — MollieWebhookControllerTest added, 19 refs / 19 ok |
| 2026-04-21 | `8a7d8fa` | docs(critical-paths): HA Pad 5 — TenantMiddlewareTest added, 18 refs / 18 ok |
| 2026-04-21 | `23a47ed` | docs(handover): 20/21-04 sessie — policy-shift + portfolio-brede audit-infra |
| 2026-04-21 | `9277a75` | docs(critical-paths): Studieplanner-api doc + SH-gap closed across 7 projects |
| 2026-04-21 | `0a5f07e` | docs(critical-paths): Infosyst + SafeHavun docs committed, SH-test gaps closed |
| 2026-04-21 | `9b9a7da` | docs(critical-paths): Infosyst critical-paths doc — 4 paths / 16 refs / 16 ok |
| 2026-04-21 | `1143a4d` | docs(critical-paths): HA security-headers gap closed — 6 paths / 17 refs / 17 ok |
| 2026-04-21 | `de73cb2` | docs(critical-paths): HavunAdmin critical-paths doc |
| 2026-04-21 | `24ff41b` | docs(critical-paths): Herdenkingsportaal critical-paths doc |
| 2026-04-21 | `419a258` | feat(critical-paths): multi-project support + JT critical-paths doc |
| 2026-04-21 | `713d565` | test(session): guard session-cookie defaults + rescope Pad 6 |
| 2026-04-21 | `d4f280a` | docs(critical-paths): align doc with tests that actually exist |
| 2026-04-20 | `2058ca8` | refactor(critical-paths): collapse glob matches into single test run |
| 2026-04-20 | `36cfcbf` | feat(audit): critical-paths:verify command (MPC fase 3) |
| 2026-04-20 | `7f6dd3a` | docs(mpc-fase-2): implementatieplan critical-paths:verify |
| 2026-04-20 | `b759bd4` | docs(mpc-fase-1): spec `critical-paths:verify` command |
| 2026-04-20 | `fc68fde` | docs(kb): coverage-padding sanitization runbook + pilot learnings |
| 2026-04-20 | `13f05d6` | policy: zinvolle tests op kritieke paden > coverage-padding |
| 2026-04-20 | `da91142` | docs(handover): HP VP-17 bunq test-repair summary |
| 2026-04-20 | `e5e5598` | docs(handover): K&V draad + Studieplanner 80% + JT PR #2 merged |
| 2026-04-20 | `1c73d44` | fix(qv): treat if/catch-guarded markTestSkipped as defensive |
| 2026-04-20 | `59cc0cb` | docs: correct spelling Mawin (not Marwin) in noodcontact section |
| 2026-04-20 | `f976cdb` | refactor(qv): extract LatestRunFinder + cache dashboard reads |
| 2026-04-20 | `4a91b93` | feat(observability): expose qv:scan findings via dashboard API |
| 2026-04-20 | `22bfe2d` | fix(qv): correct live URLs for judotoernooi + studieplanner |
| 2026-04-20 | `e67a0d2` | fix(qv): Mozilla Observatory v2 API expects host as querystring |
| 2026-04-20 | `380b3d9` | docs(kb): MPC Fase 2 — plan JT coverage push met alleen zinvolle tests |
| 2026-04-20 | `24fc043` | docs(kb): MPC Fase 1 — zinvolle-tests pattern (geen coverage-padding) |
| 2026-04-20 | `2a0058b` | docs(handover): JT coverage push stand — 17 testbestanden, 119 tests |
| 2026-04-20 | `43bbdd5` | docs(handover): coverage push results + cross-project baseline |
| 2026-04-20 | `2d193ee` | refactor(tests): bulk-insert metric seeds + cache-flush isolation |
| 2026-04-20 | `0621e55` | test(coverage): autofix api + observability:baseline + observability:aggregate |
| 2026-04-20 | `a833d50` | test(coverage): chaos experiments — 1 smoke + 1 deep coverage |
| 2026-04-20 | `8080abe` | docs(handover): finale coverage-stats + tests-deze-nacht overzicht |
| 2026-04-20 | `197ea01` | test(coverage): ChaosResult + MetricsAggregated model tests |
| 2026-04-20 | `f8bfef6` | test(coverage): VaultProject + ClaudeTask model tests |
| 2026-04-20 | `bdc1d1b` | test(coverage): 4 model tests — AutofixProposal + VaultSecret + VaultAccessLog + SlowQuery |
| 2026-04-20 | `c007849` | test(coverage): AuthDevice model — token, hash, lookup, trust-extend, revoke |
| 2026-04-20 | `12ddec1` | docs(handover): vervolg-werk nacht 20-04 vroeg + eerlijke coverage-status |
| 2026-04-20 | `e9c5be5` | test(coverage): AIProxyService — chat + rate-limit + usage-stats + health |
| 2026-04-20 | `e211c0a` | test(coverage): ObservabilityService — dashboard aggregatie + project filter |
| 2026-04-20 | `015bea0` | test(coverage): DeviceTrustService — bearer-token + revoke + logout flows |
| 2026-04-20 | `fc7f45f` | test(coverage): CircuitBreaker + PostcodeService Unit-tests |
| 2026-04-20 | `08c1c2c` | docs(kb): qv:scan auto-rapport — finale eindstaat 20-04 nachtwerk |
| 2026-04-20 | `95fee13` | fix(qv): composer/npm silently skip server-only entries (no `path`) |
| 2026-04-20 | `1f65bc1` | feat(qv): secrets check — 4 extra provider patterns (OpenAI/Sentry/DO/HF) |
| 2026-04-20 | `dd987b0` | docs: handover — 19/20-04 K&V uitbreiding + security hardening + VP-17 reconstructie |
| 2026-04-20 | `334136d` | feat(qv): debug-mode check — voorkom APP_DEBUG=true leak in productie |
| 2026-04-19 | `8743c9d` | refactor(qv): test-erosion heuristic — onderscheid unconditional vs defensive skips |
| 2026-04-19 | `6d9717b` | feat(qv): test-erosion check — VP-17 preventie cross-project |
| 2026-04-19 | `6088662` | feat(qv): session-cookies check — verifieert Laravel session-cookie security |
| 2026-04-19 | `9da22ba` | refactor(qv): walkSourceFiles() generator — DRY tree-walk across checks |
| 2026-04-19 | `11a57f4` | feat(qv): secrets-detection check — provider-prefixed credential leak scan |
| 2026-04-19 | `e36132a` | ci(coverage): hard minimum 50 % drempel — sluit handover-item |
| 2026-04-19 | `babc395` | docs(kb): qv:scan auto-rapport — eindstaat sessie 19-04-2026 |
| 2026-04-19 | `247a016` | refactor(security): TrustProxies + DRY userOrIp() — sluit nginx-IP-spoofing gap |
| 2026-04-19 | `15b6b8a` | feat(security): rate-limit auth + token endpoints — sluit brute-force gap |
| 2026-04-19 | `868deb8` | refactor(qv): extract laravelRootOrNull() — DRY scanner preamble |
| 2026-04-19 | `8d8f523` | feat(qv): rate-limit coverage check — boolean heuristic |
| 2026-04-19 | `8cf8000` | refactor(qv): forms-coverage heuristic — count validateWithBag too |
| 2026-04-19 | `cb3dcb6` | refactor(security): admin middleware — skip duplicate user fetch + close enumeration oracle |
| 2026-04-19 | `94f92bb` | feat(security): admin.token middleware sluit Vault admin endpoints (CVSS-high gap dicht) |
| 2026-04-19 | `dbf28d5` | docs: add online test sites table (Mozilla, SecurityHeaders.com, SSL Labs) |
| 2026-04-19 | `cae9258` | feat(security): FormRequest migration — Vault + QrAuth (HavunCore 47% → 60%+) |
| 2026-04-19 | `6dc5b52` | refactor(qv): forms-coverage — single tree walk + cap at 100 % |
| 2026-04-19 | `361c872` | feat(qv): forms-coverage check — heuristic FormRequest-vs-write-routes audit |
| 2026-04-19 | `61c5e03` | docs(kb): poort-register — Laravel-via-socket sectie + Munus + geparkeerde projecten |
| 2026-04-19 | `4dd06dc` | docs(kb): poort-register — frontend/backend dev mismatch + Laravel parallel-port conventie |
| 2026-04-19 | `da096a7` | docs(kb): poort-register — single source of truth voor TCP-poorten |
| 2026-04-19 | `3a5bcd1` | docs(runbook): PM2 op productie als www-data (least-privilege) |
| 2026-04-19 | `7f32ab4` | refactor(qv): server-health parsers — splitLines helper + dedup trim/split |
| 2026-04-19 | `f258aa5` | feat(qv): server health check — disk usage + failed systemd units via SSH |
| 2026-04-19 | `d2cab57` | fix(qv): observatory severity — critical only for D/F, not unranked grades |
| 2026-04-19 | `bd89552` | feat(qv): Mozilla Observatory check — grade-based findings |
| 2026-04-19 | `4add830` | refactor(qv): simplify qv:log — bounded lookup, renderTable helper, safer I/O |
| 2026-04-19 | `71d24ba` | feat(qv): qv:log command — render latest scan as KB markdown report |
| 2026-04-19 | `62765cc` | docs: handover — 19-04 K&V-systeem session notes |
| 2026-04-19 | `c1b5f6b` | refactor(qv): simplify scanner — drop dead code, extract decoder, auto-detect manifests |
| 2026-04-19 | `31ff7df` | feat(qv): K&V-systeem — cross-project quality & safety scanner |
| 2026-04-19 | `098886d` | docs: KB — log HP git-history secrets + SP/SH CSP on*= scope |
| 2026-04-18 | `27a58e9` | chore: gitignore *.env typo-filenames |
| 2026-04-18 | `19b95a6` | docs: deploy-keys — rotate Studieplanner-api RW → RO + correct HavunClub |
| 2026-04-18 | `019f5ca` | docs: central deploy-key management — runbook + inventory |
| 2026-04-18 | `1f2054f` | docs: KB — add memory_limit trap to runtime-vs-static-time family |
| 2026-04-18 | `d232616` | docs: ADR mollie-vs-bunq + runtime-audit category #5 closed |
| 2026-04-18 | `7fbf17a` | docs: KB — runtime vs static-time anti-pattern family + baseline audit |
| 2026-04-18 | `175e2ba` | docs(patterns): runtime-vs-static pitfalls runbook |
| 2026-04-18 | `bac53e1` | docs: add WhatsApp step after /rc for noodcontact protocol |
| 2026-04-18 | `c4c6119` | docs: add noodcontact greeting protocol for Thiemo & Marwin |
| 2026-04-18 | `589e7ba` | docs: KB — systematic logging of security findings + frontend gotchas |
| 2026-04-18 | `b069438` | docs: ggshield-setup — add all 10 repos + audit command |
| 2026-04-18 | `74fd820` | chore: gitignore storage/framework + storage/logs |
| 2026-04-18 | `18c58f1` | docs: session handover 2026-04-14/18 — webapp fixes + Munus setup |
| 2026-04-18 | `2502fdc` | docs(ggshield): add Windows-without-Python recovery steps |
| 2026-04-17 | `deccd60` | docs: switch from quarterly audit to weekly incremental cadence |
| 2026-04-17 | `ffeb3fe` | docs: VP-18 status reflects unsafe-eval removal landed |
| 2026-04-17 | `923a38e` | feat: complete VP-01, VP-03, VP-16 of Q2 2026 improvement plan |
| 2026-04-17 | `0df0d75` | docs: VP-18 status → feature-branch-ready (22 batches) |
| 2026-04-16 | `44447a4` | docs: Alpine CSP migratie runbook (9 conversie-patronen) |
| 2026-04-16 | `40d0520` | docs: VP-18 status update — 60/61 inline + 1/31 function-call |
| 2026-04-16 | `2b0f390` | docs: VP-18 — JudoToernooi Alpine CSP migration in progress |
| 2026-04-16 | `1975892` | docs: VP-11 — full PHPUnit suite confirms zero regressions |
| 2026-04-16 | `039427b` | docs: VP-16 ready for first quarterly cron + audit summary updated |
| 2026-04-16 | `571886f` | docs: VP-11 status — feature branch ready for staging-test |
| 2026-04-16 | `1faf8fb` | docs: VP-11 status partial — Alpine CSP migration substantial progress |
| 2026-04-16 | `de80064` | docs: VP-14 done — all 9 projects have CONTRACTS.md |
| 2026-04-16 | `a2ade8d` | docs: VP-14 — HavunCore CONTRACTS.md + template + CLAUDE.md cross-link |
| 2026-04-16 | `c7ed081` | docs: VP-16 partial — mutation testing runbook + status update |
| 2026-04-16 | `33041ff` | docs: VP-15 done — formal deploy authority during owner absence |
| 2026-04-16 | `c050dc8` | refactor: VP-13 simplify — config-driven schedule + drift detection |
| 2026-04-16 | `d67777f` | feat: VP-13 — quarterly emergency-protocol dry-run reminder system |
| 2026-04-16 | `54e83cc` | docs: VP-12 done — /end command now includes doc-sync check |
| 2026-04-16 | `92d31ce` | docs: VP-17 done + master implementation plan for VP-11..VP-17 |
| 2026-04-16 | `eb22f99` | docs: address Claude contra-review v3.0 — AI as risk + 4 new VPs |
| 2026-04-16 | `813aa85` | docs: record Gemini v3.0 review (9.8/10) + add VP-12 & VP-13 |
| 2026-04-16 | `b93e739` | docs: update Herdenkingsportaal coverage to 85.94% + payment-module breakdown |
| 2026-04-16 | `35910db` | docs: VP-02 complete — Herdenkingsportaal coverage 85.75% |
| 2026-04-16 | `23250d8` | docs: sharpen Herdenkingsportaal coverage target to 85% |
| 2026-04-16 | `9ec0904` | docs: consolidate coverage tables — single source of truth |
| 2026-04-16 | `be00adb` | docs: archive historical werkwijze v1.0 snapshot |
| 2026-04-16 | `c195fcf` | docs: fix coverage inconsistencies across all audit/kb docs |
| 2026-04-16 | `6d51aba` | docs: update all coverage numbers from live measurements 16-04-2026 |
| 2026-04-16 | `0554d08` | docs: update werkwijze-beoordeling v3.0 for third-party review |
| 2026-04-16 | `d340171` | docs: expand emergency werkplan — /start, /rc, WhatsApp flow + add Mawin |
| 2026-04-16 | `346cf44` | docs: VP-07 complete — droogtest met Thiemo afgerond 16-04-2026 |
| 2026-04-16 | `b0cf212` | docs: update VP-02 test count (795t) + VP-06 status (all 9 projects) |
| 2026-04-15 | `cfe00eb` | test: add 17 tests for IntegrityCheckCommand + update cross-references |
| 2026-04-15 | `312a949` | refactor: clean up IntegrityCheckCommand after simplify review |
| 2026-04-15 | `559f13f` | feat: VP-05/06/07 — integrity check v2.0, protocol rules, Thiemo werkplan |

## V&K status (laatste qv:scan)

_Nog geen `qv:scan` snapshot beschikbaar._

## Verdiepende bronnen

- **Architectuur V&K:** `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md`
- **Kritieke paden + MSI gates:** `docs/kb/reference/critical-paths-havuncore.md`
- **Mutation-test setup:** `docs/kb/runbooks/infection-setup-plan.md`
- **qv:scan snapshot:** `docs/kb/reference/qv-scan-latest.md`
- **Findings auto-log:** `docs/kb/reference/security-findings-log.md`
- **Findings curated:** `docs/kb/reference/security-findings.md`

