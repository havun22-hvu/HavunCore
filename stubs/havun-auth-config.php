<?php

/**
 * HavunCore Auth Configuration
 *
 * Copy this file to your project's config folder as: config/havun-auth.php
 * Then add HAVUNCORE_AUTH_URL to your .env file
 */

return [
    /*
    |--------------------------------------------------------------------------
    | HavunCore Auth API URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the HavunCore authentication API.
    |
    */
    'api_url' => env('HAVUNCORE_AUTH_URL', 'https://havuncore.havun.nl'),

    /*
    |--------------------------------------------------------------------------
    | Enable QR Login
    |--------------------------------------------------------------------------
    |
    | Set to true to enable QR code login on this site.
    |
    */
    'qr_enabled' => env('HAVUNCORE_QR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Enable Password Fallback
    |--------------------------------------------------------------------------
    |
    | Set to true to allow password login as fallback.
    |
    */
    'password_enabled' => env('HAVUNCORE_PASSWORD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Device Trust Duration (days)
    |--------------------------------------------------------------------------
    |
    | How long a device stays trusted before requiring re-authentication.
    |
    */
    'trust_days' => env('HAVUNCORE_TRUST_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Login Route
    |--------------------------------------------------------------------------
    |
    | The route name to redirect to when authentication is required.
    |
    */
    'login_route' => env('HAVUNCORE_LOGIN_ROUTE', 'login'),

    /*
    |--------------------------------------------------------------------------
    | After Login Redirect
    |--------------------------------------------------------------------------
    |
    | Where to redirect after successful login.
    |
    */
    'redirect_after_login' => env('HAVUNCORE_REDIRECT', '/dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Cookie Name
    |--------------------------------------------------------------------------
    |
    | The name of the cookie used to store the device token.
    |
    */
    'cookie_name' => env('HAVUNCORE_COOKIE_NAME', 'havun_device_token'),

    /*
    |--------------------------------------------------------------------------
    | Cookie Domain
    |--------------------------------------------------------------------------
    |
    | The domain for the auth cookie. Leave null for current domain only.
    |
    */
    'cookie_domain' => env('HAVUNCORE_COOKIE_DOMAIN', null),
];
