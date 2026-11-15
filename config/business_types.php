<?php

/*
 * Business types a workspace owner can pick. Each type names the backend modules it runs on,
 * the store-level features it switches on and the storefront app that serves its public site.
 * Add a new business (e.g. a travel agency) as one entry here plus its own module and storefront app.
 */
return [
    'default' => 'traditional',

    'types' => [
        'traditional' => [
            'label' => 'Traditional e-commerce',
            'description' => 'A regular online shop selling retail, wholesale or both.',
            'modules' => ['commerce'],
            'features' => [],
            'storefront_url' => env('STOREFRONT_URL', 'http://localhost:3000'),
        ],
        'referral' => [
            'label' => 'Referral commerce',
            'description' => 'A shop where customers refer buyers, earn commission, review refund requests of the people they referred and withdraw earnings in scheduled windows.',
            'modules' => ['commerce'],
            'features' => ['referral'],
            'storefront_url' => env('REFERRAL_STOREFRONT_URL', 'http://localhost:3001'),
        ],
    ],
];
