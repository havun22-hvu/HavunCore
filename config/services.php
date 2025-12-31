<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Claude AI, payment providers, and more.
    |
    */

    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-3-haiku-20240307'),
        'rate_limit' => env('CLAUDE_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Studieplanner Integration
    |--------------------------------------------------------------------------
    |
    | API key for Studieplanner-api to authenticate when pushing session
    | events for WebSocket broadcasting to mentors.
    |
    */

    'studieplanner' => [
        'api_key' => env('STUDIEPLANNER_API_KEY'),
    ],

];
