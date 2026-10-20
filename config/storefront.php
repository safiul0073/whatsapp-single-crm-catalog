<?php

return [
    'platform_secret' => env('STOREFRONT_PLATFORM_SECRET'),
    'root_domain' => env('STOREFRONT_ROOT_DOMAIN', 'localhost'),
    'dns_target' => env('STOREFRONT_DNS_TARGET'),
    'url' => env('STOREFRONT_URL', 'http://localhost:3000'),
    'revalidation_secret' => env('STOREFRONT_REVALIDATION_SECRET'),
    'preview_secret' => env('STOREFRONT_PREVIEW_SECRET'),
];
