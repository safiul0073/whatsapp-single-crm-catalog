<?php

return [
    'platform_secret' => env('STOREFRONT_PLATFORM_SECRET'),
    'root_domain' => env('STOREFRONT_ROOT_DOMAIN', 'localhost'),
    'dns_target' => env('STOREFRONT_DNS_TARGET'),
    'url' => env('STOREFRONT_URL', 'http://localhost:3000'),
    'revalidation_secret' => env('STOREFRONT_REVALIDATION_SECRET'),
    'preview_secret' => env('STOREFRONT_PREVIEW_SECRET'),
    // Social proof: hide "N sold" below this count; badge the top N recent sellers as "Best Seller".
    'sold_badge_min' => (int) env('STOREFRONT_SOLD_BADGE_MIN', 10),
    'best_seller_count' => (int) env('STOREFRONT_BEST_SELLER_COUNT', 10),
];
