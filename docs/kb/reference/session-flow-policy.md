---
title: Session Flow Policy — Start & End discipline
type: reference
scope: cross-project
last_check: 2026-05-08
---

# Session Flow Policy

> Cross-project regels voor `/start` en `/end` slash-commands.
> **Single source of truth** — alle project-specifieke `/start.md` en `/end.md` verwijzen hierheen, dupliceren niet.

## Tijdens de sessie — wie bepaalt het einde?

**Henk bepaalt wanneer een sessie eindigt. Claude bepaalt dat NOOIT.**

- Claude stelt **nooit** voor om af te sluiten, te pauzeren, of de sessie te beëindigen
- Claude vraagt **nooit** "zullen we stoppen?", "is er nog iets?", "kunnen we afronden?", "willen we het hier laten?" of equivalente formuleringen
- Na elke afgeronde taak: Claude blijft beschikbaar voor de volgende taak — geen afsluit-suggestie
- Alleen als Henk zelf `/end` typt of expliciet zegt dat hij stopt, ronden we af
- Claude stelt resultaten en next steps voor; Henk kiest

Dit is consistent met memory `feedback_no_closing_question.md` en `feedback_dont_pause.md`.

## Bij `/end` — sync-en-deploy verplicht

Een sessie mag **nooit** eindigen met onverwerkt werk. Voor elk project waar code/docs is aangepast tijdens de sessie:

### 1. Sync verplicht

```bash
git status            # MOET leeg zijn (of alleen bewust untracked, met reden in handover)
git log @{u}..HEAD    # MOET leeg zijn — alle commits gepusht
```

- Uncommitted wijzigingen → commit atomair (per feature/fix) of revert bewust
- Lokale commits → push direct
- Open PR's → merge, sluit, of expliciet noteren in handover **waarom** open blijft

### 2. Staging-deploy verplicht (waar beschikbaar)

Als het project een staging-omgeving heeft, deploy daar **automatisch** vóór de sessie eindigt:

| Project | Staging |
|---------|---------|
| HavunAdmin | `/var/www/havunadmin/staging` |
| JudoToernooi | `/var/www/judotoernooi/staging` |
| (overige) | geen aparte staging |

Voor projecten zonder staging: sla deze stap over.

### 3. Productie-deploy: altijd vragen

Als sessie wijzigingen bevat die in productie horen, **vraag** Henk expliciet of ook naar productie gedeployed moet worden:

> "Wil je dit ook naar productie deployen?"

- Bij **ja** → voer de productie-deploy uit (project-specifieke procedure verderop in `/end.md`)
- Bij **nee** → noteer in handover dat productie-deploy nog moet gebeuren
- Bij **twijfel** → eerst extra checks (tests, security audit) doen, daarna opnieuw vragen

**Nooit** silently naar productie pushen zonder bevestiging.

### 4. Hard rule

Sessie-einde mag niet ophouden met:
- Losse wijzigingen zonder commit
- Lokale commits zonder push
- Staging-deploy overgeslagen (bij projecten met staging)
- Productie wijzigingen zonder Henks expliciete `ja`

Als iets bewust open blijft: leg in `.claude/handover.md` vast **wat** en **waarom**.

## Verbod op autonome destructieve acties

Bij `/end` géén:
- `git push --force` zonder verzoek
- `git branch -D` voor non-merged branches zonder verzoek
- `composer remove`, `npm uninstall` zonder verzoek
- DB drop/migrate op productie zonder verzoek

Per memory `feedback_no_destructive_actions.md`.

## Zie ook

- `feedback_no_closing_question.md` (memory) — geen "nog iets of stoppen?"
- `feedback_dont_pause.md` (memory) — niet pauzeren bij milestones
- `feedback_just_continue.md` (memory) — gewoon doorwerken
- `feedback_dagsamenvatting_lopend.md` (memory) — dagsamenvatting in lopende tekst, geen bullets
- `feedback_no_destructive_actions.md` (memory) — destructieve serveracties alleen op verzoek
- `feedback_claude_owns_infra.md` (memory) — Claude voert deploy zelf uit, niet Henk
