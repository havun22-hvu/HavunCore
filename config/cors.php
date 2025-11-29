<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://herdenkingsportaal.nl',
        'https://staging.herdenkingsportaal.nl',
        'https://havunadmin.havun.nl',
        'https://staging.havunadmin.havun.nl',
        'https://havuncore.havun.nl',
        'http://localhost:*',
        'http://127.0.0.1:*',
    ],

    'allowed_origins_patterns' => [
        '#^https?://.*\.herdenkingsportaal\.nl$#',
        '#^https?://.*\.havun\.nl$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
