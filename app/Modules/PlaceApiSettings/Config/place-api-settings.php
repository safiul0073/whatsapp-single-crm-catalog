<?php

return [
    'google-places' => [
        'label' => 'Google Places',
        'icon' => 'ph ph-map-pin',
        'description' => 'Use Google Places API as the verified source for lead discovery.',
        'settings' => [
            'google_places_enabled' => [
                'type' => 'feature',
                'label' => 'Enable Google Places Lead Source',
                'hint' => 'Required before users can generate leads from the Leads module.',
                'default' => false,
                'rules' => 'nullable|boolean',
            ],
            'google_places_api_key' => [
                'type' => 'password',
                'label' => 'Google Places API Key',
                'hint' => 'Stored encrypted. Required when Google Places lead source is enabled.',
                'default' => env('GOOGLE_PLACES_API_KEY', ''),
                'rules' => 'nullable|string|max:1000',
                'encrypted' => true,
            ],
            'google_places_language' => [
                'type' => 'text',
                'label' => 'Language Code',
                'hint' => 'Optional response language, for example en or bn.',
                'default' => 'en',
                'rules' => 'nullable|string|max:10',
            ],
            'google_places_region' => [
                'type' => 'text',
                'label' => 'Region Code',
                'hint' => 'Optional region bias, for example US or BD.',
                'default' => '',
                'rules' => 'nullable|string|size:2',
            ],
            'google_places_result_limit' => [
                'type' => 'integer',
                'label' => 'Maximum Results Per Run',
                'hint' => 'Caps Google Places results requested per lead-generation run.',
                'default' => 25,
                'rules' => 'nullable|integer|min:1|max:60',
            ],
        ],
    ],
];
