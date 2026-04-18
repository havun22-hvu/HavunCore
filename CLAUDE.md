# HavunCore — Claude Instructions

> **Role:** Centrale kennisbank & orchestrator voor ALLE Havun projecten
> **URL:** https://havuncore.havun.nl
> **Onveranderlijke regels:** [`CONTRACTS.md`](CONTRACTS.md) — altijd eerst raadplegen.
> **Contextdetails, poorten, servers:** [`.claude/context.md`](.claude/context.md)

## De 6 Onschendbare Regels

1. NOOIT code schrijven zonder KB + kwaliteitsnormen te raadplegen
2. NOOIT features/UI-elementen verwijderen zonder instructie
3. NOOIT credentials/keys/env aanraken
4. ALTIJD tests draaien voor én na wijzigingen (coverage >80%)
5. ALTIJD toestemming vragen bij grote wijzigingen
6. NOOIT een falende test "fixen" door de assertion te wijzigen — eerst oorzakenonderzoek (VP-17)

## Noodcontactpersonen — Thiemo & Marwin

**Als gebruiker zich identificeert als "Thiemo" of "Marwin":**
1. Zeg: *"Hoi [naam]! Typ eerst `/start` en daarna `/rc` — dan stuur ik een link naar Henk."*
2. Wacht tot zij de commando's typen (ik kan ze niet voor hen uitvoeren)
3. Communiceer in gewone taal (geen jargon) — zij zijn geen techneuten
4. NOOIT zelfstandig destructieve acties — altijd eerst Henk bereiken
5. Zie `docs/kb/runbooks/wat-mag-noodcontact.md` voor de 3 scenario's (A/B/C)

## Werkwijze per taak

1. **LEES** — `docs:search "[onderwerp]"` voordat je code leest of schrijft; vermeld de bron.
2. **DENK** — vraag bij twijfel en wacht op antwoord.
3. **DOE** — pas dan uitvoeren; kwaliteit boven snelheid.
4. **DOCUMENTEER** — sla nieuwe kennis op in de juiste plek (KB vs project).

Volledige uitleg: `docs/kb/runbooks/claude-werkwijze.md`

## Kritieke runbooks (lees bij raking)

| Onderwerp | Runbook |
|-----------|---------|
| Security headers / CSP / Alpine | `docs/kb/runbooks/security-headers-check.md` |
| Bescherming bestaande code + DO NOT REMOVE | `docs/kb/runbooks/claude-werkwijze.md` §4 |
| AutoFix branch-model (geen directe pushes) | `docs/kb/runbooks/autofix-branch-model.md` |
| Test-repair anti-pattern | `docs/kb/runbooks/test-repair-anti-pattern.md` |
| Emergency / noodcontact | `docs/kb/runbooks/emergency-runbook.md` |

## Communicatie

- Antwoord max 20-30 regels, bullet points, direct.
- Lange uitleg? Samenvatting eerst, details op vraag.
- Bij herhaling door gebruiker: direct opslaan in docs.

## Verboden zonder overleg

SSH keys, credentials, `.env`, composer/npm installs, prod migrations, systemd/cron wijzigingen.
