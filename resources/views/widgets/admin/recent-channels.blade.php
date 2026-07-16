<div class="section-card">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="heading-5 text-neutral-950">{{ __('Recent Channels') }}</h2>
        @if(Route::has('admin.marketing-channels.index'))
            <a href="{{ route('admin.marketing-channels.index') }}" class="text-sm text-primary hover:underline">{{ __('View All') }}</a>
        @endif
    </div>
    @if($recentChannels->isNotEmpty())
        <div class="space-y-2">
            @foreach($recentChannels as $channel)
                <div class="flex items-center gap-3 rounded-md border border-neutral-100 bg-neutral-0 p-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-broadcast"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-neutral-900">
                            {{ $channel->name }}
                            <span class="text-neutral-400">/ {{ ucfirst($channel->provider) }}</span>
                        </p>
                        <p class="truncate text-xs text-neutral-400">
                            {{ $channel->workspace?->name ?? __('No workspace') }} &middot; {{ $channel->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <div class="shrink-0">
                        @switch($channel->status?->value ?? $channel->status)
                            @case('connected') <x-ui.badge variant="success">{{ __('Connected') }}</x-ui.badge> @break
                            @case('disconnected') <x-ui.badge variant="warning">{{ __('Disconnected') }}</x-ui.badge> @break
                            @case('error') <x-ui.badge variant="danger">{{ __('Error') }}</x-ui.badge> @break
                            @case('suspended') <x-ui.badge variant="warning">{{ __('Suspended') }}</x-ui.badge> @break
                            @default <x-ui.badge variant="neutral">{{ __(str($channel->status?->value ?? $channel->status)->headline()->toString()) }}</x-ui.badge>
                        @endswitch
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-neutral-400">{{ __('No channels found.') }}</p>
    @endif
</div>
