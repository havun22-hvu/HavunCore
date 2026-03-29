# Noodprotocol — Voor Backup-Contactpersoon

> **Voor:** Backup-contactpersoon
> **Doel:** Als Henk niet bereikbaar is en een website down is, kun je dit protocol volgen.
> **Vereiste kennis:** VS Code kunnen openen — de rest staat hier.

---

## Wanneer gebruik je dit?

- Papa is ziek, op reis, of niet bereikbaar
- Je krijgt een e-mail met "[ALERT]" in de onderwerpregel
- Of iemand meldt dat een website niet werkt

---

## Stap 1: VS Code openen

1. Open **VS Code** (blauw icoon op bureaublad of Start-menu)
2. Klik op **File → Open Folder**
3. Ga naar `D:\GitHub\HavunCore` en klik **Selecteer map**

---

## Stap 2: Claude Code terminal openen

1. Klik onderaan in VS Code op **Terminal** (of druk `Ctrl + ~`)
2. Als je een gewone terminal ziet, typ: `claude` en druk Enter
3. Je ziet nu de Claude Code prompt (een `>` teken)

---

## Stap 3: Vraag Claude wat er aan de hand is

Typ precies dit in de terminal:

```
De websites van Havun zijn mogelijk down. Check de status van alle apps en vertel me wat er aan de hand is. Geef simpele instructies wat ik moet doen.
```

Claude gaat dan:
- Controleren welke apps online/offline zijn
- Uitleggen wat het probleem is
- Je stap voor stap vertellen wat je moet doen

---

## Stap 4: Volg Claude's instructies

Claude kan je vragen om dingen te bevestigen. Antwoord met:
- **ja** of **ok** als je het begrijpt en wilt doorgaan
- **nee** of **stop** als je twijfelt
- **leg uit** als je iets niet snapt

### Wat Claude WEL mag doen:
- De server checken (status opvragen)
- Services herstarten (nginx, PHP)
- Caches legen
- Bestanden bekijken

### Wat Claude NIET mag doen (en jij ook niet):
- Wachtwoorden of SSH keys veranderen
- Code wijzigen
- Databases aanpassen
- Nieuwe software installeren

Als Claude iets wil doen dat je niet begrijpt: **typ "stop" en probeer Henk te bereiken.**

---

## Noodscenario's

### "Website laadt niet"

Typ aan Claude:
```
Check of https://herdenkingsportaal.nl bereikbaar is. Als het down is, herstart de webserver.
```

### "Foutmelding op een website"

Typ aan Claude:
```
Er is een foutmelding op [websitenaam]. Check de logs en vertel me wat er aan de hand is.
```

### "E-mail ontvangen met [ALERT]"

Typ aan Claude:
```
Ik heb een alert e-mail ontvangen dat [naam uit e-mail] down is. Check de status en los het op als het kan.
```

### "Ik weet het niet meer"

Typ aan Claude:
```
Ik ben de backup-contactpersoon en ik weet niet wat ik moet doen. Help me.
```

---

## Belangrijke info

| Wat | Waarde |
|-----|--------|
| **Server IP** | 188.245.159.115 |
| **Websites** | herdenkingsportaal.nl, judotoernooi.havun.nl, havuncore.havun.nl |
| **E-mail Henk** | havun22@gmail.com |
| **Hosting** | Hetzner (console.hetzner.cloud) |

---

## Regels

1. **Paniek is niet nodig** — websites gaan soms even offline, dat is normaal
2. **Claude helpt je** — je hoeft niets te onthouden, vraag het gewoon
3. **Bij twijfel: stop** — liever niks doen dan iets fout doen
4. **Probeer Henk te bereiken** — bel, WhatsApp, SMS
5. **Documenteer wat je hebt gedaan** — typ aan Claude: "schrijf op wat we gedaan hebben"

---

## Droogtest (doe dit 1x samen)

Voer de volgende stappen uit om te oefenen:

- [ ] VS Code openen
- [ ] Claude Code terminal openen (typ `claude`)
- [ ] Typ: "Check de status van alle Havun websites"
- [ ] Lees wat Claude antwoordt
- [ ] Typ: "stop" om te stoppen

**Als dit lukt, ben je klaar.**

---

*Aangemaakt: 29 maart 2026 — VP-07*
