<?php

return [
    'fatigue_threshold' => 3,
    'fatigue_window_days' => 7,

    'best_send_time' => [
        'label' => '9-11 AM local time',
        'start_hour' => 9,
        'end_hour' => 11,
    ],

    'risky_words' => [
        'guaranteed',
        'free money',
        'cash bonus',
        'limited offer',
        'act now',
        'urgent',
        'winner',
        'prize',
        'loan',
        'crypto',
    ],

    'promotional_words' => [
        'sale',
        'discount',
        'offer',
        'coupon',
        'deal',
        'buy now',
        'promo',
        'limited time',
        'save',
        'clearance',
    ],

    'whatsapp_rates' => [
        'default_country' => 'default',
        'default_category' => 'marketing',
        'currency' => 'USD',
        'countries' => [
            'default' => [
                'marketing' => 0.0149,
                'utility' => 0.0069,
                'authentication' => 0.0055,
                'service' => 0.0,
            ],
            'US' => [
                'marketing' => 0.0149,
                'utility' => 0.0069,
                'authentication' => 0.0055,
                'service' => 0.0,
            ],
            'BD' => [
                'marketing' => 0.012,
                'utility' => 0.004,
                'authentication' => 0.0035,
                'service' => 0.0,
            ],
        ],
    ],
];
