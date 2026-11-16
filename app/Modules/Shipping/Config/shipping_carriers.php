<?php

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
];
