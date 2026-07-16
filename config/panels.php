<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registered Panels
    |--------------------------------------------------------------------------
    |
    | Add a panel  → add one line here + run make:panel
    | Remove panel → delete the line + delete the folder from app/Panels/
    |
    | Navigation items support these keys:
    |   - label:      Display text
    |   - icon:       Phosphor icon class (e.g. 'ph-house')
    |   - route:      Route name pattern for active-state matching (e.g. 'admin.users.*')
    |   - permission: Optional permission string – item hidden if user lacks it
    |   - group:      Sidebar group heading (default: 'Main Menu')
    |   - children:   Optional array of sub-items [['label' => '', 'route' => '']]
    |
    */

    'admin' => [
        'name' => 'Admin Panel',
        'prefix' => 'admin',                          // URL: yoursite.com/admin/*
        'middleware' => ['web', 'auth:admin', '2fa', 'panel:admin'],
        'roles' => [],                               // Empty = any authenticated admin user
        'guard' => 'admin',                          // Uses admin guard → admins table
        'theme' => 'dark',
        'components' => 'default',                        // Fixed design, uses shared components
        'active' => true,

        'navigation' => [
            [
                'label' => 'Dashboard',
                'icon' => 'ph-house',
                'route' => 'admin.dashboard',
                'group' => 'Main Menu',
            ],
            [
                'label' => 'Users',
                'icon' => 'ph-users',
                'route' => 'admin.users.*',
                'group' => 'Management',
                'permission' => 'users.view',
            ],
        ],
    ],

    'user' => [
        'name' => 'WhatsApp Marketing',
        'prefix' => 'dashboard',                      // URL: yoursite.com/dashboard/*
        'middleware' => ['web', 'auth', 'verified', '2fa', 'panel:user'],
        'roles' => [],                               // Empty = all authenticated users
        'guard' => 'web',                            // Uses web guard → users table
        'theme' => 'light',
        'components' => 'default',
        'active' => true,

        // Navigation is rendered directly in the portal layout (layouts/user.blade.php)
        'navigation' => [],
    ],

];
