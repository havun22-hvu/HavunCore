# Noodprotocol — Thiemo & Mawin

> **Doel:** Als Henk langere tijd niet bereikbaar is, zorgen dat de systemen blijven draaien.
> **Realiteit:** Henk kan overal ter wereld inloggen (laptop, telefoon, Claude). Dit protocol is alleen voor het scenario dat Henk echt niet kan (ziekenhuis, etc.).

---

## Wat je moet weten

- De server draait zelfstandig — websites blijven gewoon online
- AutoFix herstelt automatisch de meeste fouten
- Health check stuurt alerts als iets down gaat
- **Jouw enige taak:** de PC thuis aan houden als dat nodig is

---

## Scenario 1: PC staat uit en Henk is niet bereikbaar

1. Zet de PC aan en log in
2. De server en websites draaien NIET op deze PC — die staan in een datacenter en blijven gewoon werken
3. Deze PC is alleen nodig als je via Claude Code iets wilt checken of herstellen

---

## Scenario 2: Je wilt checken of alles werkt

### VS Code openen

1. Zoek op het bureaublad of in het Start-menu:

   <img src="../../audit/vscode-icon.png" width="48" alt="VS Code icoon"/> **Visual Studio Code**

2. Open HavunCore:
   - **Optie A:** Klik links in de zijbalk op het **Project Manager** icoon (4e van onderen, twee mapjes boven elkaar) en kies **HavunCore**
   - **Optie B:** **File → Open Folder** → `D:\GitHub\HavunCore` → **Selecteer map**

### Claude Code starten

1. Open de terminal: klik onderaan op **Terminal** (of `Ctrl + ~`)
2. Typ: `claude` en druk Enter
3. Typ:

```
Check de status van alle Havun websites en vertel me of er problemen zijn.
```

4. Claude checkt alles en vertelt je wat er aan de hand is
5. Als Claude vraagt of hij iets mag doen: **ja** = doorgaan, **stop** = stoppen

---

## Scenario 3: Alert e-mail ontvangen

Je krijgt een e-mail met `[ALERT]` in het onderwerp. Dit betekent dat een website niet bereikbaar is.

**Meestal lost het zichzelf op.** Check na 10 minuten of je nog een e-mail krijgt. Zo niet: probleem is al opgelost.

Als het aanhoudt: open Claude Code (zie boven) en typ:

```
Ik heb een alert ontvangen. Check wat er aan de hand is en los het op.
```

---

## Regels

1. **Geen paniek** — downtime is normaal en meestal tijdelijk
2. **Claude helpt** — je hoeft niks te weten, vraag het gewoon
3. **Bij twijfel: stop** — liever niks doen dan iets kapot maken
4. **Probeer Henk te bereiken** — bel, WhatsApp, SMS

## Wat mag jij wel/niet doen zonder Henk?

→ Zie [`wat-mag-noodcontact.md`](wat-mag-noodcontact.md) — drie scenario's (A: Henk bereikbaar, B: Henk weg <24u + site down, C: Henk weg >24u + security) met cheat-sheet welke commando's veilig zijn en welke niet.

---

## Belangrijke info

| Wat | Waarde |
|-----|--------|
| **Henk's e-mail** | havun22@gmail.com |
| **Websites** | herdenkingsportaal.nl, judotoernooi.havun.nl |
| **Hosting** | Hetzner (datacenter, niet deze PC) |

---

## Droogtest (1x samen doen, kost 2 minuten)

- [x] VS Code openen (afgetekend 16-04-2026)
- [x] Terminal openen, `claude` typen
- [x] Typ: "Check de status van alle Havun websites"
- [x] Lees het antwoord
- [x] Typ: `/exit` om te stoppen

---

*Aangemaakt: 29 maart 2026 — VP-07*
