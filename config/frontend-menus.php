<?php

return [
    'header' => [
        'key' => 'header',
        'label' => 'Header Menu',
        'description' => 'Primary site navigation displayed in the header.',
        'max_depth' => 2,
        'allows_groups' => true,
        'theme_rendered' => true,
    ],
    'footer' => [
        'key' => 'footer',
        'label' => 'Footer Menu',
        'description' => 'Simplified footer navigation. Nested dropdowns are not rendered here.',
        'max_depth' => 1,
        'allows_groups' => true,
        'theme_rendered' => true,
    ],
    'mobile' => [
        'key' => 'mobile',
        'label' => 'Mobile Menu',
        'description' => 'Navigation used for compact and mobile-friendly menu patterns.',
        'max_depth' => 2,
        'allows_groups' => true,
        'theme_rendered' => true,
    ],
];
