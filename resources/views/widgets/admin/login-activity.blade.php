<div class="section-card">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="heading-5 text-neutral-950">{{ __('Login Activity') }}</h2>
        @if(Route::has('admin.login-activity.index'))
            <a href="{{ route('admin.login-activity.index') }}" class="text-sm text-primary hover:underline">{{ __('View All') }}</a>
        @endif
    </div>
    @if($loginActivities->isNotEmpty())
        <div class="space-y-2">
            @foreach($loginActivities as $activity)
            <div class="flex items-center gap-3 rounded-md border border-neutral-100 bg-neutral-0 p-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg
                    @switch($activity->event)
                        @case('login') bg-success/10 text-success @break
                        @case('logout') bg-info/10 text-info @break
                        @case('failed') bg-error/10 text-error @break
                        @case('lockout') bg-warning/10 text-warning @break
                        @default bg-neutral-100 text-neutral-500
                    @endswitch
                ">
                    <i class="ph
                        @switch($activity->event)
                            @case('login') ph-sign-in @break
                            @case('logout') ph-sign-out @break
                            @case('failed') ph-x-circle @break
                            @case('lockout') ph-lock @break
                            @default ph-activity
                        @endswitch
                    "></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-neutral-900 truncate">
                        {{ ucfirst($activity->event) }}
                        @if($activity->user)
                            — {{ $activity->user->name ?? $activity->user->email ?? __('Unknown') }}
                        @endif
                    </p>
                    <p class="text-xs text-neutral-400">
                        {{ $activity->ip_address }} &middot; {{ $activity->browser }} / {{ $activity->platform }}
                        &middot; {{ $activity->created_at->diffForHumans() }}
                    </p>
                </div>
                <div class="shrink-0">
                    <x-ui.badge :variant="$activity->getEventBadgeVariant()">{{ ucfirst($activity->event) }}</x-ui.badge>
                </div>
            </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-neutral-400">{{ __('No login activity recorded.') }}</p>
    @endif
</div>
