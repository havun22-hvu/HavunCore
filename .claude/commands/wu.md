# /wu — Werkzaamheden Urenregistratie

Genereer een beknopte omschrijving van de dagwerkzaamheden voor de urenadministratie.

## Werkwijze

1. Haal git commits van vandaag op:
```bash
git log --oneline --after="yesterday" --format="%s"
```

2. Combineer met wat er in deze sessie is gedaan.

3. Schrijf de output in dit formaat — niets meer, niets minder:

---

**[DD-MM-JJJJ] — [Projectnaam]**
[Omschrijving in lopende tekst, alles achter elkaar, geen bullets, geen enters.]

---

## Regels

- Zakelijke taal, begrijpelijk voor de belastingdienst
- Werkzaamheden achter elkaar gescheiden door een puntkomma of punt
- Geen technisch jargon ("refactor", "commit", "PR") — gebruik gewone taal
- Max 2 zinnen
- Gericht op WAT er gedaan is, niet HOE

## Voorbeelden

**Goed:**
12-05-2026 — HavunCore: Gedragsregels voor samenwerking met AI-assistent gedocumenteerd en uitgerold naar alle projecten; handover bijgewerkt.

**Fout (bullets):**
- Gedragsregels toegevoegd
- Handover bijgewerkt
- Commits gepusht

**Fout (te technisch):**
Added ⛔ block to /start /mpc /end SKILL.md files, committed 63 files, pushed to origin master.
