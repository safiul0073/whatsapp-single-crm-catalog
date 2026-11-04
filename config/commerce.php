<?php

return [
    'showcase_frontend_url' => env('ECOMMARCE_FRONTEND_URL'),
    'store_workspace_id' => env('COMMERCE_STORE_WORKSPACE_ID'),
    'store_key' => env('MARKETING_STORE_KEY'),
    'geoip' => [
        'url' => env('COMMERCE_GEOIP_URL', 'http://ip-api.com/json/{ip}?fields=countryCode,country,city'),
    ],
    'visit_retention_days' => (int) env('COMMERCE_VISIT_RETENTION_DAYS', 90),
];
