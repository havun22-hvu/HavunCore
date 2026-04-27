---
title: GitHub Actions budget-discipline
type: runbook
scope: havuncore
last_check: 2026-04-27
---

# GitHub Actions — budget-discipline

> **Free tier:** 2.000 minuten/maand voor alle repos onder `havun22-hvu` samen.
> **Lesgeld:** 27-04-2026, 100% verbruikt vóór maand-einde door HP-CI failures + doc-only commits.

## Principes

1. **Alleen essentieel op GitHub** — CI is een vangnet, geen primaire test-stage
2. **Tests primair lokaal** — pre-commit hooks + `php artisan test --no-coverage`
3. **Doc-commits triggeren NOOIT CI** — `paths-ignore` op alle workflows
4. **Geen parallelle runs** — `concurrency: cancel-in-progress`
5. **PR-only**, niet op push naar main — één run per merge, niet per commit
6. **Coverage / mutation niet bij elke run** — lokaal of weekly schedule
7. **Self-hosted runner overwegen** — onbeperkte minuten op Hetzner VPS (zie §Self-hosted)

## Standaard CI workflow-template

Plak dit in elke nieuwe `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  pull_request:
    branches: [main, master]
    paths-ignore:
      - 'docs/**'
      - '*.md'
      - '.claude/**'
      - 'kb/**'
      - 'README*'
      - 'CHANGELOG*'

concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true

jobs:
  test:
    runs-on: ubuntu-latest
    timeout-minutes: 10  # hard kill bij hangs
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, bcmath, intl, pdo_sqlite
          coverage: none  # PCOV trekt 2-3x meer minuten

      - name: Cache composer
        uses: actions/cache@v4
        with:
          path: ~/.composer/cache
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Composer audit
        run: composer audit --abandoned=report
        continue-on-error: true

      - name: Test
        run: php artisan test --no-coverage --parallel
```

## Mutation testing schedule (apart workflow)

Mutation = duur. Niet op elke push. Wekelijkse schedule of handmatig:

```yaml
name: Mutation Testing

on:
  schedule:
    - cron: '0 3 * * 1'  # maandag 03:00 UTC
  workflow_dispatch:

jobs:
  mutate:
    runs-on: ubuntu-latest
    timeout-minutes: 30
    steps:
      # … installatie …
      - run: vendor/bin/infection --threads=4 --min-msi=80
```

## Coverage workflow (alleen op verzoek)

Niet automatisch. Trigger via:

```yaml
on:
  workflow_dispatch:
  pull_request:
    types: [labeled]  # alleen wanneer label "run-coverage" wordt geplaatst
```

## Wat wel automatisch mag

- **Deploy workflows** — `workflow_dispatch` of op tag (`v*.*.*`), nooit auto-push
- **Dependabot Updates** — security PRs, kosten verwaarloosbaar
- **Issues / Pull Requests / Update Changelog** — geen compute, alleen GitHub API

## Self-hosted runner (Hetzner VPS)

Onbeperkte minuten, maar wel onderhoud:

1. Op de Hetzner VPS: `useradd -m gh-runner`
2. GitHub repo → Settings → Actions → Runners → "New self-hosted runner" (linux-x64)
3. Volg installatie-instructies (download + token), draai als `gh-runner`-user
4. Workflow: `runs-on: self-hosted` i.p.v. `ubuntu-latest`

**Veiligheid:**
- Runner heeft toegang tot je server — alleen vertrouwde repos
- Sandbox per job (Docker)
- Geen secrets in PR-context van fork-PRs (default GitHub-veilig)

## Budget-monitoring

Maandelijks (1e) controleren:

```bash
gh api user/settings/billing/actions
# → ingeval > 50% verbruikt vóór dag 15: workflows met meeste runs disablen
```

## Beleid bij kwartaal/incident

- **>80 % verbruikt vóór dag 20** → meeste workflows `gh workflow disable` tot reset
- **Repeat-failure-loop** (>5 opeenvolgende failures) → workflow disable + issue maken
- **Doc-only PR** → moet `paths-ignore` skippen, anders CI fixen niet de PR

## Auto-fix audit-script

```bash
# Periodieke audit: heeft elke actieve workflow paths-ignore + concurrency?
for repo in HavunCore HavunAdmin Herdenkingsportaal JudoToernooi Infosyst \
            SafeHavun HavunVet Studieplanner JudoScoreBoard havuncore-webapp; do
  gh api "repos/havun22-hvu/$repo/contents/.github/workflows" 2>/dev/null \
    | jq -r '.[].name' \
    | while read f; do
      content=$(gh api "repos/havun22-hvu/$repo/contents/.github/workflows/$f" \
        | jq -r '.content' | base64 -d)
      pi=$(echo "$content" | grep -c 'paths-ignore')
      cc=$(echo "$content" | grep -c 'cancel-in-progress')
      echo "$repo/$f: paths-ignore=$pi concurrency-cancel=$cc"
    done
done
```

Per workflow moeten beide getallen ≥ 1 zijn.

## Mei-aanzet plan

Na 1 mei (reset 2.000 min):

1. **Week 1** — alleen HP CI weer aan met dit template (paths-ignore, PR-only, no-coverage). Monitor verbruik.
2. **Week 2** — Studieplanner + HavunVet idem aanzetten.
3. **Week 3** — overige projecten één voor één.
4. **Week 4** — mutation-test schedule (1x/week) overwegen voor critical-paths-projecten.

Doel: **< 500 minuten/maand** voor de hele portfolio na hervatting.

## Bron

- Incident 27-04-2026: 100% van 2.000 min cap verbruikt
- HP-CI faalde 191x in april (84% failure rate) → ~50% van budget alleen voor HP
- Memory: `project_github_actions_budget_2026_04.md`
