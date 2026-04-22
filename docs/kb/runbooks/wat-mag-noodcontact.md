---
title: Wat mag jij doen — Thiemo & Mawin
type: runbook
scope: havuncore
last_check: 2026-04-22
---

# Wat mag jij doen — Thiemo & Mawin

> **Doel:** Helder onderscheid tussen "doen", "alleen onder begeleiding", en "echt niet".
> **Voor wie:** Thiemo en Mawin als noodcontactpersonen — geen techneuten.
> **Vereisten:** Je hebt SSH-toegang tot de server en weet hoe Claude CLI te starten.
> **Klaar-criteria:** Je kunt zonder Claude Code te raadplegen lezen welk commando wel/niet mag in welk scenario.
> **Gerelateerd:** `noodcontactpersoon-protocol.md` (basis), `emergency-runbook.md` (technische details).

## De drie scenario's

### A. Henk is bereikbaar binnen 1 uur

**Wat te doen:**
1. Start Claude Code in de terminal (`claude` typen, dan `/start`)
2. Genereer een remote-control link: typ `/rc`
3. Stuur de link via WhatsApp naar Henk
4. **Wacht** — Henk neemt over zodra hij online is

**Wat NIET te doen:** zelfstandig dingen veranderen. Henk kan altijd zelf binnen het uur.

---

### B. Henk is langer dan 1 uur niet bereikbaar én een website is (deels) down

**Eerst altijd:** open Claude Code en typ:
> *"Een website is down en Henk is niet bereikbaar. Help me uitvinden wat er aan de hand is."*

Claude analyseert en zegt wat er aan de hand is. Daarna mag je deze commando's uitvoeren — onder begeleiding van Claude:

| Commando | Wat het doet | Veilig? |
|----------|--------------|---------|
| `php artisan down` | Site in onderhoudsmodus zetten | ✅ ja, omkeerbaar |
| `php artisan up` | Site weer aanzetten | ✅ ja |
| `service php-fpm restart` | PHP-server herstarten | ✅ ja, kort moment downtime |
| `service nginx reload` | Webserver herladen | ✅ ja |
| `git pull` (als hotfix-PR door Henk al gemerged is) | Nieuwe code ophalen | ⚠️ alleen als Henk gemerged heeft |

**NIET zelfstandig (vraag eerst Henk via WhatsApp):**

| Commando | Waarom niet |
|----------|-------------|
| `php artisan migrate` | Wijzigt de database — niet omkeerbaar |
| `git push --force` | Kan code overschrijven — niet omkeerbaar |
| Bestanden wijzigen in `.env` | Bevat geheime sleutels |
| Composer / npm packages installeren | Kan de site stuk maken |

---

### C. Henk is langer dan 24 uur niet bereikbaar én er is een kritiek beveiligingsprobleem

**Wat te doen:**

1. Open Claude Code, typ `/start`
2. Typ: *"Er is een security-incident en Henk is langer dan 24u niet bereikbaar. Wat moet ik doen?"*
3. Claude geeft je een stap-voor-stap plan. Volg het.
4. Eerste actie is bijna altijd: **maintenance mode aan**, alle sites offline:
   ```
   php artisan down --message="Tijdelijk niet beschikbaar wegens onderhoud"
   ```
5. Stuur e-mail naar **havun22@gmail.com** met onderwerp `[URGENT-SECURITY]` + omschrijving wat je hebt gezien
6. Stuur WhatsApp naar Henk (alle nummers proberen)
7. **Wacht** op Henk voor de inhoudelijke fix — zet niets weer aan zonder zijn akkoord

**NIET zelfstandig:** geen code wijzigen, geen credentials wijzigen, geen security-config aanpassen. Maintenance mode aanzetten = het beste wat je kunt doen.

---

## Cheat-sheet (print en hang naast je PC)

| Situatie | Eerste stap |
|----------|-------------|
| Henk bereikbaar | `/rc` + WhatsApp link sturen |
| Site down, Henk weg <24u | `php artisan down` mag, `migrate` mag NIET |
| Security probleem, Henk weg >24u | `php artisan down` + e-mail `[URGENT-SECURITY]` |
| Twijfel | **STOP** — vraag Claude CLI of stuur WhatsApp naar Henk |

---

## SSH inloggen (kort overzicht)

Als je echt op de server moet zijn:

```bash
ssh root@188.245.159.115
```

Daarna ben je op de server. Welke folder waar staat:

| Site | Pad |
|------|-----|
| Herdenkingsportaal | `/var/www/herdenkingsportaal/production` |
| JudoToernooi | `/var/www/judotoernooi/production` |
| HavunAdmin | `/var/www/havunadmin/production` |
| HavunCore | `/var/www/havuncore/production` |

Stap eerst de juiste folder in (`cd /var/www/...`) voordat je een `php artisan` commando uitvoert.

---

## Belangrijk

- **Liever niks doen dan iets kapot maken** — dat is regel 1
- **Claude CLI is je vangnet** — gebruik het bij elke twijfel
- **WhatsApp Henk altijd** — ook als je het opgelost hebt: meld wat je gedaan hebt
- **Documenteer:** schrijf op een Post-it wat je gedaan hebt en wanneer (Henk werkt het later bij)

---

*Aangemaakt: 16-04-2026 — VP-15. Eerste update na droogtest Q3 2026 (juli).*
