<?php

return [
    'auth_cookie' => [
        'name' => env('API_AUTH_COOKIE', 'lpup_token'),
        'ttl_minutes' => (int) env('API_AUTH_COOKIE_TTL', 60 * 24),
    ],
];
