<?php

return [
    'default_provider' => 'log',
    'providers' => [
        'log' => [
            'label' => 'Log (Testing)',
            'description' => 'Write email payloads to the application log for safe testing.',
            'fields' => [],
        ],
        'smtp' => [
            'label' => 'SMTP',
            'description' => 'Send email through an SMTP server.',
            'fields' => [
                'mail_host' => [
                    'label' => 'SMTP Host',
                    'type' => 'text',
                    'required' => true,
                    'rules' => ['required', 'string', 'max:255'],
                ],
                'mail_port' => [
                    'label' => 'SMTP Port',
                    'type' => 'number',
                    'required' => true,
                    'rules' => ['required', 'integer', 'min:1', 'max:65535'],
                ],
                'mail_encryption' => [
                    'label' => 'Encryption',
                    'type' => 'select',
                    'required' => true,
                    'default' => 'tls',
                    'options' => [
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                        'none' => 'None',
                    ],
                    'rules' => ['required', 'in:tls,ssl,none'],
                ],
                'mail_username' => [
                    'label' => 'SMTP Username',
                    'type' => 'text',
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
                'mail_password' => [
                    'label' => 'SMTP Password',
                    'type' => 'password',
                    'secret' => true,
                    'required' => true,
                    'rules' => ['required', 'string', 'max:255'],
                    'saved_rules' => ['nullable', 'string', 'max:255'],
                ],
            ],
        ],
        'mailgun' => [
            'label' => 'Mailgun',
            'description' => 'Send email through Mailgun API credentials.',
            'fields' => [
                'mailgun_domain' => [
                    'label' => 'Mailgun Domain',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'mg.example.com',
                    'rules' => ['required', 'string', 'max:255'],
                ],
                'mailgun_secret' => [
                    'label' => 'Mailgun Secret',
                    'type' => 'password',
                    'secret' => true,
                    'required' => true,
                    'rules' => ['required', 'string', 'max:255'],
                    'saved_rules' => ['nullable', 'string', 'max:255'],
                ],
                'mailgun_endpoint' => [
                    'label' => 'Mailgun Endpoint',
                    'type' => 'text',
                    'default' => 'api.mailgun.net',
                    'placeholder' => 'api.mailgun.net',
                    'rules' => ['nullable', 'string', 'max:255'],
                ],
            ],
        ],
        'sendmail' => [
            'label' => 'Sendmail',
            'description' => 'Use the server sendmail transport.',
            'fields' => [],
        ],
    ],
];
