<?php

return [
    'base_url' => env('PROPIT_BASE_URL', 'https://real-time.proppit.com/api/v2'),

    // OpenAPI uses /token with user/password. Kept api_key/api_secret for backward compatibility.
    'api_user' => env('PROPIT_API_USER', env('PROPIT_API_KEY', '')),
    'api_password' => env('PROPIT_API_PASSWORD', env('PROPIT_API_SECRET', '')),
    'api_key' => env('PROPIT_API_KEY', ''),
    'api_secret' => env('PROPIT_API_SECRET', ''),

    'country' => env('PROPIT_COUNTRY', 'CO'),
    'publisher_external_id' => env('PROPIT_PUBLISHER_EXTERNAL_ID'),

    'timeout' => (int) env('PROPIT_TIMEOUT', 15),
    'retry_attempts' => (int) env('PROPIT_RETRY_ATTEMPTS', 2),
    'retry_delay_ms' => (int) env('PROPIT_RETRY_DELAY_MS', 250),
    'user_agent' => env('PROPIT_USER_AGENT', 'PropitSDK/1.0 (+https://real-time.proppit.com/api/v2/docs)'),
    'enable_structured_logs' => (bool) env('PROPIT_ENABLE_STRUCTURED_LOGS', false),
    'custom_headers' => [],
];
