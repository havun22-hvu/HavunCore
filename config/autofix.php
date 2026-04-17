<?php

/**
 * AutoFix configuration.
 *
 * Centrale source-of-truth voor het AutoFix delivery-model.
 * Project-executors lezen de proposal-status (`dry_run` vs `branch_pending`)
 * en handelen daarop — zij pushen NOOIT direct naar main/master.
 *
 * Achtergrond: VP-01 (verbeterplan-q2-2026.md) — branch+PR i.p.v. directe push.
 */

return [

    /*
     * Wanneer true: AutoFix instrueert de project-executor om naar een
     * hotfix-branch te pushen + automatische PR aan te maken, in plaats
     * van direct te committen op main/master.
     */
    'branch_model' => env('AUTOFIX_BRANCH_MODEL', true),

    /*
     * Branch-prefix gevolgd door {project}-{timestamp}.
     * Voorbeeld: hotfix/autofix-judotoernooi-2026-04-17-1530
     */
    'branch_prefix' => env('AUTOFIX_BRANCH_PREFIX', 'hotfix/autofix-'),

    /*
     * Wanneer true: project-executor maakt automatisch een PR aan via
     * de GitHub API na succesvolle push. Vereist GH_TOKEN in project-env.
     */
    'auto_pr' => env('AUTOFIX_AUTO_PR', true),

    /*
     * Risk-levels die GEEN code-wijziging mogen krijgen — alleen notificatie.
     * Eigenaar review't en past handmatig toe (of accepteert voorstel).
     */
    'dry_run_on_risk' => ['medium', 'high'],

    /*
     * Database-state snapshot vóór elke fix (per-tabel context).
     * Project-executor logt relevante rijen vóór toepassen.
     */
    'snapshot_enabled' => env('AUTOFIX_SNAPSHOT_ENABLED', true),
    'snapshot_max_rows' => env('AUTOFIX_SNAPSHOT_MAX_ROWS', 50),

    /*
     * Review-URL pattern voor fixes. Token wordt door project gegenereerd.
     * HavunCore expose't /autofix/{token} read-only voor de eigenaar.
     */
    'review_url_pattern' => env('AUTOFIX_REVIEW_URL', 'https://havuncore.havun.nl/autofix/{token}'),
];
