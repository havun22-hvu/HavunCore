---
title: JudoToernooi portfolio-repo vs Laravel-app split
type: decision
date: 2026-05-09
status: proposed
project: judotoernooi (cross-checkout)
---

# ADR — JudoToernooi portfolio + Laravel split

## Status

Voorgesteld 2026-05-09. **Geen autonome fix** — raakt productie-deploy-flow + Henks lokale werkstroom. Henk-beslissing nodig.

## Context

Tijdens de [repo-hygiene rollout](repo-hygiene-2026-05-09.md) bleek dat de PWA Project Status `JudoToernooi` op de server-checkout 1209 deleted files rapporteerde. Diagnose:

**Server (`/var/www/judotoernooi/`):**

```
.git/                      ← portfolio-repo: README, /images, /Scripts, .claude, etc
laravel → repo-prod/laravel  ← symlink
laravel-old/               ← Apr 27 backup van vroegere flat layout
repo-prod/.git             ← echte Laravel-app repo (havun22-hvu/judotoernooi)
repo-staging/.git          ← staging Laravel-app repo
staging-old/               ← Apr 27 backup
```

De portfolio-repo's git-index tracked nog steeds **alle 1209 `laravel/...` paths** uit de pre-refactor flat structuur. `git status` resolve't de symlink niet als tracked content → 1209 D regels.

**Lokaal (`D:/GitHub/JudoToernooi/`):**

```
laravel/                   ← echte directory, deel van de portfolio-repo
.git/                      ← portfolio-repo (zelfde origin als server portfolio-repo)
```

Lokaal werkt Henk **flat** — geen symlink, geen repo-prod, geen split. De portfolio-repo bevat lokaal álle 699 portfolio-files **plus** de hele Laravel-app op één plek.

## Wat veroorzaakt het probleem

Op een gegeven moment (~24-04-2026 op basis van `repo-prod/` mtime + `laravel-old/` Apr 27) is de server overgegaan op een Capistrano-achtige split: portfolio-repo voor server-niveau bestanden, aparte `repo-prod` / `repo-staging` checkouts voor de Laravel-app via symlink. **Maar de portfolio-repo's git-index op de server is nooit opgeschoond.** Lokaal is de refactor nooit doorgevoerd.

Origin (`havun22-hvu/judotoernooi`) bevat dus de **flat** structuur, want lokaal commit Henk daarop. De server pulled die flat structuur in zijn portfolio-repo, maar de werkelijke `laravel/` is een symlink — git ziet de inhoud niet en marqueert alles als deleted.

## Opties

### A — Portfolio-repo accepteert dat het geen Laravel meer track

Op server **én** lokaal: stop met `laravel/` te tracken in de portfolio-repo. De Laravel-app krijgt z'n eigen GitHub-repo (de `repo-prod` op server is daar al een aanzet, alleen nooit op origin gepushed?).

**Wijzigingen:**
- Lokaal: `git rm -r --cached laravel`, voeg `laravel/` toe aan `.gitignore`, commit + push
- Lokaal extra: nieuwe map `D:/GitHub/JudoToernooi-app/` met aparte git-clone van een **nieuwe** GitHub-repo voor de Laravel-app
- Server: `repo-prod/.git` push zijn content naar die nieuwe GitHub-repo (origin verwisselen)
- Server: portfolio-repo's git-index synchroon maken met origin (na de lokale push gaat dit vanzelf)

**Voordeel:** clean separation tussen server-niveau (portfolio) en applicatie (Laravel). Schaalbaar voor andere split-projecten.

**Nadeel:** Henks workflow verandert — hij werkt lokaal in **twee** mappen (`JudoToernooi/` voor portfolio, `JudoToernooi-app/` voor Laravel). Bestaande Claude-sessions, bookmarks en lokale scripts verwijzen naar `D:/GitHub/JudoToernooi/laravel/`.

### B — Server geeft de split op, gaat terug naar flat

Verwijder op de server de `repo-prod/`/`repo-staging/` split, vervang de symlink door een echte directory. Server-deploy gaat dan via één `git pull` op de portfolio-repo, net als alle andere Havun-projecten.

**Wijzigingen:**
- Server: stop nginx/php-fpm graceful (of accepteer kortstondige downtime)
- Server: `cd /var/www/judotoernooi && rm laravel && git checkout laravel/` (haalt de tracked versie terug uit de portfolio-repo)
- Server: archiveer `repo-prod/`, `repo-staging/`, `laravel-old/`, `staging-old/`
- Server: restart php-fpm
- (Geen wijziging op lokaal of GitHub — die zijn al flat)

**Voordeel:** Henks workflow blijft hetzelfde. Server gedraagt zich weer als alle andere projecten. Eén commando deploy.

**Nadeel:** verlies van de Capistrano-achtige rollback-capaciteit van de split-deploy. Korte downtime tijdens de migratie. `repo-prod`/`repo-staging` waren mogelijk bewust opgezet voor zero-downtime — die feature gaat weg.

### C — Status quo + alleen de symptomen aanpakken

Niets wijzigen aan de structuur. Update PWA-config zodat hij naar `/var/www/judotoernooi/repo-prod` wijst (al gedaan vandaag, commit 98c54b0). Documenteer de mismatch en accepteer dat de portfolio-repo op server "1209 D" toont als z'n eigen normale toestand.

**Voordeel:** geen wijzigingen, geen risico.

**Nadeel:** technische schuld blijft. Bij elke nieuwe sessie moet ik dit weer uitleggen. PWA toont voor portfolio-repo geen zinvolle status.

## Aanbeveling

**Optie B** lijkt het minst disruptief en consistent met hoe de andere 7 Havun-projecten werken. Maar dat hangt af van **waarom** de split ooit gedaan is — als zero-downtime deploy een harde eis was, dan is A beter.

Je zou je kunnen herinneren of de split bewust werd gedaan voor zero-downtime / atomic-deploy, of dat het een experiment was dat is blijven hangen.

## Wat ik niet doe

- Geen autonome fix — dit raakt productie-deploy-flow + lokale workflow.
- Geen `git rm` op origin/main zonder akkoord — andere checkouts elsewhere kunnen breken.
- Geen `rm -rf repo-prod` op server zonder akkoord — daar staat de actief gebruikte symlink-target in.

Wachten op Henk's keuze (A/B/C). De PWA-config wijst momenteel correct naar de actieve checkout (`repo-prod`), dus er is geen functionele blocker — alleen cosmetisch ruis op één status-kaart.
