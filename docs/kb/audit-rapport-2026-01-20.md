# Documentatie Audit Rapport - Alle Havun Projecten

> **Datum:** 20 januari 2026
> **Scope:** 7 projecten, ~272 markdown bestanden
> **Doel:** Volledigheid, consistentie, obsolete info, structuur

---

## Samenvatting

| Project | MD Files | Score | Status |
|---------|----------|-------|--------|
| HavunCore | 58 | 6.4/10 | Onderhoud nodig |
| HavunAdmin | 69 | 6/10 | Versie sync nodig |
| Herdenkingsportaal | 60 | 7/10 | Package docs conflict |
| Infosyst | 18 | 6/10 | Training docs vaag |
| Studieplanner | 20 | 6.9/10 | UUID docs verouderd |
| SafeHavun | 20 | 7.5/10 | Changelog ontbreekt |
| JudoToernooi | 27 | 7.5/10 | Duplicate handovers |

**Gemiddelde score: 6.8/10**

---

## Kritieke Issues per Project

### HavunCore

| Issue | Severity | Status |
|-------|----------|--------|
| Credentials in plain text (.claude/context.md) | CRITICAL | Te fixen |
| Duplicate workflow docs (3 bestanden) | HIGH | Consolideren |
| Verouderde "Laatste Sessie" sections | MEDIUM | Opschonen |
| Ontbrekende project docs (5+ projecten) | MEDIUM | Toevoegen |

**Bestanden:**
- `.claude/context.md` - Credentials moeten naar Vault referentie
- `docs/kb/claude-workflow-enforcement.md` - Consolideren met `runbooks/claude-werkwijze.md`

### HavunAdmin

| Issue | Severity | Status |
|-------|----------|--------|
| Versie mismatch (v0.7.0 vs v0.8.0) | CRITICAL | Te fixen |
| Compliance score niet gesync (81% vs 97%) | CRITICAL | Te fixen |
| Credentials exposed in README.md | CRITICAL | Te fixen |
| Staging URL inconsistent | HIGH | Standaardiseren |
| Duplicate 01-architecture directory | MEDIUM | Verwijderen |

**Bestanden:**
- `README.md` regel 3: v0.7.0 → v0.8.0
- `README.md` regel 224-236: Credentials verwijderen
- `docs/01-getting-started/PROJECT-STATUS.md` regel 122: 81% → 97%

### Herdenkingsportaal

| Issue | Severity | Status |
|-------|----------|--------|
| USER-LEVELS.md vs PACKAGE-TYPES.md conflict | CRITICAL | Deprecation notice |
| GASTENBOEK-FEATURE.md status verouderd | HIGH | Update naar IMPLEMENTED |
| README.md pricing verouderd | MEDIUM | Update naar 3-tier |

**Bestanden:**
- `docs/2-FEATURES/USER-LEVELS.md` - Deprecation header toevoegen
- `docs/2-FEATURES/GASTENBOEK-FEATURE.md` - Status updaten

### Infosyst

| Issue | Severity | Status |
|-------|----------|--------|
| Credentials in plain text (.claude/context.md) | CRITICAL | Te fixen |
| Training status onduidelijk | HIGH | Clarificeren |
| 35% duplicatie in docs | MEDIUM | Consolideren |
| Ontbrekende API/webhook docs | MEDIUM | Toevoegen |

**Bestanden:**
- `.claude/context.md` regel 23: MySQL password verwijderen

### Studieplanner

| Issue | Severity | Status |
|-------|----------|--------|
| UUID dependency docs verouderd | HIGH | Update tech-stack.md |
| API port inconsistentie (8000 vs 8003) | HIGH | Standaardiseren |
| Ontbrekende audit.md command | MEDIUM | Toevoegen |
| Features.md TODO checklist verouderd | MEDIUM | Updaten |

**Bestanden:**
- `.claude/docs/tech-stack.md` regel 64: uuid → expo-crypto
- `.claude/context.md` vs `CLAUDE.md`: port standaardiseren

### SafeHavun

| Issue | Severity | Status |
|-------|----------|--------|
| CHANGELOG.md is Laravel template | HIGH | Vervangen |
| Port configuratie onduidelijk | MEDIUM | Clarificeren |
| Ontbrekende Troubleshooting docs | MEDIUM | Toevoegen |
| Ontbrekende Security docs | MEDIUM | Toevoegen |

**Bestanden:**
- `CHANGELOG.md` - Vervangen met SafeHavun changelog

### JudoToernooi

| Issue | Severity | Status |
|-------|----------|--------|
| Duplicate handover bestanden | HIGH | Consolideren |
| Broken links (2-3) | HIGH | Fixen |
| Verouderde sessie logs | MEDIUM | Archiveren |
| Auth docs verspreid over 3 bestanden | MEDIUM | Consolideren |

**Bestanden:**
- `.claude/handover.md` - Verwijderen (gebruik root HANDOVER.md)
- `.claude/sessie-2026-01-03.md` - Archiveren
- `.claude/sessie-2026-01-04.md` - Archiveren

---

## Cross-Project Issues

### 1. Credentials Management

**Probleem:** 4 van 7 projecten hebben credentials in plain text in git-tracked bestanden.

| Project | Bestand | Type |
|---------|---------|------|
| HavunCore | .claude/context.md | DB, API keys, Mollie |
| HavunAdmin | README.md | Admin password, DB |
| HavunAdmin | docs/.../BUSINESS-INFO.md | Google Cloud |
| Infosyst | .claude/context.md | MySQL password |

**Oplossing:** Verwijs naar HavunCore Vault of gebruik `[ZIE .env]` placeholder.

### 2. Workflow Documentation Chaos

**Probleem:** Meerdere projecten hebben conflicterende workflow docs.

- HavunCore: 5 verschillende workflow bestanden
- Andere projecten verwijzen naar HavunCore maar hebben ook eigen versies

**Oplossing:**
1. HavunCore = single source of truth voor workflow
2. Andere projecten = alleen verwijzing, geen kopie

### 3. Versie Tracking

**Probleem:** Geen consistente versie tracking across projecten.

**Oplossing:** Elk project moet in CLAUDE.md bovenaan versie + laatste update datum hebben.

### 4. Ontbrekende Commands

**Probleem:** Niet alle projecten hebben complete `.claude/commands/` set.

| Project | start | end | md | kb | audit | update |
|---------|-------|-----|----|----|-------|--------|
| HavunCore | ✓ | - | ✓ | ✓ | ✓ | ✓ |
| HavunAdmin | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Herdenkingsportaal | ✓ | ✓ | ✓ | ✓ | ✓ | - |
| Infosyst | ✓ | - | - | - | - | - |
| Studieplanner | ✓ | - | ✓ | ✓ | - | ✓ |
| SafeHavun | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| JudoToernooi | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

---

## Aanbevelingen

### Prioriteit 1 (Deze week)

1. **Credentials cleanup** - Verwijder alle plain text credentials
2. **Versie sync** - HavunAdmin README → v0.8.0
3. **Deprecation notices** - Herdenkingsportaal USER-LEVELS.md

### Prioriteit 2 (Deze maand)

1. **Workflow consolidatie** - HavunCore als single source
2. **Missing commands** - Studieplanner audit.md, etc.
3. **Duplicate cleanup** - JudoToernooi handovers, HavunAdmin 01-architecture

### Prioriteit 3 (Doorlopend)

1. **Versie tracking** - Consistente format alle projecten
2. **Cross-reference validation** - Automated link checking
3. **Regular audits** - 2x per maand

---

## Volgende Audit

**Geplande datum:** 3 februari 2026
**Focus:** Verificatie fixes + nieuwe issues

---

*Rapport gegenereerd door Claude Code audit*
