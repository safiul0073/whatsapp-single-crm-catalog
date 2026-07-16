<?php

return [
    'default_provider' => 'log',
    'providers' => [
        'log' => [
            'label' => 'Log (Testing)',
            'description' => 'Write SMS payloads to the application log for safe testing.',
            'fields' => [],
        ],
        'twilio' => [
            'label' => 'Twilio',
            'description' => 'Send SMS through a Twilio messaging number.',
            'fields' => [
                'twilio_sid' => [
                    'label' => 'Twilio Account SID',
                    'type' => 'text',
                    'required' => true,
                    'rules' => ['required', 'string', 'max:255'],
                ],
                'twilio_auth_token' => [
                    'label' => 'Twilio Auth Token',
                    'type' => 'password',
                    'secret' => true,
                    'required' => true,
                    'rules' => ['required', 'string', 'max:255'],
                    'saved_rules' => ['nullable', 'string', 'max:255'],
                ],
            ],
        ],
        'vonage' => [
            'label' => 'Vonage',
            'description' => 'Send SMS through Vonage API credentials.',
            'fields' => [
                'vonage_api_key' => [
                    'label' => 'Vonage API Key',
                    'type' => 'text',
                    'required' => true,
                    'rules' => ['required', 'string', 'max:255'],
                ],
                'vonage_api_secret' => [
                    'label' => 'Vonage API Secret',
                    'type' => 'password',
                    'secret' => true,
                    'required' => true,
                    'rules' => ['required', 'string', 'max:255'],
                    'saved_rules' => ['nullable', 'string', 'max:255'],
                ],
            ],
        ],
    ],
];
