<?php

/*
|--------------------------------------------------------------------------
| Proppit SDK configuration
|--------------------------------------------------------------------------
|
| Every setting reads PROPPIT_* first and falls back to the legacy PROPIT_*
| single-p names, so .env files written before the rename keep working.
| New installs should use PROPPIT_* only — the legacy names are deprecated.
|
*/

return [
    'base_url' => env('PROPPIT_BASE_URL', env('PROPIT_BASE_URL', 'https://real-time.proppit.com/api/v2')),

    // System-level credentials identifying Homlity as an API client to Proppit.
    // Must never be stored per-agency or included in property payloads.
    'client_id' => env('PROPPIT_CLIENT_ID', env(
        'PROPIT_CLIENT_ID',
        env('PROPPIT_API_USER', env('PROPIT_API_USER', env('PROPPIT_API_KEY', env('PROPIT_API_KEY', ''))))
    )),

    'client_secret' => env('PROPPIT_CLIENT_SECRET', env(
        'PROPIT_CLIENT_SECRET',
        env('PROPPIT_API_PASSWORD', env('PROPIT_API_PASSWORD', env('PROPPIT_API_SECRET', env('PROPIT_API_SECRET', ''))))
    )),

    'country' => env('PROPPIT_COUNTRY', env('PROPIT_COUNTRY', 'CO')),

    // Default publisher externalId used by PropertyApi::find() and delete().
    // Leave null in multi-agency installs and use findByExternalId() instead.
    'publisher_external_id' => env('PROPPIT_PUBLISHER_EXTERNAL_ID', env('PROPIT_PUBLISHER_EXTERNAL_ID')),

    'timeout'        => (int) env('PROPPIT_TIMEOUT', env('PROPIT_TIMEOUT', 30)),
    'retry_attempts' => (int) env('PROPPIT_RETRY_ATTEMPTS', env('PROPIT_RETRY_ATTEMPTS', 3)),
    'retry_delay_ms' => (int) env('PROPPIT_RETRY_DELAY_MS', env('PROPIT_RETRY_DELAY_MS', 500)),
    'user_agent'     => env('PROPPIT_USER_AGENT', env('PROPIT_USER_AGENT', 'Homlity-Proppit-SDK/1.0')),

    'enable_structured_logs' => (bool) env('PROPPIT_ENABLE_STRUCTURED_LOGS', env('PROPIT_ENABLE_STRUCTURED_LOGS', true)),

    'custom_headers' => [],
];
