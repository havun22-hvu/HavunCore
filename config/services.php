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

    /*
    |--------------------------------------------------------------------------
    | Doc Intelligence KB API
    |--------------------------------------------------------------------------
    |
    | Bearer token for remote KB search access (Claude on other machines).
    |
    */

    'doc_intelligence' => [
        'api_token' => env('DOC_INTELLIGENCE_API_TOKEN'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model'   => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webapp real-time notify
    |--------------------------------------------------------------------------
    |
    | Localhost endpoint on the HavunCore webapp (Node.js) backend that the
    | health:alert command pings so the notification panel updates live via
    | Socket.io. Defaults to the production port (3001); best-effort only.
    |
    */

    'webapp_notify_url' => env('WEBAPP_NOTIFY_URL', 'http://127.0.0.1:3001/api/internal/notify'),

];
