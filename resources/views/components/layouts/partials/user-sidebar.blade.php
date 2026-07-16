@php
    $authUser = auth()->user();
    $siteName = 'WaPro';
    $canSeeSidebarItem = function (?string $permission) use ($authUser): bool {
        if (! $permission) {
            return true;
        }

        $permissions = explode('|', $permission);

        return $authUser->canAny($permissions);
    };

    $groups = [
        [
            'label' => __('Account'),
            'items' => [
                ['label' => __('Dashboard'), 'route' => 'user.dashboard', 'active' => 'user.dashboard', 'icon' => 'ph-house', 'permission' => 'workspace.view'],
            ],
        ],
        [
            'label' => __('Commerce'),
            'items' => [
                ['label' => __('Products'), 'route' => 'user.commerce.products.index', 'active' => 'user.commerce.products.*', 'icon' => 'ph-t-shirt', 'permission' => 'commerce.view'],
                ['label' => __('Categories'), 'route' => 'user.commerce.categories.index', 'active' => 'user.commerce.categories.*', 'icon' => 'ph-tree-structure', 'permission' => 'commerce.view'],
                ['label' => __('Brands'), 'route' => 'user.commerce.brands.index', 'active' => 'user.commerce.brands.*', 'icon' => 'ph-seal-check', 'permission' => 'commerce.view'],
                ['label' => __('Audiences'), 'route' => 'user.commerce.audiences.index', 'active' => 'user.commerce.audiences.*', 'icon' => 'ph-users-three', 'permission' => 'commerce.view'],
                ['label' => __('Orders'), 'route' => 'user.commerce.orders.index', 'active' => 'user.commerce.orders.*', 'icon' => 'ph-package', 'permission' => 'commerce.view'],
                ['label' => __('Meta Catalog'), 'route' => 'user.commerce.catalog', 'active' => 'user.commerce.catalog*', 'icon' => 'ph-storefront', 'permission' => 'commerce.manage'],
            ],
        ],
        [
            'label' => __('Inbox'),
            'items' => [
                ['label' => __('Inbox'), 'route' => 'user.inbox.index', 'active' => 'user.inbox.*', 'icon' => 'ph-chat-text', 'permission' => 'inbox.view|inbox.assigned_only'],
                ['label' => __('Channel Setup'), 'route' => 'user.whatsapp-cloud.channel-setup', 'active' => 'user.whatsapp-cloud.*', 'icon' => 'ph-gear-six', 'permission' => 'channels.manage'],
            ],
        ],
        [
            'label' => __('Messaging'),
            'items' => [
                ['label' => __('Templates'), 'route' => 'user.message-templates.index', 'active' => 'user.message-templates.*', 'icon' => 'ph-file-text', 'permission' => null],
                ['label' => __('Auto Replies'), 'route' => 'user.auto-replies.index', 'active' => 'user.auto-replies.*', 'icon' => 'ph-arrow-bend-up-left', 'permission' => 'automations.manage'],
            ],
        ],
        [
            'label' => __('Contacts'),
            'items' => [
                ['label' => __('Contacts'), 'route' => 'user.contacts.index', 'active' => 'user.contacts.*', 'icon' => 'ph-users-three', 'permission' => 'contacts.view'],
                ['label' => __('Leads'), 'route' => 'user.leads.index', 'active' => 'user.leads.*', 'icon' => 'ph-user-focus', 'permission' => 'leads.view'],
                ['label' => __('CRM'), 'route' => 'user.crm.index', 'active' => 'user.crm.*', 'icon' => 'ph-kanban', 'permission' => null],
                ['label' => __('Groups'), 'route' => 'user.groups.index', 'active' => ['user.groups.*', 'user.segments.*'], 'icon' => 'ph-folders', 'permission' => 'contacts.manage'],
            ],
        ],
        [
            'label' => __('Broadcasting'),
            'items' => [
                ['label' => __('Campaigns'), 'route' => 'user.campaigns.index', 'active' => 'user.campaigns.*', 'icon' => 'ph-paper-plane-tilt', 'permission' => 'campaigns.view'],
            ],
        ],
        [
            'label' => __('Automation & AI'),
            'items' => [
                ['label' => __('Automations'), 'route' => 'user.automations.index', 'active' => 'user.automations.*', 'icon' => 'ph-flow-arrow', 'permission' => 'automations.manage'],
                ['label' => __('Chatbots'), 'route' => 'user.chatbots.index', 'active' => ['user.chatbots.index', 'user.chatbots.create', 'user.chatbots.config', 'user.chatbots.store', 'user.chatbots.update', 'user.chatbots.toggle', 'user.chatbots.destroy', 'user.chatbots.test'], 'icon' => 'ph-robot', 'permission' => 'chatbots.manage'],
                ['label' => __('Knowledge Bases'), 'route' => 'user.knowledge-bases.index', 'active' => 'user.knowledge-bases.*', 'icon' => 'ph-books', 'permission' => 'chatbots.manage'],
                ['label' => __('Website Widgets'), 'route' => 'user.chatbots.widgets.index', 'active' => 'user.chatbots.widgets.*', 'icon' => 'ph-browser', 'permission' => 'chatbots.manage'],
            ],
        ],
        [
            'label' => __('Account'),
            'items' => [
                ['label' => __('Activity Log'), 'route' => 'user.audit-log.index', 'active' => 'user.audit-log.*', 'icon' => 'ph-list-magnifying-glass', 'permission' => 'workspace.view'],
                ['label' => __('Media'), 'route' => 'user.media.index', 'active' => 'user.media.*', 'icon' => 'ph-image-square', 'permission' => 'workspace.view'],
                ['label' => __('Support'), 'route' => 'user.support-tickets.index', 'active' => 'user.support-tickets.*', 'icon' => 'ph-question', 'permission' => 'workspace.view'],
            ],
        ],
    ];

    // Filter out groups with no visible items (due to disabled routes or missing permissions)
    $filteredGroups = [];
    foreach ($groups as $group) {
        $visibleItems = [];
        foreach ($group['items'] as $item) {
            if (Route::has($item['route']) && $canSeeSidebarItem($item['permission'] ?? null)) {
                $visibleItems[] = $item;
            }
        }
        if (! empty($visibleItems)) {
            $group['items'] = $visibleItems;
            $filteredGroups[] = $group;
        }
    }
@endphp

<div id="appSidebarBackdrop" class="app-backdrop lg:hidden"></div>

<aside id="appSidebar" class="app-sidebar" aria-label="{{ __('User navigation') }}">
    <div class="app-sidebar__brand" aria-label="{{ $siteName }}">
        <a href="{{ route('user.dashboard') }}" class="flex min-w-0 items-center gap-2.5">
            @if (setting('site_logo') && media_url(setting('site_logo')))
                <img src="{{ media_url(setting('site_logo')) }}" alt="{{ $siteName }}"
                    class="h-9 w-9 shrink-0 rounded-xl object-cover">
            @else
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary text-neutral-0 shadow-[0_6px_16px_-6px_rgba(31,170,83,0.7)]">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" />
                    </svg>
                </span>
            @endif
            <span class="truncate font-title text-lg font-extrabold tracking-tight text-title">
                {{ $siteName }}
            </span>
        </a>

        <button type="button" id="appSidebarClose"
            class="ms-auto grid h-8 w-8 place-items-center rounded-lg text-body hover:bg-neutral-100 hover:text-title lg:hidden"
            aria-label="{{ __('Close sidebar') }}">
            <i class="ph ph-x text-lg"></i>
        </button>
    </div>

    <nav class="app-nav scrollbar-hide flex-1 overflow-y-auto px-3 py-4" aria-label="{{ __('Sidebar navigation') }}">
        @foreach ($filteredGroups as $group)
            <p class="app-nav__group">{{ $group['label'] }}</p>
            <div class="space-y-0.5">
                @foreach ($group['items'] as $item)
                    <a href="{{ route($item['route']) }}"
                        class="app-nav__link {{ request()->routeIs(...(array) $item['active']) ? 'is-active' : '' }}"
                        aria-label="{{ $item['label'] }}">
                        <i class="ph {{ $item['icon'] }} text-xl"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach

        @if (Route::has('user.system-notifications.index'))
            <a href="{{ route('user.system-notifications.index') }}"
                class="app-nav__link {{ request()->routeIs('user.system-notifications.*') ? 'is-active' : '' }}"
                aria-label="{{ __('Notifications') }}">
                <span class="relative inline-grid place-items-center">
                    <i class="ph ph-bell text-xl"></i>
                    @if (($sidebarUnreadCount ?? 0) > 0)
                        <span class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-error"></span>
                    @endif
                </span>
                <span>{{ __('Notifications') }}</span>
            </a>
        @endif
    </nav>

    <div class="app-sidebar__user">
        <div class="flex items-center gap-3 rounded-xl bg-section p-2.5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-deep text-sm font-bold text-neutral-0">
                {{ strtoupper(substr($authUser->name ?? 'U', 0, 2)) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-title">{{ $authUser->name ?? __('User') }}</p>
                <p class="truncate text-xs text-body">{{ $authUser->email ?? '' }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-body transition-colors hover:bg-neutral-100 hover:text-error"
                    aria-label="{{ __('Sign out') }}">
                    <i class="ph ph-sign-out text-lg"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
