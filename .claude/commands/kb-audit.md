# KB Audit — documentatie-review

> Review alle markdown-docs in dit project op obsolete info, overlap,
> inconsistenties en structuur. Werk de KB bij met kant-en-klare
> verwijder-commando's voor batch-approval.

## Uit te voeren

### 1. Mechanische scan (delegeer aan artisan indien beschikbaar)

HavunCore heeft een `docs:audit` artisan command dat obsolete/broken-link/
structure/zombie-checks doet. Als dit project zelf Laravel is:

```bash
php artisan list 2>/dev/null | grep -q "docs:audit" && \
  php artisan docs:audit --json > /tmp/docs-audit.json
```

Anders via centraal HavunCore (slug moet matchen met
`config/quality-safety.php` in HavunCore):

```bash
cd D:/GitHub/HavunCore && php artisan docs:audit --project=<slug> --json
```

Geen artisan beschikbaar → sla over; Claude doet alles handmatig.

### 2. Claude deep review (semantic checks)

Wat de artisan NIET kan, doe jij:

#### Overlap-detectie
Lees alle MD-files in `docs/` en `.claude/`. Voor elk paar:
- Zelfde frontmatter `title:` → markeer als **HIGH** duplicate
- >50% overlap in H2/H3-headers → markeer als **MEDIUM** overlap
- Belangrijk: overlap ≠ duplicatie. Business-vs-technisch perspectief op
  hetzelfde onderwerp is **OK**. Alleen écht-dupliceren van content melden.

#### Referentie-integriteit — 6 Onschendbare Regels

Check `.claude/rules.md` (als aanwezig) tegen HavunCore's canonical set in
`D:/GitHub/HavunCore/CLAUDE.md` (regels 13-18). Deze 6 regels moeten
consistent zijn cross-project:

1. NOOIT code schrijven zonder KB + kwaliteitsnormen te raadplegen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor én na wijzigingen
5. ALTIJD toestemming vragen bij grote wijzigingen
6. NOOIT een falende test "fixen" door de assertion te wijzigen (VP-17)

Afwijkingen (gewijzigde tekst, ontbrekende regels, geparafraseerd) → **HIGH**.
Extra project-specifieke regels mogen, zolang de 6 kern overeind blijven.

#### Cross-doc inconsistenties
- **Versienummers**: `composer.json`/`package.json` is canonical
- **Poorten**: `docs/kb/reference/poort-register.md` is canonical
- **Paden/URLs**: `.claude/context.md` is canonical
- **MSI-gates**: `docs/kb/reference/critical-paths-*.md` is canonical

Bij verschil doc-vs-canonical: **HIGH**, voorstel om doc te updaten.

### 3. Combineer tot rapport

Merge artisan-output (indien aanwezig) + eigen findings. Groepeer per severity.

### 4. Batch-approval commands

Aan het eind van het rapport: kant-en-klaar bash-blok met `rm`/`mv`
commando's voor weggooi-kandidaten (alleen CRIT/HIGH obsolete/zombie/
duplicate). Begint MET een safety-guard:

```bash
git status --porcelain | grep -q . && { echo "Working tree not clean — abort"; exit 1; }
# Obsolete (last_check > 24 maanden):
rm "docs/kb/old-stuff.md"
# Duplicate (canonical = other-file.md):
rm "docs/kb/duplicate-v1.md"
```

Jij scant de lijst. Als akkoord: **typ "Uitvoeren"** en ik run het blok.

### 5. KB bijwerken

Schrijf rapport naar `docs/kb/reference/kb-audit-latest.md` in het project.
Bij HIGH/CRITICAL: ook append naar `docs/kb/reference/kb-audit-log.md`
(historie — niet overschreven).

### 6. Direct fixen — alleen triviaal

**Mag ik zelf zonder vragen:**
- Typo's in frontmatter (title-case, spelling)
- `last_check:` datum updaten (alleen als content onveranderd blijft)
- Broken relative links waar target 1-op-1 te herleiden is
- Lege sections verwijderen (alleen ##-headers zonder body)

**NIET zelf — altijd voorstel aan gebruiker:**
- Inhoudelijke herschrijving
- Samenvoegen van overlappende docs
- Verwijderen van hele files
- Rule-afwijkingen in `.claude/rules.md`

### 7. Samenvatting aan gebruiker

- Totalen per severity
- Top-3 acties die beoordeling vragen
- Link naar batch-approval blok (indien aanwezig)
- Link naar `docs/kb/reference/kb-audit-latest.md` voor details

## Frequentie

- **Maandelijks** standaard (parallel aan V&K maandelijkse cron)
- **Voor releases** — extra audit-ready check
- **Na KB-refactor** — verifieer cross-links

## Bronnen

- `docs/kb/runbooks/kwaliteit-veiligheid-systeem.md` — V&K architectuur (HavunCore)
- `CLAUDE.md` — 6 Onschendbare Regels (canonical)
- `docs/kb/reference/havun-quality-standards.md` — enterprise normen
