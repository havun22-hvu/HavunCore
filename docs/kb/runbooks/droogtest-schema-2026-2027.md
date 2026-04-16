# Runbook: Noodprotocol-Droogtest Schema 2026-2027

> **Doel:** Voorkomen dat noodprotocol-kennis bij Thiemo en Mawin verwatert. Eén droogtest per kalenderkwartaal.
> **Vereisten:** Eigenaar bereikbaar, contactpersoon beschikbaar, eigen PC + WhatsApp.
> **Klaar-criteria:** Contactpersoon heeft `/start` + `/rc` doorlopen en eigenaar heeft de remote-link succesvol kunnen openen.
> **Gerelateerd:** `emergency-runbook.md`, `noodcontactpersoon-protocol.md`, `werkplan-thiemo-droogtest.md`.

## Schema (rouleert per kwartaal)

> **Source-of-truth:** `config/droogtest.php`. Een test (`test_runbook_markdown_matches_config_schedule`) bewaakt dat onderstaande tabel synchroon blijft met de config — bij wijziging beide aanpassen.

| Kwartaal | Geplande datum | ISO-datum | Contactpersoon | Stand-by |
|----------|---------------|-----------|---------------|----------|
| Q3 2026 | zondag 19 juli 2026, 14:00 | `2026-07-19` | Thiemo | Mawin |
| Q4 2026 | zondag 18 oktober 2026, 14:00 | `2026-10-18` | Mawin | Thiemo |
| Q1 2027 | zondag 18 januari 2027, 14:00 | `2027-01-18` | Thiemo | Mawin |
| Q2 2027 | zondag 19 april 2027, 14:00 | `2027-04-19` | Mawin | Thiemo |

Verschuiven mag (vakantie etc.), maar moet binnen het kwartaal blijven en in dit document **én** in `config/droogtest.php` worden bijgewerkt.

## Reminder

7 dagen vóór elke geplande datum stuurt HavunCore automatisch een e-mail naar `henkvu@gmail.com`:

- Onderwerp: *"Droogtest noodprotocol over 1 week — \[contactpersoon]"*
- Inhoud: datum, contactpersoon, link naar deze runbook
- Bron: `app/Console/Commands/SendDroogtestReminderCommand.php` (cron: dagelijks 09:00)

## Procedure (vereenvoudigd protocol — voor contactpersoon)

1. **Aanmelden bij eigenaar (WhatsApp):** *"Klaar voor droogtest"*
2. Eigen PC opstarten, terminal openen
3. `cd D:\GitHub\HavunCore`
4. `claude` (start Claude Code CLI)
5. Typ `/start` — wacht tot Claude bevestigt: project geladen, status gerapporteerd
6. Typ `/rc` — Claude genereert remote-control link
7. Stuur link via WhatsApp naar eigenaar
8. Eigenaar opent link, bevestigt *"ik zie mee"*
9. Korte oefening: lees met Claude een willekeurige `docs/kb/runbooks/` bestand
10. Eigenaar geeft "klaar" sein → contactpersoon mag afsluiten
11. Eigenaar werkt deze runbook bij: tabel-rij met datum + initialen contactpersoon = ✅

## Wat te doen als de droogtest niet lukt

| Probleem | Eerste actie |
|----------|--------------|
| `/start` faalt op git pull | `git status` — zijn er lokaal uncommitted wijzigingen? Vraag eigenaar |
| `/rc` geeft geen link | Claude versie outdated? `claude --version`, vraag eigenaar |
| Eigenaar krijgt link maar kan niet inloggen | Network/firewall — herstart router, probeer mobiel netwerk |
| Eigenaar onbereikbaar | Verzet droogtest naar volgende zondag, update tabel hierboven |

Bij volledige mislukking: rapporteer in WhatsApp aan eigenaar. Dat IS de signal — het noodprotocol heeft een gat dat geadresseerd moet worden.

## Geschiedenis (wie heeft welke droogtest gedaan)

| Datum | Contactpersoon | Resultaat |
|-------|---------------|-----------|
| 16-04-2026 | Thiemo | ✅ Eerste droogtest succesvol (zie `werkplan-thiemo-droogtest.md`) |
