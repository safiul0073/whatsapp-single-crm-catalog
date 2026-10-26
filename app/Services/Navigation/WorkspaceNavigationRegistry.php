<?php

namespace App\Services\Navigation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class WorkspaceNavigationRegistry
{
    /**
     * Build the filtered two-level navigation tree for the given user and request.
     *
     * @return array{
     *     active_module: string,
     *     has_route_context: bool,
     *     modules: list<array<string, mixed>>,
     *     flat_items: list<array<string, mixed>>
     * }
     */
    public function forUser(?Authenticatable $user, ?Request $request = null): array
    {
        $request ??= request();
        $currentRouteName = $request->route()?->getName();
        $hasRouteContext = is_string($currentRouteName) && $currentRouteName !== '';

        $visibleModules = [];
        $flatItems = [];

        foreach ($this->definitions() as $module) {
            $filteredGroups = [];
            $firstUrl = null;
            $firstRoute = null;
            $isModuleActive = false;

            foreach ($module['route_patterns'] as $pattern) {
                if ($request->routeIs($pattern)) {
                    $isModuleActive = true;
                    break;
                }
            }

            foreach ($module['groups'] as $group) {
                $visibleItems = [];

                foreach ($group['items'] as $item) {
                    if (! Route::has($item['route']) || ! $this->canAccess($user, $item['permission'] ?? null)) {
                        continue;
                    }

                    $activePatterns = (array) ($item['active'] ?? $item['route']);
                    $isItemActive = $request->routeIs(...$activePatterns);

                    if ($isItemActive) {
                        $isModuleActive = true;
                    }

                    $url = route($item['route']);

                    if ($firstUrl === null) {
                        $firstUrl = $url;
                        $firstRoute = $item['route'];
                    }

                    $resolvedItem = [
                        'id' => $item['route'],
                        'label' => $item['label'],
                        'route' => $item['route'],
                        'url' => $url,
                        'active' => $activePatterns,
                        'is_active' => $isItemActive,
                        'icon' => $item['icon'],
                        'permission' => $item['permission'] ?? null,
                        'badge' => $item['badge'] ?? null,
                        'keywords' => $item['keywords'] ?? '',
                        'module_id' => $module['id'],
                        'module_label' => $module['label'],
                        'group_label' => $group['label'],
                    ];

                    $visibleItems[] = $resolvedItem;
                    $flatItems[] = $resolvedItem;
                }

                if ($visibleItems !== []) {
                    $filteredGroups[] = [
                        'label' => $group['label'],
                        'items' => $visibleItems,
                    ];
                }
            }

            if ($filteredGroups === [] || $firstUrl === null) {
                continue;
            }

            $visibleModules[] = [
                'id' => $module['id'],
                'label' => $module['label'],
                'short_label' => $module['short_label'] ?? $module['label'],
                'icon' => $module['icon'],
                'route' => $firstRoute,
                'url' => $firstUrl,
                'is_active' => $isModuleActive,
                'groups' => $filteredGroups,
            ];
        }

        $activeModuleId = 'dashboard';
        foreach ($visibleModules as $module) {
            if ($module['is_active']) {
                $activeModuleId = $module['id'];
                break;
            }
        }

        if ($visibleModules !== [] && ! collect($visibleModules)->contains('id', $activeModuleId)) {
            $activeModuleId = $visibleModules[0]['id'];
        }

        $activeModuleData = null;
        foreach ($visibleModules as &$module) {
            $module['is_active'] = $module['id'] === $activeModuleId;
            if ($module['is_active']) {
                $activeModuleData = $module;
            }
        }
        unset($module);

        return [
            'active_module' => $activeModuleId,
            'active_module_data' => $activeModuleData,
            'has_route_context' => $hasRouteContext,
            'modules' => $visibleModules,
            'flat_items' => $flatItems,
        ];
    }

    /**
     * Build searchable navigation groups for GlobalSearchController.
     *
     * @return list<array{label: string, icon: string, items: list<array{id: string, label: string, url: string, search: string}>}>
     */
    public function searchableGroups(?Authenticatable $user, ?Request $request = null): array
    {
        $navigation = $this->forUser($user, $request);
        $groups = [];

        foreach ($navigation['modules'] as $module) {
            $items = [];

            foreach ($module['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    $label = $item['label'];
                    $keywords = $item['keywords'] ?? '';

                    $items[] = [
                        'id' => $item['route'],
                        'label' => $label,
                        'url' => $item['url'],
                        'search' => "{$label} {$module['label']} {$group['label']} {$keywords} {$item['route']}",
                    ];
                }
            }

            if ($items !== []) {
                $groups[] = [
                    'label' => $module['label'],
                    'icon' => $module['icon'],
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    /**
     * Determine whether the given user passes the permission expression.
     */
    public function canAccess(?Authenticatable $user, ?string $permission): bool
    {
        if (! $permission) {
            return true;
        }

        if (! $user || ! method_exists($user, 'canAny')) {
            return false;
        }

        if (str_contains($permission, '&')) {
            foreach (explode('&', $permission) as $requiredPermission) {
                $orPermissions = array_filter(array_map('trim', explode('|', $requiredPermission)));
                if ($orPermissions !== [] && ! $user->canAny($orPermissions)) {
                    return false;
                }
            }

            return true;
        }

        $permissions = array_filter(array_map('trim', explode('|', $permission)));

        return $user->canAny($permissions);
    }

    /**
     * Centralized two-level module and contextual sidebar definitions.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'id' => 'dashboard',
                'label' => __('Dashboard'),
                'short_label' => __('Dashboard'),
                'icon' => 'ph-house',
                'route_patterns' => [
                    'user.dashboard',
                    'user.workspaces.*',
                    'user.support-tickets.*',
                    'user.system-notifications.*',
                    'user.profile.*',
                    'user.two-factor.*',
                    'user.subscription.*',
                ],
                'groups' => [
                    [
                        'label' => __('Workspace'),
                        'items' => [
                            [
                                'label' => __('Dashboard'),
                                'route' => 'user.dashboard',
                                'active' => 'user.dashboard',
                                'icon' => 'ph-house',
                                'permission' => 'workspace.view',
                                'keywords' => 'home overview stats analytics kpi performance',
                            ],
                            [
                                'label' => __('Team & Members'),
                                'route' => 'user.workspaces.team',
                                'active' => 'user.workspaces.team*',
                                'icon' => 'ph-users',
                                'permission' => 'team.manage',
                                'keywords' => 'staff roles permissions members invite',
                            ],
                        ],
                    ],
                    [
                        'label' => __('Account'),
                        'items' => [
                            [
                                'label' => __('Support'),
                                'route' => 'user.support-tickets.index',
                                'active' => 'user.support-tickets.*',
                                'icon' => 'ph-question',
                                'permission' => 'workspace.view',
                                'keywords' => 'help tickets issue support',
                            ],
                            [
                                'label' => __('Notifications'),
                                'route' => 'user.system-notifications.index',
                                'active' => 'user.system-notifications.*',
                                'icon' => 'ph-bell',
                                'permission' => 'workspace.view',
                                'badge' => 'notifications',
                                'keywords' => 'alerts messages bell notifications',
                            ],
                            [
                                'label' => __('My profile'),
                                'route' => 'user.profile.edit',
                                'active' => ['user.profile.*', 'user.two-factor.*'],
                                'icon' => 'ph-user-circle',
                                'permission' => 'workspace.view',
                                'keywords' => 'profile account password security locale timezone 2fa',
                            ],
                            [
                                'label' => __('Subscription'),
                                'route' => 'user.subscription.show',
                                'active' => 'user.subscription.*',
                                'icon' => 'ph-credit-card',
                                'permission' => 'subscription.manage',
                                'keywords' => 'billing plan subscription upgrade',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'inbox',
                'label' => __('Inbox'),
                'short_label' => __('Inbox'),
                'icon' => 'ph-chat-text',
                'route_patterns' => [
                    'user.inbox.*',
                ],
                'groups' => [
                    [
                        'label' => __('Conversations'),
                        'items' => [
                            [
                                'label' => __('Inbox'),
                                'route' => 'user.inbox.index',
                                'active' => 'user.inbox.*',
                                'icon' => 'ph-chat-text',
                                'permission' => 'inbox.view|inbox.assigned_only',
                                'keywords' => 'chat conversations messages replies omnichannel',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'crm',
                'label' => __('CRM'),
                'short_label' => __('CRM'),
                'icon' => 'ph-users-three',
                'route_patterns' => [
                    'user.contacts.*',
                    'user.crm.*',
                    'user.leads.*',
                    'user.groups.*',
                    'user.segments.*',
                    'user.tags.*',
                    'user.imports.*',
                ],
                'groups' => [
                    [
                        'label' => __('Customers'),
                        'items' => [
                            [
                                'label' => __('Contacts'),
                                'route' => 'user.contacts.index',
                                'active' => 'user.contacts.*',
                                'icon' => 'ph-users-three',
                                'permission' => 'contacts.view|contacts.assigned_only',
                                'keywords' => 'people customers phone directory',
                            ],
                            [
                                'label' => __('CRM'),
                                'route' => 'user.crm.index',
                                'active' => 'user.crm.*',
                                'icon' => 'ph-kanban',
                                'permission' => 'crm.view',
                                'keywords' => 'pipeline deals stages tasks board',
                            ],
                            [
                                'label' => __('Generated Leads'),
                                'route' => 'user.leads.index',
                                'active' => 'user.leads.*',
                                'icon' => 'ph-user-focus',
                                'permission' => 'leads.view',
                                'keywords' => 'prospects captured leads ai lead generation',
                            ],
                        ],
                    ],
                    [
                        'label' => __('Segmentation & Data'),
                        'items' => [
                            [
                                'label' => __('Groups'),
                                'route' => 'user.groups.index',
                                'active' => ['user.groups.*', 'user.segments.*'],
                                'icon' => 'ph-folders',
                                'permission' => 'contacts.manage',
                                'keywords' => 'segments lists contact groups audience',
                            ],
                            [
                                'label' => __('Contact Tags'),
                                'route' => 'user.tags.index',
                                'active' => 'user.tags.*',
                                'icon' => 'ph-tag',
                                'permission' => 'contacts.manage',
                                'keywords' => 'tags labels contact segmentation',
                            ],
                            [
                                'label' => __('Import History'),
                                'route' => 'user.imports.history',
                                'active' => 'user.imports.*',
                                'icon' => 'ph-upload-simple',
                                'permission' => 'contacts.manage',
                                'keywords' => 'csv import contacts upload history',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'marketing',
                'label' => __('Marketing'),
                'short_label' => __('Marketing'),
                'icon' => 'ph-paper-plane-tilt',
                'route_patterns' => [
                    'user.campaigns.*',
                    'user.message-templates.*',
                ],
                'groups' => [
                    [
                        'label' => __('Campaigns'),
                        'items' => [
                            [
                                'label' => __('Campaigns'),
                                'route' => 'user.campaigns.index',
                                'active' => 'user.campaigns.*',
                                'icon' => 'ph-paper-plane-tilt',
                                'permission' => 'campaigns.view',
                                'keywords' => 'broadcast send campaign marketing reports',
                            ],
                            [
                                'label' => __('Templates'),
                                'route' => 'user.message-templates.index',
                                'active' => 'user.message-templates.*',
                                'icon' => 'ph-file-text',
                                'permission' => 'templates.manage',
                                'keywords' => 'message templates whatsapp approval',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'automation',
                'label' => __('Automation & AI'),
                'short_label' => __('Automation'),
                'icon' => 'ph-robot',
                'route_patterns' => [
                    'user.automations.*',
                    'user.auto-replies.*',
                    'user.chatbots.*',
                    'user.knowledge-bases.*',
                ],
                'groups' => [
                    [
                        'label' => __('Automation'),
                        'items' => [
                            [
                                'label' => __('Automations'),
                                'route' => 'user.automations.index',
                                'active' => 'user.automations.*',
                                'icon' => 'ph-flow-arrow',
                                'permission' => 'automations.manage',
                                'keywords' => 'workflow builder trigger flow automation',
                            ],
                            [
                                'label' => __('Auto Replies'),
                                'route' => 'user.auto-replies.index',
                                'active' => 'user.auto-replies.*',
                                'icon' => 'ph-arrow-bend-up-left',
                                'permission' => 'automations.manage',
                                'keywords' => 'automatic reply autoresponder keyword rules',
                            ],
                            [
                                'label' => __('Chatbots'),
                                'route' => 'user.chatbots.index',
                                'active' => [
                                    'user.chatbots.index',
                                    'user.chatbots.create',
                                    'user.chatbots.config',
                                    'user.chatbots.store',
                                    'user.chatbots.update',
                                    'user.chatbots.toggle',
                                    'user.chatbots.destroy',
                                    'user.chatbots.test',
                                ],
                                'icon' => 'ph-robot',
                                'permission' => 'chatbots.manage',
                                'keywords' => 'bot ai assistant persona chatbot',
                            ],
                            [
                                'label' => __('Knowledge Bases'),
                                'route' => 'user.knowledge-bases.index',
                                'active' => 'user.knowledge-bases.*',
                                'icon' => 'ph-books',
                                'permission' => 'chatbots.manage',
                                'keywords' => 'knowledge documents training rag embeddings',
                            ],
                            [
                                'label' => __('Website Widgets'),
                                'route' => 'user.chatbots.widgets.index',
                                'active' => 'user.chatbots.widgets.*',
                                'icon' => 'ph-browser',
                                'permission' => 'chatbots.manage',
                                'keywords' => 'widget website embed live chat',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'commerce',
                'label' => __('Commerce'),
                'short_label' => __('Commerce'),
                'icon' => 'ph-shopping-bag',
                'route_patterns' => [
                    'user.commerce.*',
                    'user.shipping.*',
                ],
                'groups' => [
                    [
                        'label' => __('Commerce'),
                        'items' => [
                            [
                                'label' => __('POS'),
                                'route' => 'user.commerce.pos.index',
                                'active' => 'user.commerce.pos.*',
                                'icon' => 'ph-cash-register',
                                'permission' => 'commerce.pos.use',
                                'keywords' => 'point of sale register checkout retail',
                            ],
                            [
                                'label' => __('Orders'),
                                'route' => 'user.commerce.orders.index',
                                'active' => [
                                    'user.commerce.orders.index',
                                    'user.commerce.orders.show',
                                    'user.commerce.orders.create',
                                    'user.commerce.orders.complete*',
                                    'user.commerce.orders.packing-slip',
                                    'user.commerce.orders.receipt',
                                ],
                                'icon' => 'ph-package',
                                'permission' => 'commerce.orders.view',
                                'keywords' => 'orders fulfillment packing slip invoices',
                            ],
                            [
                                'label' => __('Products'),
                                'route' => 'user.commerce.products.index',
                                'active' => 'user.commerce.products.*',
                                'icon' => 'ph-t-shirt',
                                'permission' => 'commerce.products.view',
                                'keywords' => 'products inventory catalog items',
                            ],
                            [
                                'label' => __('Customers'),
                                'route' => 'user.commerce.customers.index',
                                'active' => 'user.commerce.customers.*',
                                'icon' => 'ph-users',
                                'permission' => 'commerce.customers.view',
                                'keywords' => 'customers buyers accounts storefront mobile app',
                            ],
                            [
                                'label' => __('Storefront'),
                                'route' => 'user.commerce.storefront.index',
                                'active' => 'user.commerce.storefront.index',
                                'icon' => 'ph-browser',
                                'permission' => 'commerce.storefront.settings|commerce.storefront.pages|commerce.storefront.marketing|commerce.storefront.domains|commerce.storefront.reviews|commerce.storefront.credentials',
                                'keywords' => 'storefront theme colors domains pages sliders menus coupons reviews cms',
                            ],
                            [
                                'label' => __('Live Shopping'),
                                'route' => 'user.commerce.storefront.live',
                                'active' => 'user.commerce.storefront.live*',
                                'icon' => 'ph-broadcast',
                                'permission' => 'commerce.storefront.settings|commerce.storefront.marketing',
                                'keywords' => 'live stream broadcast webrtc reverb facebook simulcast video slider',
                            ],
                            [
                                'label' => __('Pack Templates'),
                                'route' => 'user.commerce.pack-templates.index',
                                'active' => 'user.commerce.pack-templates.*',
                                'icon' => 'ph-package',
                                'permission' => 'commerce.pack-templates.manage',
                                'keywords' => 'custom pack templates mixed wholesale bundles',
                            ],
                            [
                                'label' => __('Referrals'),
                                'route' => 'user.commerce.referrals.index',
                                'active' => 'user.commerce.referrals.*',
                                'icon' => 'ph-gift',
                                'permission' => 'commerce.referrals.view',
                                'keywords' => 'referral refer earn rewards wallet cash-in cashin target slabs',
                            ],
                            [
                                'label' => __('Meta Catalog'),
                                'route' => 'user.commerce.catalog',
                                'active' => 'user.commerce.catalog*',
                                'icon' => 'ph-storefront',
                                'permission' => 'commerce.catalog.manage',
                                'keywords' => 'meta facebook whatsapp catalog feed sync',
                            ],
                            [
                                'label' => __('Categories'),
                                'route' => 'user.commerce.categories.index',
                                'active' => 'user.commerce.categories.*',
                                'icon' => 'ph-tree-structure',
                                'permission' => 'commerce.products.view',
                                'keywords' => 'product categories taxonomy',
                            ],
                            [
                                'label' => __('Brands'),
                                'route' => 'user.commerce.brands.index',
                                'active' => 'user.commerce.brands.*',
                                'icon' => 'ph-seal-check',
                                'permission' => 'commerce.products.view',
                                'keywords' => 'product brands manufacturers',
                            ],
                            [
                                'label' => __('Audiences'),
                                'route' => 'user.commerce.audiences.index',
                                'active' => 'user.commerce.audiences.*',
                                'icon' => 'ph-users-three',
                                'permission' => 'commerce.products.view',
                                'keywords' => 'product audiences target',
                            ],
                            [
                                'label' => __('Variants & Sizes'),
                                'route' => 'user.commerce.variants.index',
                                'active' => 'user.commerce.variants.*',
                                'icon' => 'ph-squares-four',
                                'permission' => 'commerce.products.view',
                                'keywords' => 'variants sizes colors options presets',
                            ],
                            [
                                'label' => __('Payment services'),
                                'route' => 'user.commerce.payment-services.index',
                                'active' => 'user.commerce.payment-services.*',
                                'icon' => 'ph-credit-card',
                                'permission' => 'commerce.orders.settings',
                                'keywords' => 'payments methods bank mobile money',
                            ],
                            [
                                'label' => __('Order settings'),
                                'route' => 'user.commerce.orders.settings',
                                'active' => 'user.commerce.orders.settings*',
                                'icon' => 'ph-gear',
                                'permission' => 'commerce.orders.settings',
                                'keywords' => 'order settings rules reservation',
                            ],
                        ],
                    ],
                    [
                        'label' => __('Shipping Management'),
                        'items' => [
                            [
                                'label' => __('Shipping Zones'),
                                'route' => 'user.shipping.index',
                                'active' => ['user.shipping.index', 'user.shipping.zones.*', 'user.shipping.rates.*'],
                                'icon' => 'ph-globe-hemisphere-west',
                                'permission' => 'shipping.manage',
                                'keywords' => 'shipping zones rates delivery regions',
                            ],
                            [
                                'label' => __('Shipping Methods'),
                                'route' => 'user.shipping.methods.index',
                                'active' => 'user.shipping.methods.*',
                                'icon' => 'ph-truck',
                                'permission' => 'shipping.manage',
                                'keywords' => 'shipping methods carriers couriers',
                            ],
                            [
                                'label' => __('Settings'),
                                'route' => 'user.shipping.settings',
                                'active' => 'user.shipping.settings.*',
                                'icon' => 'ph-gear',
                                'permission' => 'shipping.manage',
                                'keywords' => 'shipping configuration settings',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'channels',
                'label' => __('Channels'),
                'short_label' => __('Channels'),
                'icon' => 'ph-plugs-connected',
                'route_patterns' => [
                    'user.channels.*',
                    'user.whatsapp-cloud.*',
                    'user.meta-social.*',
                    'user.telegram.*',
                    'user.email.*',
                    'user.sms.*',
                    'user.social-widgets.*',
                ],
                'groups' => [
                    [
                        'label' => __('Channels'),
                        'items' => [
                            [
                                'label' => __('All channels'),
                                'route' => 'user.channels.index',
                                'active' => 'user.channels.*',
                                'icon' => 'ph-plugs-connected',
                                'permission' => 'workspace.view',
                                'keywords' => 'channels connect hub integrations',
                            ],
                            [
                                'label' => __('WhatsApp Cloud'),
                                'route' => 'user.whatsapp-cloud.channel-setup',
                                'active' => 'user.whatsapp-cloud.channel-setup*',
                                'icon' => 'ph-whatsapp-logo',
                                'permission' => 'channels.manage',
                                'keywords' => 'whatsapp cloud api phone waba connect',
                            ],
                            [
                                'label' => __('Customer Login'),
                                'route' => 'user.whatsapp-cloud.customer-login',
                                'active' => 'user.whatsapp-cloud.customer-login*',
                                'icon' => 'ph-key',
                                'permission' => 'channels.manage&commerce.orders.settings',
                                'keywords' => 'whatsapp otp customer login auth storefront',
                            ],
                            [
                                'label' => __('Social'),
                                'route' => 'user.meta-social.setup',
                                'active' => 'user.meta-social.*',
                                'icon' => 'ph-share-network',
                                'permission' => 'meta-social.manage',
                                'keywords' => 'facebook messenger instagram threads social',
                            ],
                            [
                                'label' => __('Telegram'),
                                'route' => 'user.telegram.index',
                                'active' => 'user.telegram.*',
                                'icon' => 'ph-telegram-logo',
                                'permission' => 'telegram.manage',
                                'keywords' => 'telegram bot channel connect',
                            ],
                            [
                                'label' => __('Email'),
                                'route' => 'user.email.index',
                                'active' => 'user.email.*',
                                'icon' => 'ph-envelope-simple',
                                'permission' => 'email.manage',
                                'keywords' => 'email smtp channel setup',
                            ],
                            [
                                'label' => __('SMS'),
                                'route' => 'user.sms.index',
                                'active' => 'user.sms.*',
                                'icon' => 'ph-chat-text',
                                'permission' => 'sms.manage',
                                'keywords' => 'sms text gateway channel',
                            ],
                            [
                                'label' => __('Widgets'),
                                'route' => 'user.social-widgets.library',
                                'active' => 'user.social-widgets.*',
                                'icon' => 'ph-squares-four',
                                'permission' => 'meta-social.manage',
                                'keywords' => 'social widgets instagram feed embed',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
