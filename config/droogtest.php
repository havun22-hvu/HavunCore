<?php

/**
 * Droogtest (emergency-protocol dry run) configuration.
 *
 * Source-of-truth voor het schema is dit bestand.
 * De runbook docs/kb/runbooks/droogtest-schema-2026-2027.md MOET dezelfde
 * datums + roller bevatten — een test in tests/Feature bewaakt deze sync.
 */

return [

    /*
     * Recipient van de wekelijkse-vooraf reminder e-mail.
     * Override in .env via DROOGTEST_REMINDER_EMAIL.
     */
    'recipient' => env('DROOGTEST_REMINDER_EMAIL', 'henkvu@gmail.com'),

    /*
     * Timezone die gebruikt wordt voor alle datum-vergelijkingen.
     * Hardcoded omdat dit een Nederlands bedrijfsproces is — niet
     * afhankelijk van app.timezone die ooit zou kunnen wijzigen.
     */
    'timezone' => 'Europe/Amsterdam',

    /*
     * Aantal dagen voor de droogtest dat de reminder verstuurd wordt.
     */
    'reminder_days_before' => 7,

    /*
     * Geplande droogtests, sorteer chronologisch.
     * Datum-formaat: YYYY-MM-DD (geen tijd; uitvoering is altijd 14:00 lokaal).
     */
    'schedule' => [
        ['date' => '2026-07-19', 'contact' => 'Thiemo', 'standby' => 'Mawin'],
        ['date' => '2026-10-18', 'contact' => 'Mawin',  'standby' => 'Thiemo'],
        ['date' => '2027-01-18', 'contact' => 'Thiemo', 'standby' => 'Mawin'],
        ['date' => '2027-04-19', 'contact' => 'Mawin',  'standby' => 'Thiemo'],
    ],
];
