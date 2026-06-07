# Bevinding: JudoToernooi productie-deploy loopt via `repo-prod` (symlink), KB-docs onvolledig

> **Datum:** 2026-06-08
> **Status:** Voorgelegd ter review (vermoeden van probleem)
> **Gevonden tijdens:** deploy van de awasete-ippon waarschuwing (scoreboard-live.blade + ScoreboardController) naar productie.
> **Door:** Claude (JudoScoreBoard-sessie)

## Samenvatting

De gedocumenteerde deploy-instructie voor JudoToernooi productie is **onvolledig en
potentieel misleidend**. KB zegt:

```bash
cd /var/www/judotoernooi/laravel
git pull origin main
```
(bron: `runbooks/deploy.md` r.85/94 én `projects/judotoernooi.md` r.300)

De werkelijke serverstructuur (geverifieerd op `root@188.245.159.115`, 2026-06-08):

- nginx productie-root = `/var/www/judotoernooi/laravel/public`
- `/var/www/judotoernooi/laravel` is een **symlink** → `/var/www/judotoernooi/repo-prod/laravel`
- De echte git-checkout is **`/var/www/judotoernooi/repo-prod`** (branch `main`).
- Er is een **post-merge git-hook** in repo-prod die na elke pull zelf de caches wist
  én `php artisan optimize` draait ("post-merge: Laravel caches cleared and optimized").
- De parent-repo `/var/www/judotoernooi` (óók een git-repo op `main`) staat in een
  **dirty/legacy staat**: `git status` toont `laravel/*` als *deleted*. Niet de actieve deploy.
- Verdere clutter op top-level: `laravel-old`, `staging-old`, `legacy-gas`, `repo-staging`.

## Waarom dit een probleem is (vermoeden)

1. **`config:clear` ná deploy verprutst de optimalisatie.** De gedocumenteerde stap
   `php artisan config:clear && php artisan cache:clear` draait ná de post-merge hook die
   net `optimize` deed → de gecachete (snelle) staat wordt weer ongedaan gemaakt. Wie
   handmatig wist moet daarna `php artisan optimize` draaien.
2. **Misleidende git-locatie.** De instructie wijst naar `laravel/` (symlink, geen eigen
   `.git`). Het werkt nu toevallig omdat git de symlink volgt naar `repo-prod`, maar dat is
   fragiel en niet uitgelegd. Wie in `/var/www/judotoernooi` (de parent) gaat pullen, raakt
   de verkeerde, dirty repo.
3. **Dirty parent-repo = latent risico.** De `laravel/*`-deletions in de parent kunnen bij
   een verkeerde git-actie stale bestanden terugzetten of verwarring geven bij troubleshooting.

## Voorstel / aanbeveling

- **Correcte deploy vastleggen** in `runbooks/deploy.md` + `projects/judotoernooi.md`:
  ```bash
  cd /var/www/judotoernooi/repo-prod
  git pull --ff-only origin main      # post-merge hook wist caches + draait `optimize`
  # NIET handmatig config:clear erna (maakt optimize ongedaan); zo wel, dan `php laravel/artisan optimize`
  ```
- **Server-hygiëne** (infra-beslissing, niet door Claude uitgevoerd): overweeg de dirty parent-repo
  `/var/www/judotoernooi` op te schonen of als deploy-pad te schrappen, plus `*-old`/`legacy-gas`.
  Sluit aan bij eerdere `repo-hygiene-2026-05-09.md`.

## Verificatie deze sessie

- repo-prod gepulld naar `84a79367` (awasete-feature), post-merge hook draaide, `optimize` hersteld.
- Live blade bevat `awasete-warning` (5×); controller accepteert `osaekomi.warning`. Productie OK.
