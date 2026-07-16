<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Permission Definitions
    |--------------------------------------------------------------------------
    |
    | Define all permissions per module. The sync command reads this file
    | and ensures the database matches. Add new permissions here and
    | run: php artisan permission:sync
    |
    */

    'modules' => [
        'users' => [
            'guard' => 'admin',
            'permissions' => ['view', 'create', 'edit', 'delete'],
        ],
        'roles' => [
            'guard' => 'admin',
            'permissions' => ['view', 'create', 'edit', 'delete'],
        ],
        'dashboard' => [
            'guard' => 'admin',
            'permissions' => ['view'],
        ],
        // User panel permissions (web guard)
        'user-dashboard' => [
            'guard' => 'web',
            'permissions' => ['view'],
            'prefix' => 'dashboard',  // generates dashboard.view instead of user-dashboard.view
        ],
        'profile' => [
            'guard' => 'web',
            'permissions' => ['edit'],
        ],
        'staffs' => [
            'guard' => 'admin',
            'permissions' => ['view', 'create', 'edit', 'delete'],
        ],
        'job-postings' => [
            'guard' => 'admin',
            'permissions' => ['view', 'create', 'edit', 'delete'],
        ],
        'job-applications' => [
            'guard' => 'admin',
            'permissions' => ['view', 'edit', 'delete'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Definitions
    |--------------------------------------------------------------------------
    |
    | Define roles and their assigned permissions. Super-admin gets
    | all permissions via Gate::before, so no explicit assignment needed.
    |
    */

    'roles' => [
        // Admin guard roles
        'super-admin' => [
            'guard' => 'admin',
            'permissions' => [],  // Gets all via Gate::before
        ],
        // Web guard roles
        'user' => [
            'guard' => 'web',
            'permissions' => '*',  // All web-guard permissions
        ],
    ],
];
