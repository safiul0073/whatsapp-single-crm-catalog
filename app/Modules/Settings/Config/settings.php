<?php

return [

    /*
    |--------------------------------------------------------------------------
    | General Settings
    |--------------------------------------------------------------------------
    */
    'general' => [
        'label' => 'General',
        'icon' => 'ph ph-gear',
        'description' => 'Core platform identity and configuration',
        'settings' => [
            'site_name' => [
                'type' => 'text',
                'label' => 'Site Name',
                'hint' => 'The name displayed across the platform',
                'default' => 'Admin Panel',
                'rules' => 'required|string|max:255',
                'public' => true,
            ],
            'site_description' => [
                'type' => 'textarea',
                'label' => 'Site Description',
                'hint' => 'A brief description of your platform',
                'default' => '',
                'rules' => 'nullable|string|max:500',
                'public' => true,
            ],
            'contact_email' => [
                'type' => 'email',
                'label' => 'Contact Email',
                'hint' => 'Primary contact email address',
                'default' => 'admin@example.com',
                'rules' => 'required|email|max:255',
                'public' => true,
            ],
            'default_timezone' => [
                'type' => 'select',
                'label' => 'Default Timezone',
                'hint' => 'Timezone used for dates and scheduling',
                'default' => 'UTC',
                'rules' => 'required|timezone',
                'options_resolver' => 'timezones',
            ],
            'date_format' => [
                'type' => 'select',
                'label' => 'Date Format',
                'hint' => 'How dates are displayed across the platform',
                'default' => 'd M, Y',
                'rules' => 'required|string',
                'options' => [
                    'd M, Y' => '23 Feb, 2026',
                    'M d, Y' => 'Feb 23, 2026',
                    'Y-m-d' => '2026-02-23',
                    'd/m/Y' => '23/02/2026',
                    'm/d/Y' => '02/23/2026',
                ],
            ],
            'items_per_page' => [
                'type' => 'number',
                'label' => 'Items Per Page',
                'hint' => 'Default pagination size for lists and tables',
                'default' => 15,
                'rules' => 'required|integer|min:5|max:100',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    */
    'appearance' => [
        'label' => 'Appearance',
        'icon' => 'ph ph-paint-brush',
        'description' => 'Logo, favicon, and theme colors',
        'settings' => [
            'site_logo' => [
                'type' => 'media',
                'label' => 'Site Logo',
                'hint' => 'Recommended: 200×50px, PNG or SVG',
                'default' => null,
                'accept' => 'image',
                'rules' => 'nullable|integer',
            ],
            'site_favicon' => [
                'type' => 'media',
                'label' => 'Site Favicon',
                'hint' => 'Recommended: 32×32px or 64×64px, PNG or ICO',
                'default' => null,
                'accept' => 'image',
                'rules' => 'nullable|integer',
            ],
            'primary_color' => [
                'type' => 'color',
                'label' => 'Primary Color',
                'hint' => 'Main brand color for buttons, links, and accents',
                'default' => '#1fb254',
                'rules' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'public' => true,
            ],
            'secondary_color' => [
                'type' => 'color',
                'label' => 'Secondary Color',
                'hint' => 'Used for secondary actions and decorative elements',
                'default' => '#215ebf',
                'rules' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'public' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Settings
    |--------------------------------------------------------------------------
    */
    'mail' => [
        'label' => 'Mail',
        'icon' => 'ph ph-envelope',
        'description' => 'Outgoing email and notification settings',
        'settings' => [
            'mail_mailer' => [
                'type' => 'select',
                'label' => 'Mail Driver',
                'hint' => 'Default transport used to send all outgoing emails',
                'default' => env('MAIL_MAILER', 'log'),
                'rules' => 'required|in:smtp,mailgun,sendmail,log,array',
                'options' => [
                    'smtp' => 'SMTP',
                    'mailgun' => 'Mailgun',
                    'sendmail' => 'Sendmail',
                    'log' => 'Log (Development)',
                    'array' => 'Array (Testing)',
                ],
            ],
            'mail_from_name' => [
                'type' => 'text',
                'label' => 'From Name',
                'hint' => 'Sender name for outgoing emails',
                'default' => env('MAIL_FROM_NAME', 'Example'),
                'rules' => 'required|string|max:255',
            ],
            'mail_from_address' => [
                'type' => 'email',
                'label' => 'From Address',
                'hint' => 'Sender email address',
                'default' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'rules' => 'required|email|max:255',
            ],
            'mail_host' => [
                'type' => 'text',
                'label' => 'SMTP Host',
                'hint' => 'SMTP server host, e.g. smtp.mailgun.org',
                'default' => env('MAIL_HOST', '127.0.0.1'),
                'rules' => 'required_if:settings.mail_mailer,smtp|string|max:255',
                'visible_if' => [
                    'mail_mailer' => ['smtp'],
                ],
            ],
            'mail_port' => [
                'type' => 'number',
                'label' => 'SMTP Port',
                'hint' => 'SMTP server port, usually 587 (TLS) or 465 (SSL)',
                'default' => (int) env('MAIL_PORT', 2525),
                'rules' => 'required_if:settings.mail_mailer,smtp|integer|min:1|max:65535',
                'visible_if' => [
                    'mail_mailer' => ['smtp'],
                ],
            ],
            'mail_encryption' => [
                'type' => 'select',
                'label' => 'SMTP Encryption',
                'hint' => 'Use None for local/testing SMTP servers',
                'default' => env('MAIL_SCHEME') ?: 'none',
                'rules' => 'required_if:settings.mail_mailer,smtp|in:none,tls,ssl',
                'options' => [
                    'none' => 'None',
                    'tls' => 'TLS',
                    'ssl' => 'SSL',
                ],
                'visible_if' => [
                    'mail_mailer' => ['smtp'],
                ],
            ],
            'mail_username' => [
                'type' => 'text',
                'label' => 'SMTP Username',
                'hint' => 'Username for SMTP authentication',
                'default' => env('MAIL_USERNAME', ''),
                'rules' => 'nullable|string|max:255',
                'visible_if' => [
                    'mail_mailer' => ['smtp'],
                ],
            ],
            'mail_password' => [
                'type' => 'password',
                'label' => 'SMTP Password',
                'hint' => 'Password or app password for SMTP authentication',
                'default' => env('MAIL_PASSWORD', ''),
                'rules' => 'nullable|string|max:255',
                'visible_if' => [
                    'mail_mailer' => ['smtp'],
                ],
            ],
            'mailgun_domain' => [
                'type' => 'text',
                'label' => 'Mailgun Domain',
                'hint' => 'Your Mailgun sending domain (e.g. mg.example.com)',
                'default' => env('MAILGUN_DOMAIN', ''),
                'rules' => 'required_if:settings.mail_mailer,mailgun|string|max:255',
                'visible_if' => [
                    'mail_mailer' => ['mailgun'],
                ],
            ],
            'mailgun_secret' => [
                'type' => 'password',
                'label' => 'Mailgun API Key',
                'hint' => 'Private Mailgun API key used for authentication',
                'default' => env('MAILGUN_SECRET', ''),
                'rules' => 'required_if:settings.mail_mailer,mailgun|string|max:255',
                'visible_if' => [
                    'mail_mailer' => ['mailgun'],
                ],
            ],
            'mailgun_endpoint' => [
                'type' => 'text',
                'label' => 'Mailgun Endpoint',
                'hint' => 'Mailgun API endpoint host (e.g. api.mailgun.net)',
                'default' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
                'rules' => 'required_if:settings.mail_mailer,mailgun|string|max:255',
                'visible_if' => [
                    'mail_mailer' => ['mailgun'],
                ],
            ],
            'mailgun_scheme' => [
                'type' => 'select',
                'label' => 'Mailgun Scheme',
                'hint' => 'Connection scheme for Mailgun API requests',
                'default' => env('MAILGUN_SCHEME', 'https'),
                'rules' => 'required_if:settings.mail_mailer,mailgun|in:http,https',
                'options' => [
                    'https' => 'HTTPS',
                    'http' => 'HTTP',
                ],
                'visible_if' => [
                    'mail_mailer' => ['mailgun'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'label' => 'Features',
        'icon' => 'ph ph-toggle-right',
        'description' => 'Feature flags and platform capabilities',
        'settings' => [
            'enable_registration' => [
                'type' => 'feature',
                'label' => 'User Registration',
                'hint' => 'Allow new users to create accounts',
                'default' => true,
                'public' => true,
            ],
            'enable_api' => [
                'type' => 'feature',
                'label' => 'API Access',
                'hint' => 'Enable REST API endpoints',
                'default' => true,
            ],
            'maintenance_mode' => [
                'type' => 'feature',
                'label' => 'Maintenance Mode',
                'hint' => 'Show maintenance page to non-admin users',
                'default' => false,
                'public' => true,
            ],
            'require_2fa_for_admins' => [
                'type' => 'feature',
                'label' => 'Require 2FA for Admins',
                'hint' => 'Require all admin users to enable two-factor authentication',
                'default' => false,
            ],
            'enable_2fa_for_users' => [
                'type' => 'feature',
                'label' => '2FA for Users',
                'hint' => 'Allow users to enable two-factor authentication on their accounts',
                'default' => true,
            ],
            'require_2fa_for_users' => [
                'type' => 'feature',
                'label' => 'Require 2FA for Users',
                'hint' => 'Require all users to enable two-factor authentication',
                'default' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy & Cookie Settings
    |--------------------------------------------------------------------------
    */
    'privacy_cookies' => [
        'label' => 'Privacy & Cookies',
        'icon' => 'ph ph-cookie',
        'description' => 'Cookie consent banner and privacy policy links',
        'settings' => [
            'cookie_popup_enabled' => [
                'type' => 'feature',
                'label' => 'Cookie Popup',
                'hint' => 'Show the cookie consent notice on public frontend pages',
                'default' => true,
                'public' => true,
            ],
            'cookie_popup_title' => [
                'type' => 'text',
                'label' => 'Popup Title',
                'hint' => 'Heading shown in the cookie consent popup',
                'default' => 'We use cookies',
                'rules' => 'nullable|string|max:120',
                'public' => true,
            ],
            'cookie_popup_message' => [
                'type' => 'textarea',
                'label' => 'Popup Message',
                'hint' => 'Short explanation shown in the cookie consent popup',
                'default' => 'We use cookies to improve your browsing experience, analyze site traffic, and personalize content. By clicking accept, you consent to our use of cookies.',
                'rules' => 'nullable|string|max:600',
                'public' => true,
            ],
            'cookie_popup_accept_label' => [
                'type' => 'text',
                'label' => 'Accept Button Label',
                'hint' => 'Button text used to accept cookies',
                'default' => 'Accept',
                'rules' => 'nullable|string|max:60',
                'public' => true,
            ],
            'cookie_popup_policy_label' => [
                'type' => 'text',
                'label' => 'Policy Link Label',
                'hint' => 'Text for the cookie policy link',
                'default' => 'Cookie Policy',
                'rules' => 'nullable|string|max:80',
                'public' => true,
            ],
            'cookie_popup_policy_url' => [
                'type' => 'text',
                'label' => 'Cookie Policy URL',
                'hint' => 'Frontend URL for the cookie policy page',
                'default' => '/cookie-policy',
                'rules' => 'nullable|string|max:255',
                'public' => true,
            ],
            'cookie_popup_lifetime_days' => [
                'type' => 'number',
                'label' => 'Cookie Lifetime Days',
                'hint' => 'How long acceptance should be remembered',
                'default' => 365,
                'rules' => 'required|integer|min:1|max:3650',
                'public' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channel Settings
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'label' => 'Notifications',
        'icon' => 'ph ph-bell-ringing',
        'description' => 'Configure notification channels, SMS providers, and push notification settings',
        'layout' => 'full',
        'card_groups' => true,
        'settings' => [
            'enable_email_notifications' => [
                'type' => 'feature',
                'label' => 'Email Notifications',
                'hint' => 'Send notifications via email using templates',
                'default' => true,
                'card_group' => [
                    'label' => 'Email Channel',
                    'icon' => 'ph ph-envelope-simple',
                    'description' => 'Email delivery and template-based notifications',
                ],
            ],
            'enable_sms_notifications' => [
                'type' => 'feature',
                'label' => 'SMS Notifications',
                'hint' => 'Send SMS notifications via Vonage, Twilio, or log driver',
                'default' => false,
                'card_group' => [
                    'label' => 'SMS Channel',
                    'icon' => 'ph ph-device-mobile',
                    'description' => 'Gateway selection and SMS delivery credentials',
                ],
            ],
            'enable_push_notifications' => [
                'type' => 'feature',
                'label' => 'Web Push Notifications',
                'hint' => 'Browser push notifications via VAPID protocol',
                'default' => false,
                'card_group' => [
                    'label' => 'Web Push',
                    'icon' => 'ph ph-browser',
                    'description' => 'Browser push notifications using VAPID keys',
                ],
            ],
            'enable_mobile_push_notifications' => [
                'type' => 'feature',
                'label' => 'Mobile Push Notifications',
                'hint' => 'Mobile push notifications via Firebase Cloud Messaging',
                'default' => false,
                'card_group' => [
                    'label' => 'Mobile Push',
                    'icon' => 'ph ph-device-tablet-speaker',
                    'description' => 'Firebase credentials for mobile push delivery',
                ],
            ],
            'sms_provider' => [
                'type' => 'select',
                'label' => 'SMS Provider',
                'hint' => 'Choose which SMS gateway to use for sending messages',
                'default' => 'log',
                'card_group' => [
                    'label' => 'SMS Channel',
                ],
                'options' => [
                    'log' => 'Log (Development)',
                    'vonage' => 'Vonage (Nexmo)',
                    'twilio' => 'Twilio',
                ],
            ],
            'sms_from_number' => [
                'type' => 'text',
                'label' => 'SMS From Number',
                'hint' => 'The phone number SMS messages are sent from (e.g. +1234567890)',
                'default' => '',
                'card_group' => [
                    'label' => 'SMS Channel',
                ],
                'rules' => 'nullable|string|max:20',
            ],
            'vonage_api_key' => [
                'type' => 'text',
                'label' => 'Vonage API Key',
                'hint' => 'Your Vonage API key from the dashboard',
                'default' => '',
                'card_group' => [
                    'label' => 'SMS Channel',
                ],
                'rules' => 'nullable|string|max:255',
            ],
            'vonage_api_secret' => [
                'type' => 'password',
                'label' => 'Vonage API Secret',
                'hint' => 'Your Vonage API secret',
                'default' => '',
                'card_group' => [
                    'label' => 'SMS Channel',
                ],
                'rules' => 'nullable|string|max:255',
            ],
            'twilio_sid' => [
                'type' => 'text',
                'label' => 'Twilio Account SID',
                'hint' => 'Your Twilio account SID',
                'default' => '',
                'card_group' => [
                    'label' => 'SMS Channel',
                ],
                'rules' => 'nullable|string|max:255',
            ],
            'twilio_auth_token' => [
                'type' => 'password',
                'label' => 'Twilio Auth Token',
                'hint' => 'Your Twilio authentication token',
                'default' => '',
                'card_group' => [
                    'label' => 'SMS Channel',
                ],
                'rules' => 'nullable|string|max:255',
            ],
            'vapid_public_key' => [
                'type' => 'textarea',
                'label' => 'VAPID Public Key',
                'hint' => 'Public key for Web Push (VAPID). Generate with: php artisan webpush:vapid',
                'default' => '',
                'card_group' => [
                    'label' => 'Web Push',
                ],
                'rules' => 'nullable|string',
            ],
            'vapid_private_key' => [
                'type' => 'textarea',
                'label' => 'VAPID Private Key',
                'hint' => 'Private key for Web Push (VAPID)',
                'default' => '',
                'card_group' => [
                    'label' => 'Web Push',
                ],
                'rules' => 'nullable|string',
            ],
            'firebase_credentials_json' => [
                'type' => 'textarea',
                'label' => 'Firebase Service Account JSON',
                'hint' => 'Paste the contents of your Firebase service account JSON key file',
                'default' => '',
                'card_group' => [
                    'label' => 'Mobile Push',
                ],
                'rules' => 'nullable|string',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'label' => 'Storage',
        'icon' => 'ph ph-hard-drives',
        'description' => 'Where uploaded files and media are stored',
        'settings' => [
            'storage_provider' => [
                'type' => 'tile_select',
                'label' => 'Storage Provider',
                'hint' => 'Local keeps files on this server; S3 and Cloudflare R2 store them in the cloud',
                'default' => 'local',
                'rules' => 'required|in:local,s3,r2',
                'options' => [
                    'local' => 'Local Server (/uploads)',
                    's3' => 'Amazon S3',
                    'r2' => 'Cloudflare R2',
                ],
                'tile_options' => [
                    'local' => [
                        'label' => 'Local Server',
                        'description' => 'Files stay on this server in /uploads',
                        'icon' => 'ph ph-hard-drives',
                    ],
                    's3' => [
                        'label' => 'Amazon S3',
                        'description' => 'Store files in an AWS S3 bucket',
                        'icon' => 'ph ph-cloud',
                    ],
                    'r2' => [
                        'label' => 'Cloudflare R2',
                        'description' => 'S3-compatible storage with no egress fees',
                        'icon' => 'ph ph-cloud-arrow-up',
                    ],
                ],
            ],
            'storage_s3_key' => [
                'type' => 'text',
                'label' => 'Access Key ID',
                'hint' => 'S3 / R2 access key ID',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'visible_if' => [
                    'storage_provider' => ['s3', 'r2'],
                ],
            ],
            'storage_s3_secret' => [
                'type' => 'password',
                'label' => 'Secret Access Key',
                'hint' => 'S3 / R2 secret access key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'visible_if' => [
                    'storage_provider' => ['s3', 'r2'],
                ],
            ],
            'storage_s3_region' => [
                'type' => 'text',
                'label' => 'Region',
                'hint' => 'AWS region, e.g. us-east-1',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'visible_if' => [
                    'storage_provider' => ['s3'],
                ],
            ],
            'storage_s3_bucket' => [
                'type' => 'text',
                'label' => 'Bucket',
                'hint' => 'Name of the S3 / R2 bucket',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'visible_if' => [
                    'storage_provider' => ['s3', 'r2'],
                ],
            ],
            'storage_s3_endpoint' => [
                'type' => 'text',
                'label' => 'Endpoint',
                'hint' => 'Your R2 S3 API endpoint',
                'default' => '',
                'rules' => 'nullable|url|max:255',
                'visible_if' => [
                    'storage_provider' => ['r2'],
                ],
            ],
            'storage_s3_url' => [
                'type' => 'text',
                'label' => 'Public URL',
                'hint' => 'Public base URL for stored files',
                'default' => '',
                'rules' => 'nullable|url|max:255',
                'visible_if' => [
                    'storage_provider' => ['s3', 'r2'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plugins
    |--------------------------------------------------------------------------
    */
    'plugins' => [
        'label' => 'Plugins',
        'icon' => 'ph ph-plug',
        'description' => 'Third-party integrations for analytics, live chat, and bot protection',
        'layout' => 'full',
        'card_groups' => true,
        'settings' => [
            'plugin_ga4_enabled' => [
                'type' => 'feature',
                'label' => 'Google Analytics 4',
                'hint' => 'Track visitors with Google Analytics (GA4)',
                'default' => false,
                'card_group' => [
                    'label' => 'Google Analytics 4',
                    'icon' => 'ph ph-chart-line-up',
                    'description' => 'Website traffic and event analytics',
                ],
            ],
            'plugin_ga4_measurement_id' => [
                'type' => 'text',
                'label' => 'Measurement ID',
                'hint' => 'Your GA4 measurement ID (e.g. G-XXXXXXXXXX)',
                'default' => '',
                'rules' => 'nullable|string|max:50',
                'public' => true,
                'card_group' => ['label' => 'Google Analytics 4'],
                'visible_if' => ['plugin_ga4_enabled' => [true, '1', 1]],
            ],
            'plugin_tawk_enabled' => [
                'type' => 'feature',
                'label' => 'Live Chat (Tawk.to)',
                'hint' => 'Show the Tawk.to live chat widget on public pages',
                'default' => false,
                'card_group' => [
                    'label' => 'Live Chat',
                    'icon' => 'ph ph-chat-circle-dots',
                    'description' => 'Tawk.to live chat widget',
                ],
            ],
            'plugin_tawk_property_id' => [
                'type' => 'text',
                'label' => 'Property ID',
                'hint' => 'Tawk.to property ID, or paste the full embed URL from your Tawk.to widget script',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'public' => true,
                'card_group' => ['label' => 'Live Chat'],
                'visible_if' => ['plugin_tawk_enabled' => [true, '1', 1]],
            ],
            'plugin_tawk_widget_id' => [
                'type' => 'text',
                'label' => 'Widget ID',
                'hint' => 'Second ID from your embed URL: https://embed.tawk.to/{property_id}/{widget_id}',
                'default' => '',
                'rules' => 'nullable|string|max:100',
                'public' => true,
                'card_group' => ['label' => 'Live Chat'],
                'visible_if' => ['plugin_tawk_enabled' => [true, '1', 1]],
            ],
            'plugin_turnstile_enabled' => [
                'type' => 'feature',
                'label' => 'Google reCAPTCHA',
                'hint' => 'Protect auth forms with Google reCAPTCHA v2 checkbox.',
                'default' => false,
                'card_group' => [
                    'label' => 'Google reCAPTCHA',
                    'icon' => 'ph ph-shield-check',
                    'description' => 'Google reCAPTCHA v2 checkbox protection',
                ],
            ],
            'plugin_turnstile_site_key' => [
                'type' => 'text',
                'label' => 'Site Key',
                'hint' => 'Google reCAPTCHA v2 site key for this app domain',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'public' => true,
                'card_group' => ['label' => 'Google reCAPTCHA'],
                'visible_if' => ['plugin_turnstile_enabled' => [true, '1', 1]],
            ],
            'plugin_turnstile_secret_key' => [
                'type' => 'password',
                'label' => 'Secret Key',
                'hint' => 'Google reCAPTCHA v2 secret key for server-side verification',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'card_group' => ['label' => 'Google reCAPTCHA'],
                'visible_if' => ['plugin_turnstile_enabled' => [true, '1', 1]],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Social Login
    |--------------------------------------------------------------------------
    */
    'social' => [
        'label' => 'Social Login',
        'icon' => 'ph ph-users-three',
        'description' => 'OAuth sign-in providers for the public login and registration pages',
        'layout' => 'full',
        'card_groups' => true,
        'settings' => [
            'social_google_enabled' => [
                'type' => 'feature',
                'label' => 'Google',
                'hint' => 'Allow users to sign in with their Google account',
                'default' => false,
                'card_group' => [
                    'label' => 'Google',
                    'icon' => 'ph ph-google-logo',
                    'description' => 'Sign in with Google',
                ],
            ],
            'social_google_client_id' => [
                'type' => 'text',
                'label' => 'Client ID',
                'hint' => 'OAuth client ID from Google Cloud Console',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'card_group' => ['label' => 'Google'],
                'visible_if' => ['social_google_enabled' => [true, '1', 1]],
            ],
            'social_google_client_secret' => [
                'type' => 'password',
                'label' => 'Client Secret',
                'hint' => 'OAuth client secret from Google Cloud Console',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'card_group' => ['label' => 'Google'],
                'visible_if' => ['social_google_enabled' => [true, '1', 1]],
            ],
            'social_google_callback_url' => [
                'type' => 'readonly_url',
                'label' => 'Callback URL',
                'hint' => 'Copy this URL into Google Cloud Console as the authorized redirect URI',
                'readonly' => true,
                'value_resolver' => 'social_callback_url',
                'provider' => 'google',
                'card_group' => ['label' => 'Google'],
                'visible_if' => ['social_google_enabled' => [true, '1', 1]],
            ],
            'social_facebook_enabled' => [
                'type' => 'feature',
                'label' => 'Facebook',
                'hint' => 'Allow users to sign in with their Facebook account',
                'default' => false,
                'card_group' => [
                    'label' => 'Facebook',
                    'icon' => 'ph ph-facebook-logo',
                    'description' => 'Sign in with Facebook',
                ],
            ],
            'social_facebook_client_id' => [
                'type' => 'text',
                'label' => 'App ID',
                'hint' => 'App ID from Meta for Developers',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'card_group' => ['label' => 'Facebook'],
                'visible_if' => ['social_facebook_enabled' => [true, '1', 1]],
            ],
            'social_facebook_client_secret' => [
                'type' => 'password',
                'label' => 'App Secret',
                'hint' => 'App secret from Meta for Developers',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'card_group' => ['label' => 'Facebook'],
                'visible_if' => ['social_facebook_enabled' => [true, '1', 1]],
            ],
            'social_facebook_callback_url' => [
                'type' => 'readonly_url',
                'label' => 'Callback URL',
                'hint' => 'Copy this URL into Meta for Developers as the valid OAuth redirect URI',
                'readonly' => true,
                'value_resolver' => 'social_callback_url',
                'provider' => 'facebook',
                'card_group' => ['label' => 'Facebook'],
                'visible_if' => ['social_facebook_enabled' => [true, '1', 1]],
            ],
            'social_github_enabled' => [
                'type' => 'feature',
                'label' => 'GitHub',
                'hint' => 'Allow users to sign in with their GitHub account',
                'default' => false,
                'card_group' => [
                    'label' => 'GitHub',
                    'icon' => 'ph ph-github-logo',
                    'description' => 'Sign in with GitHub',
                ],
            ],
            'social_github_client_id' => [
                'type' => 'text',
                'label' => 'Client ID',
                'hint' => 'OAuth client ID from GitHub Developer settings',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'card_group' => ['label' => 'GitHub'],
                'visible_if' => ['social_github_enabled' => [true, '1', 1]],
            ],
            'social_github_client_secret' => [
                'type' => 'password',
                'label' => 'Client Secret',
                'hint' => 'OAuth client secret from GitHub Developer settings',
                'default' => '',
                'rules' => 'nullable|string|max:255',
                'card_group' => ['label' => 'GitHub'],
                'visible_if' => ['social_github_enabled' => [true, '1', 1]],
            ],
            'social_github_callback_url' => [
                'type' => 'readonly_url',
                'label' => 'Callback URL',
                'hint' => 'Copy this URL into GitHub OAuth App as the authorization callback URL',
                'readonly' => true,
                'value_resolver' => 'social_callback_url',
                'provider' => 'github',
                'card_group' => ['label' => 'GitHub'],
                'visible_if' => ['social_github_enabled' => [true, '1', 1]],
            ],
        ],
    ],

];
