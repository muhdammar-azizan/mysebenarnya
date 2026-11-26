<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTPS
    |--------------------------------------------------------------------------
    |
    | When true, every generated URL uses https://. Defaults to on in production.
    |
    */

    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The host sits behind a proxy/CDN. Trusting it lets Laravel see the real
    | visitor IP and know the request arrived over HTTPS. Use "*" on shared
    | hosting, or a comma separated list of proxy IPs.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Seconds browsers must keep using HTTPS for this site (only sent over
    | HTTPS). Starts at 30 days; raise it once everything is confirmed working.
    | Set to 0 to disable.
    |
    */

    'hsts_max_age' => (int) env('HSTS_MAX_AGE', 2592000),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Set CSP_REPORT_ONLY=true to log violations in the browser console without
    | blocking anything. Use it as a quick safety valve if a page breaks.
    |
    */

    'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Abuse limits
    |--------------------------------------------------------------------------
    |
    | [attempts, seconds]. Counted per visitor IP (never per account, because
    | demo accounts are shared). See App\Support\Throttle.
    |
    */

    'limits' => [
        // Any sign-in failures from one IP, whatever email or username is tried.
        'login_ip' => [30, 600],
        'register' => [5, 3600],
        // Reset links sent from one IP, and to one email address.
        'forgot_password' => [5, 3600],
        'forgot_password_email' => [3, 3600],
        'reset_password' => [10, 3600],
        'verification_email' => [6, 3600],
        'submit_inquiry' => [10, 3600],
        // PDF, Excel and CSV exports are heavy.
        'export' => [15, 60],
        // Live search runs while typing.
        'search' => [120, 60],
    ],

];
