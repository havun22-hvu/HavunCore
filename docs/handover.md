# Handover (auto-generated)

> **Auto-gegenereerd door `php artisan docs:handover`** op Tue, Aug 4, 2026 4:00 AM.
> Bewerk dit bestand niet handmatig — wijzigingen worden overschreven.
> Voor session-detail zie `.claude/handover.md`. Voor V&K-architectuur zie
> `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md`.

## Recente activiteit (laatste 7 dagen)

| Datum | Hash | Bericht |
|-------|------|---------|
| 2026-08-04 | `831080c` | docs: session close 04-08 -- monitoring that measures, and the pattern behind it |
| 2026-08-04 | `bca2060` | chore(monitoring): stop watching a site that was taken down on purpose |
| 2026-08-04 | `b7ab20f` | feat(actions): blind monitoring reaches a person, not /dev/null |
| 2026-08-04 | `219131c` | fix(actions): a repository the API will not hand over is not a clean one |
| 2026-08-04 | `ec130f3` | fix(actions): the per-repo SSH aliases made the watcher blind |
| 2026-08-04 | `5f0de59` | fix(actions): watch the builds without gh, and say so when it watched nothing |
| 2026-08-03 | `aaf27f0` | docs(handover): serverHealth measures again, and one flaky test |
| 2026-08-03 | `6e9247b` | fix(qv): a command for this machine does not go over SSH |
| 2026-08-03 | `b09bdcc` | docs(handover): the backup fix is live, not waiting |
| 2026-08-03 | `9ac4253` | docs(handover): the scan measures on the server now, and what it found |
| 2026-08-03 | `dc9335f` | fix(qv): a project that does not belong here is skipped, not an error |
| 2026-08-03 | `38a0204` | docs(handover): back under the line limit |
| 2026-08-03 | `3148ff9` | docs(handover): what the scan found once it could see |
| 2026-08-03 | `624cf12` | fix(qv): the two registries spell the server path differently |
| 2026-08-03 | `64349a9` | fix(qv): the code checks look where the code actually is |
| 2026-08-03 | `38e58c0` | docs(qv): the scan runs as root, not as www-data |
| 2026-08-03 | `f18e72e` | chore(qv): drop LatestRunFinder, and record what the scan does not measure |
| 2026-08-03 | `e7cd10c` | fix(qv): the report reads every check, not whichever ran last |
| 2026-08-03 | `74bf040` | refactor(qv): one definition of what a backup measurement is |
| 2026-08-03 | `c187221` | fix(qv): the backup check measures again where it actually runs |
| 2026-08-03 | `49dd4fb` | test(qv): the backup check must judge its own measurement |
| 2026-08-03 | `8f94cab` | chore(auto): refresh handover, qv-scan-latest (2026-08-03T06:00:09+02:00) |
| 2026-08-03 | `95447d6` | docs: session handover 2026-08-03 |
| 2026-08-02 | `9d06268` | docs(qv): the nightly backup check has been measuring nothing |
| 2026-08-02 | `14a8d1f` | docs(handover): back under the line limit |
| 2026-08-02 | `5ebbfdb` | docs(secrets): the monitoring PAT is replaced, and lasts a year now |
| 2026-08-02 | `02dc72c` | feat(secrets): rotate a database password without it ever reaching a transcript |
| 2026-08-02 | `93616f6` | docs(handover): the leaked havunadmin database password is dead |
| 2026-08-02 | `a33d89f` | chore(docs): sync the auto-generated snapshots from production |
| 2026-08-02 | `f06a4dd` | docs(handover): what is waiting to deploy, and one rescue branch |
| 2026-08-02 | `5247c0d` | docs: session handover 2026-08-01 |
| 2026-08-02 | `0036619` | docs: one source for the inviolable rules, six of them |
| 2026-08-01 | `5b3f5c7` | fix(docs-audit): thirty of the thirty-nine findings were the auditors own bugs |
| 2026-08-01 | `2e48f87` | docs(secrets): rotate the monitoring PAT without leaking it into shell history |
| 2026-08-01 | `81db54f` | docs: verify the HavunAdmin backup, and record a password I leaked |
| 2026-08-01 | `14615b7` | docs: a register for the databases, so two similar names cannot hide again |
| 2026-08-01 | `c2939d7` | feat(qv): ask the app which database it uses, not the list |
| 2026-08-01 | `79b02f1` | docs(handover): back under the line by removing what is done |
| 2026-08-01 | `c55264e` | chore: remove Vusista 1 -- it is not needed anymore |
| 2026-08-01 | `9db69f1` | docs: close out the deploys, the backup fixes and the vite false alarm |
| 2026-08-01 | `613f374` | fix(backup): back up what was missing, stop backing up what is gone |
| 2026-08-01 | `00a3336` | feat(qv): check that a backup was actually made, not that it was planned |
| 2026-08-01 | `1e40106` | chore(deps): close six guzzle advisories |
| 2026-08-01 | `d78b2ff` | docs(handover): vusista2 got the way of working, and a CI to prove it runs |
| 2026-08-01 | `63468be` | feat(qv): report the project that is missing from the scan list |
| 2026-08-01 | `d7d3084` | chore(docs): sync the auto-generated snapshots from production |
| 2026-07-31 | `7e4bf3c` | docs: session handover 31-07 + stop the stub duplicate-warning at its source |
| 2026-07-31 | `aadd034` | docs(kb): the session-cookie finding also sits in the live legacy app |
| 2026-07-31 | `cb75bb6` | chore(server): remove Veen from our server, leave Cees' server alone |
| 2026-07-31 | `c64b78b` | chore(kb): park Veen, and start scanning it for exactly that reason |
| 2026-07-31 | `70fd629` | docs(kb): the detour register existed as a rule but never as a file |
| 2026-07-31 | `524c874` | fix(qv): test-erosion reported a clean zero where it had measured nothing |
| 2026-07-31 | `99a563c` | docs(kb): Vusista 1 stays until the rebuild is finished |
| 2026-07-31 | `69ef389` | chore(kb): Vusista 1 is reference material now, so stop scanning it |
| 2026-07-31 | `7bd169b` | docs(handover): back near the line limit, and two rows the cleanup made false |
| 2026-07-31 | `bc65245` | feat(monitoring): a red Action reaches someone instead of nobody |
| 2026-07-31 | `747eac9` | chore(server): remove the Vusista server environment, and the registries with it |
| 2026-07-31 | `fb46ee7` | docs(kb): close out the per-stack V&K plan, and correct its point 5 |
| 2026-07-31 | `652eee4` | feat(qv): a skipped check says so instead of passing for clean |
| 2026-07-31 | `b80a7d2` | feat(qv): the scanner learns how a project is built, and never fakes a zero |
| 2026-07-31 | `23fd5cf` | chore(kb): Vusista2 has a remote now, and the plan says why that is not a deploy |
| 2026-07-31 | `2f248f7` | chore(config): register vusista2 before it repeats the same disappearance |
| 2026-07-30 | `cdc52f7` | docs(claude): roll the foundation norm out to every active project |
| 2026-07-30 | `2cd628f` | chore(kb): register Vusista for scanning, and fold the two Vusista notes into one |
| 2026-07-30 | `f964bfe` | feat(scaffold): the project type is chosen, not inherited |
| 2026-07-30 | `e9245b2` | docs: Vusista lessons -- scaffold imposes a stack, five tasks |
| 2026-07-30 | `85b4033` | docs(kb): choosing a stack becomes a binding step, and detours get counted |
| 2026-07-30 | `4812cc6` | docs(kb): the stack becomes a decision instead of an inheritance |
| 2026-07-30 | `43fe4c2` | chore(docs): sync the auto-generated snapshots from production |
| 2026-07-30 | `5df7bbd` | docs(kb): add pattern for holding an evidence position on inherited software |
| 2026-07-29 | `407a75f` | docs(kb): add VPDUpdate to the mobile-login checklist, plus two findings |
| 2026-07-28 | `78cefc9` | chore(kb): refresh the audit snapshot (272 files, 1 critical) |
| 2026-07-28 | `ffcfe2a` | docs(kb): the SSL failure is Avast on the laptop, not the host |
| 2026-07-28 | `bd5a35f` | docs(kb): add magic link pattern for raw Node.js projects |

## V&K status (laatste qv:scan)

**Totals:** critical 0 | high 23 | medium 57 | low 7 | errors 0

_Snapshot timestamp: 2026-08-04T03:57:03+02:00_

**HIGH/CRITICAL findings:**

- **[HIGH]** `havunadmin/composer` — guzzlehttp/guzzle >=8.0.0,<8.0.1|<7.15.2 — Guzzle: Noncanonical host can bypass host-based checks
- **[HIGH]** `herdenkingsportaal/composer` — guzzlehttp/guzzle >=8.0.0,<8.0.1|<7.15.2 — Guzzle: Noncanonical host can bypass host-based checks
- **[HIGH]** `studieplanner-api/composer` — guzzlehttp/guzzle >=8.0.0,<8.0.1|<7.15.2 — Guzzle: Noncanonical host can bypass host-based checks
- **[HIGH]** `studieplanner-api/composer` — laravel/framework <12.60.0|>=13.0.0,<=13.9.0 — Laravel Framework: CRLF injection in default email rule 
- **[HIGH]** `studieplanner-api/composer` — phpunit/phpunit >=0,<8.5.52|>=9.0.0,<9.6.33|>=10.0.0,<10.5.62|>=11.0.0,<11.5.50|>=12.0.0,<12.5.8 — Unsafe Deserialization in PHPT Code Coverage Handling
- **[HIGH]** `studieplanner-api/composer` — symfony/http-kernel >=7.4.0,<7.4.12|>=8.0.0,<8.0.12 — CVE-2026-45075: HEAD Request Bypasses methods: ['GET'] Filter in #[IsGranted] / #[IsSignatureValid] / #[IsCsrfTokenValid]
- **[HIGH]** `studieplanner-api/composer` — symfony/mime >=2.0.0,<3.0.0|>=3.0.0,<4.0.0|>=4.0.0,<5.0.0|>=5.0.0,<5.1.0|>=5.1.0,<5.2.0|>=5.2.0,<5.3.0|>=5.3.0,<5.4.0|>=5.4.0,<5.4.52|>=6.0.0,<6.1.0|>=6.1.0,<6.2.0|>=6.2.0,<6.3.0|>=6.3.0,<6.4.0|>=6.4.0,<6.4.40|>=7.0.0,<7.1.0|>=7.1.0,<7.2.0|>=7.2.0,<7.3.0|>=7.3.0,<7.4.0|>=7.4.0,<7.4.12|>=8.0.0,<8.0.12 — CVE-2026-45067: Email Header / SMTP Command Injection via CRLF in Symfony\Component\Mime\Address
- **[HIGH]** `studieplanner-api/composer` — web-token/jwt-library <3.4.10|>=4.0.0,<4.0.7|>=4.1.0,<4.1.7 — RSA1_5 (RSAES-PKCS1-v1_5) decryption lacks implicit rejection, exposing a Bleichenbacher/Marvin padding oracle
- **[HIGH]** `studieplanner-api/composer` — web-token/jwt-library <3.4.10|>=4.0.0,<4.0.7|>=4.1.0,<4.1.7 — Chacha20Poly1305 key-encryption algorithm discards the Poly1305 authentication tag, performing no authentication on decryption
- **[HIGH]** `judotoernooi/composer` — guzzlehttp/guzzle >=8.0.0,<8.0.1|<7.15.2 — Guzzle: Noncanonical host can bypass host-based checks
- _… +13 meer (zie `docs/kb/reference/qv-scan-latest.md`)_

## KB audit (laatste wekelijkse run)

**Totals:** critical 3 | high 27 | medium 0 | low 9

_Zie `docs/kb/reference/kb-audit-latest.md` voor detail._

## Verdiepende bronnen

- **Architectuur V&K:** `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md`
- **Kritieke paden + MSI gates:** `docs/kb/reference/critical-paths-havuncore.md`
- **Mutation-test setup:** `docs/kb/runbooks/infection-setup-plan.md`
- **qv:scan snapshot:** `docs/kb/reference/qv-scan-latest.md`
- **Findings auto-log:** `docs/kb/reference/security-findings-log.md`
- **Findings curated:** `docs/kb/reference/security-findings.md`

