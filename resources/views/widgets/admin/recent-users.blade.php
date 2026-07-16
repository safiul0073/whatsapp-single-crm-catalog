<div class="section-card">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="heading-5 text-neutral-950">{{ __('Recent Users') }}</h2>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-primary hover:underline">{{ __('View All') }}</a>
    </div>
    @if($recentUsers->isNotEmpty())
        <div class="space-y-2">
            @foreach($recentUsers as $user)
            <div class="flex items-center gap-3 rounded-md border border-neutral-100 bg-neutral-0 p-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <i class="ph ph-user"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-neutral-900 truncate">{{ $user->name }}</p>
                    <p class="text-xs text-neutral-400">{{ $user->email }}</p>
                </div>
                <div class="shrink-0">
                    @foreach($user->roles as $role)
                        <x-ui.badge variant="primary">{{ $role->name }}</x-ui.badge>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-neutral-400">{{ __('No users found.') }}</p>
    @endif
</div>
