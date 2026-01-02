# MD File Audit Command

Voer een audit uit op alle MD bestanden in alle Havun projecten.

## Procedure

Volg de stappen in `docs/kb/runbooks/md-file-audit.md`:

### 1. Scan alle projecten

```
D:\GitHub\HavunCore
D:\GitHub\HavunAdmin
D:\GitHub\Herdenkingsportaal
D:\GitHub\Judotoernooi
D:\GitHub\infosyst
D:\GitHub\Studieplanner
D:\GitHub\SafeHavun
D:\GitHub\Havun
D:\GitHub\VPDUpdate
```

### 2. Per project checken

- [ ] CLAUDE.md bestaat en is max 60-80 regels?
- [ ] CLAUDE.md bevat LEES-DENK-DOE-DOCUMENTEER sectie?
- [ ] .claude/context.md bestaat en is actueel?
- [ ] Geen dubbele info (ook in HavunCore)?
- [ ] Verwijzingen kloppen?
- [ ] Logische structuur?

### 3. Standaard format controleren

**CLAUDE.md moet bevatten:**
- Type/Framework header
- LEES-DENK-DOE-DOCUMENTEER sectie
- Forbidden without permission
- Quick Reference tabel
- Knowledge Base verwijzing

**context.md moet bevatten:**
- Features/functionaliteit
- Database info (indien relevant)
- Veelvoorkomende taken

### 4. Fixes doorvoeren

| Type | Actie |
|------|-------|
| Typos, formatting | Direct fixen |
| Verouderde info | Direct updaten |
| Structuur aanpassen | Direct fixen |
| Inhoudelijke twijfel | Overleggen |
| Technische keuzes | Zelf oplossen |
| Grote wijzigingen | Eerst bespreken |

### 5. Commit per project

- Commit message: `docs: MD file audit [datum]`
- Push naar remote

### 6. Update audit log

In `docs/kb/runbooks/md-file-audit.md`:
- Voeg datum toe aan Audit Log tabel
- Noteer gevonden issues en status
- Update volgende audit datum

### 7. Rapporteer aan gebruiker

- Lijst van gecontroleerde projecten
- Gevonden en opgeloste issues
- Openstaande vragen (indien van toepassing)
