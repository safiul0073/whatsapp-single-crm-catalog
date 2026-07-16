@props(['title' => 'Dashboard'])

@php
    $authUser = auth()->user();

    $bellConfig = [
        'initialUnreadCount' => $topbarUnreadCount ?? 0,
        'unreadCountUrl' => route('user.system-notifications.unread-count'),
        'recentUrl' => route('user.system-notifications.recent'),
        'markReadUrl' => route('user.system-notifications.mark-read', ['notification' => '__ID__']),
        'markAllReadUrl' => route('user.system-notifications.mark-all-read'),
        'viewAllUrl' => route('user.system-notifications.index'),
    ];
@endphp

<header class="app-topbar">
    <div class="flex min-w-0 flex-1 items-center gap-3">
        <button type="button" id="appSidebarOpen"
            class="app-topbar__sidebar-toggle lg:hidden"
            aria-label="{{ __('Toggle sidebar') }}">
            <i class="ph ph-list text-xl"></i>
        </button>

        <button type="button" class="app-topbar__search" data-modal-trigger="globalSearchModal"
            aria-label="{{ __('Search') }}">
            <i class="ph ph-magnifying-glass shrink-0 text-base"></i>
            <span class="w-full text-left">{{ __('Search contacts, campaigns, chats...') }}</span>
        </button>

        <button type="button" class="app-topbar__icon sm:hidden" data-modal-trigger="globalSearchModal"
            aria-label="{{ __('Search') }}">
            <i class="ph ph-magnifying-glass text-xl"></i>
        </button>
    </div>

    <div class="flex items-center gap-1.5 sm:gap-2.5">
        <button type="button" class="app-topbar__icon" data-action="toggle-theme"
            aria-label="{{ __('Toggle theme') }}">
            <svg id="sunIcon" class="hidden h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="2"
                viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="5" />
                <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
            </svg>
            <svg id="moonIcon" class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="2"
                viewBox="0 0 24 24">
                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
            </svg>
        </button>

        <x-ui.language-switcher />

        <div class="relative" x-data="notificationBell({{ Js::from($bellConfig) }})">
            <button @click="togglePanel()" class="app-topbar__icon" aria-label="{{ __('Notifications') }}">
                <i class="ph ph-bell text-xl"></i>
                <span x-show="unreadCount > 0" x-cloak x-text="unreadCount > 99 ? '99+' : unreadCount"
                    class="absolute -end-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-white"></span>
            </button>

            <div x-show="isOpen" x-cloak @click.outside="isOpen = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-1"
                class="absolute end-0 top-full z-50 mt-2 w-80 overflow-hidden rounded-xl border border-neutral-100 bg-neutral-0 shadow-xl md:w-96">
                <div class="flex items-center justify-between border-b border-neutral-100 p-4">
                    <h4 class="font-bold text-neutral-950">{{ __('Notifications') }}</h4>
                    <button @click="markAllRead()" x-show="unreadCount > 0" class="text-xs text-primary hover:underline">
                        {{ __('Mark all read') }}
                    </button>
                </div>
                <div class="max-h-80 overflow-y-auto scrollbar-hide">
                    <div x-show="loading" class="flex items-center justify-center p-8">
                        <div class="datatable-spinner"></div>
                    </div>
                    <template x-if="!loading">
                        <div>
                            <template x-for="n in notifications" :key="n.id">
                                <a :href="n.url || 'javascript:void(0)'" @click="handleNotificationClick(n, $event)"
                                    class="flex cursor-pointer gap-3 border-b border-neutral-50 p-4 transition-colors hover:bg-neutral-50"
                                    :class="{ 'bg-primary/5': !n.read_at }">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                        :class="n.icon_bg || 'bg-primary/10 text-primary'">
                                        <i class="ph" :class="n.icon || 'ph-bell'"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-neutral-900" x-text="n.title"></p>
                                        <p class="truncate text-xs text-neutral-500" x-text="n.body"></p>
                                        <p class="mt-1 text-xs text-neutral-400" x-text="n.time_ago"></p>
                                    </div>
                                    <div x-show="!n.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary"></div>
                                </a>
                            </template>
                            <div x-show="notifications.length === 0" class="p-8 text-center text-sm text-neutral-400">
                                {{ __('No notifications') }}
                            </div>
                        </div>
                    </template>
                </div>
                <a :href="viewAllUrl"
                    class="block border-t border-neutral-100 py-3 text-center text-sm text-primary transition-colors hover:bg-neutral-50">
                    {{ __('View All Notifications') }}
                </a>
            </div>
        </div>

        <div class="dropdown-wrapper relative ms-1">
            <button type="button" class="flex items-center gap-1.5 rounded-full p-1 transition-colors hover:bg-section"
                data-action="toggle-dropdown" data-target="userDropdown" aria-haspopup="true">
                @if ($authUser->avatar && avatar_url($authUser->avatar))
                    <img src="{{ avatar_url($authUser->avatar) }}" alt="{{ $authUser->name }}"
                        class="h-8 w-8 rounded-full object-cover ring-2 ring-primary/10">
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-deep text-xs font-bold text-neutral-0">
                        {{ strtoupper(substr($authUser->name ?? 'U', 0, 2)) }}
                    </span>
                @endif
                <i class="ph ph-caret-down hidden text-xs text-neutral-400 lg:block"></i>
            </button>

            <div id="userDropdown"
                class="dropdown-panel absolute end-0 top-full z-50 mt-2 w-56 rounded-xl border border-neutral-200 bg-neutral-0 p-1.5 shadow-[0_20px_50px_-20px_rgba(10,27,20,0.35)]"
                role="menu">
                <div class="flex items-center gap-3 px-2.5 py-2">
                    @if ($authUser->avatar && avatar_url($authUser->avatar))
                        <img src="{{ avatar_url($authUser->avatar) }}" alt="{{ $authUser->name }}"
                            class="h-9 w-9 shrink-0 rounded-full object-cover">
                    @else
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-deep text-sm font-bold text-neutral-0">
                            {{ strtoupper(substr($authUser->name ?? 'U', 0, 2)) }}
                        </span>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-title">{{ $authUser->name ?? __('User') }}</p>
                        <p class="truncate text-xs text-body">{{ $authUser->email ?? '' }}</p>
                    </div>
                </div>
                <div class="my-1 border-t border-neutral-100"></div>
                <div>
                    <a href="{{ route('user.profile.edit') }}"
                        class="dropdown-item"
                        role="menuitem">
                        <i class="ph ph-user-circle text-base"></i> {{ __('My profile') }}
                    </a>
                </div>
                <div class="my-1 border-t border-neutral-100"></div>
                <div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="dropdown-item text-error hover:bg-error/10 hover:text-error"
                            role="menuitem">
                            <i class="ph ph-sign-out text-base"></i> {{ __('Sign out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
