<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Template Definitions
    |--------------------------------------------------------------------------
    |
    | Define all notification templates here. Each key is a unique slug.
    | The seeder and notification:sync command read this file to populate
    | the database. Once an admin edits a template in the UI, their changes
    | are preserved — sync only creates new templates, never overwrites.
    |
    | Structure:
    |   'slug' => [
    |       'name'        => 'Human-readable name',
    |       'description' => 'What triggers this notification',
    |       'channels'    => ['email', 'in_app', 'sms', 'web_push', 'mobile_push'],
    |       'variables'   => ['var_name' => 'Description for admin UI'],
    |       'defaults'    => [
    |           'email_subject' => 'Subject with {{var_name}} placeholders',
    |           'email_body'    => '<p>HTML body with {{var_name}}</p>',
    |           'sms_body'      => 'Plain text with {{var_name}}',
    |           'in_app_title'  => 'Title with {{var_name}}',
    |           'in_app_body'   => 'Body with {{var_name}}',
    |           'push_title'    => 'Push title with {{var_name}}',
    |           'push_body'     => 'Push body with {{var_name}}',
    |       ],
    |   ],
    |
    | Global variables (always available, no need to define):
    |   {{site_name}}, {{site_url}}, {{current_year}}
    |
    */

    'welcome' => [
        'name' => 'Welcome',
        'description' => 'Sent to new users after registration',
        'channels' => ['email', 'in_app'],
        'variables' => [
            'user_name' => 'The registered user\'s name',
            'login_url' => 'URL to the login page',
        ],
        'defaults' => [
            'email_subject' => 'Welcome to {{site_name}}, {{user_name}}!',
            'email_body' => '<p>Hello {{user_name}},</p><p>Welcome to {{site_name}}! Your account has been created successfully.</p><p>Click the button below to get started.</p>',
            'in_app_title' => 'Welcome, {{user_name}}!',
            'in_app_body' => 'Your account has been created successfully. Start exploring the platform.',
        ],
    ],

    'password-changed' => [
        'name' => 'Password Changed',
        'description' => 'Sent when a user changes their password',
        'channels' => ['email'],
        'variables' => [
            'user_name' => 'The user\'s name',
            'changed_at' => 'Date and time of the change',
        ],
        'defaults' => [
            'email_subject' => 'Your password has been changed',
            'email_body' => '<p>Hi {{user_name}},</p><p>Your password was successfully changed on {{changed_at}}.</p><p>If you did not make this change, please contact support immediately.</p>',
        ],
    ],

    'subscription-expiring-soon' => [
        'name' => 'Subscription Expiring Soon',
        'description' => 'Sent to workspace owners one day before their subscription expires',
        'channels' => ['email', 'sms', 'in_app', 'web_push', 'mobile_push'],
        'variables' => [
            'user_name' => 'The workspace owner name',
            'workspace_name' => 'The workspace name',
            'plan_name' => 'The current plan name',
            'expires_at' => 'The subscription expiry date',
            'renew_url' => 'The subscription renewal URL',
            'days_remaining' => 'Days remaining before expiry',
        ],
        'defaults' => [
            'email_subject' => 'Your {{plan_name}} plan expires soon',
            'email_body' => '<p>Hello {{user_name}},</p><p>Your {{plan_name}} plan for {{workspace_name}} expires on {{expires_at}}.</p><p>Please renew your subscription to keep your services running.</p>',
            'sms_body' => '{{site_name}}: Your {{plan_name}} plan expires on {{expires_at}}. Renew: {{renew_url}}',
            'in_app_title' => 'Your plan expires soon',
            'in_app_body' => '{{plan_name}} for {{workspace_name}} expires on {{expires_at}}.',
            'push_title' => 'Your plan expires soon',
            'push_body' => '{{plan_name}} expires on {{expires_at}}.',
        ],
    ],

    'subscription-expired' => [
        'name' => 'Subscription Expired',
        'description' => 'Sent to workspace owners when their subscription is marked expired',
        'channels' => ['email', 'sms', 'in_app', 'web_push', 'mobile_push'],
        'variables' => [
            'user_name' => 'The workspace owner name',
            'workspace_name' => 'The workspace name',
            'plan_name' => 'The expired plan name',
            'expires_at' => 'The subscription expiry date',
            'renew_url' => 'The subscription renewal URL',
            'days_remaining' => 'Days remaining before expiry',
        ],
        'defaults' => [
            'email_subject' => 'Your {{plan_name}} plan has expired',
            'email_body' => '<p>Hello {{user_name}},</p><p>Your {{plan_name}} plan for {{workspace_name}} expired on {{expires_at}}.</p><p>Your workspace is now read-only until you renew.</p>',
            'sms_body' => '{{site_name}}: Your {{plan_name}} plan has expired. Renew: {{renew_url}}',
            'in_app_title' => 'Your plan has expired',
            'in_app_body' => '{{workspace_name}} is read-only until you renew {{plan_name}}.',
            'push_title' => 'Your plan has expired',
            'push_body' => '{{workspace_name}} is read-only until renewal.',
        ],
    ],

];
