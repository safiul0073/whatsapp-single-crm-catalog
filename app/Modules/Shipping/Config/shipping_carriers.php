<?php

use App\Modules\Shipping\Services\Rates\AgentRateChartProvider;
use App\Modules\Shipping\Services\Rates\CourierApiRateProvider;

/**
 * Starting values for weight rating. Each courier account and agent stores its own
 * divisor and rounding step, and the handling limits below are editable defaults:
 * confirm them against the provider's current service guide before relying on them.
 */
return [
    'default_dim_divisor' => 5000,
    'default_rounding_step' => '0.5',
    'limits' => [
        'additional_handling_weight_kg' => 25,
        'additional_handling_longest_side_cm' => 122,
        'additional_handling_second_side_cm' => 76,
        'overweight_kg' => 70,
        'oversize_longest_side_cm' => 274,
        'oversize_length_plus_girth_cm' => 400,
    ],
    'quote_validity_days' => 7,

    /*
     * Sources asked for prices when rates are requested, in order.
     */
    'rate_providers' => [
        CourierApiRateProvider::class,
        AgentRateChartProvider::class,
    ],

    /*
     * Public tracking pages, filled in when a tracking number is saved without a link.
     */
    'tracking_urls' => [
        'ups' => 'https://www.ups.com/track?tracknum=%s',
        'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=%s',
        'aramex' => 'https://www.aramex.com/track/results?ShipmentNumber=%s',
    ],

    /*
     * Automatic tracking: how often an open shipment is re-checked, how many are
     * checked per run, and after how many days an unfinished shipment stops being polled.
     */
    'tracking' => [
        'interval_minutes' => 60,
        'batch_size' => 200,
        'max_age_days' => 90,
    ],

    'api' => [
        'timeout' => 20,
        'connect_timeout' => 5,
        'quote_validity_hours' => 24,
        'ups' => [
            'version' => 'v2409',
            'ship_version' => 'v2409',
            'sandbox' => 'https://wwwcie.ups.com',
            'production' => 'https://onlinetools.ups.com',
        ],
        'fedex' => [
            'sandbox' => 'https://apis-sandbox.fedex.com',
            'production' => 'https://apis.fedex.com',
        ],
        'aramex' => [
            'sandbox' => 'https://ws.aramex.net/ShippingAPI.V2/RateCalculator/Service_1_0.svc',
            'production' => 'https://ws.aramex.net/ShippingAPI.V2/RateCalculator/Service_1_0.svc',
            'tracking' => [
                'sandbox' => 'https://ws.aramex.net/ShippingAPI.V2/Tracking/Service_1_0.svc',
                'production' => 'https://ws.aramex.net/ShippingAPI.V2/Tracking/Service_1_0.svc',
            ],
            'shipping' => [
                'sandbox' => 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc',
                'production' => 'https://ws.aramex.net/ShippingAPI.V2/Shipping/Service_1_0.svc',
            ],
            'label_report_id' => 9201,
        ],
    ],
];
