<?php

return [
    'base_url' => env('PROPIT_BASE_URL', 'https://real-time.proppit.com/api/v2'),

    // System-level credentials identifying Homlity as an API client to Proppit.
    // Must never be stored per-agency or included in property payloads.
    'client_id'     => env('PROPIT_CLIENT_ID', env('PROPIT_API_USER', env('PROPIT_API_KEY', ''))),
    'client_secret' => env('PROPIT_CLIENT_SECRET', env('PROPIT_API_PASSWORD', env('PROPIT_API_SECRET', ''))),

    'country' => env('PROPIT_COUNTRY', 'CO'),

    'timeout'       => (int) env('PROPIT_TIMEOUT', 30),
    'retry_attempts' => (int) env('PROPIT_RETRY_ATTEMPTS', 3),
    'retry_delay_ms' => (int) env('PROPIT_RETRY_DELAY_MS', 500),
    'user_agent'    => env('PROPIT_USER_AGENT', 'Homlity-Proppit-SDK/1.0'),
    'enable_structured_logs' => (bool) env('PROPIT_ENABLE_STRUCTURED_LOGS', true),

    'custom_headers' => [],
];
