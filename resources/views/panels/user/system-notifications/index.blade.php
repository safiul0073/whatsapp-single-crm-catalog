<x-layouts.user :title="__('Notifications')">

    <div class="bg-white rounded-2xl border border-border-soft shadow-xs overflow-hidden">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-border-soft">
            <div>
                <h2 class="font-display font-bold text-text-strong text-[15px]">{{ __('Notifications') }}</h2>
                <p class="font-body text-[12px] text-text-muted mt-0.5">{{ __('Recent updates and alerts') }}</p>
            </div>
            @if($notifications->total() > 0)
                <form method="POST" action="{{ route('user.system-notifications.mark-all-read') }}">
                    @csrf
                    <button type="submit" class="font-body text-body-sm font-semibold text-brand-blue hover:underline">
                        {{ __('Mark all read') }}
                    </button>
                </form>
            @endif
        </div>

        {{-- Filter Tabs --}}
        <div class="flex items-center gap-0 border-b border-border-soft px-4 overflow-x-auto">
            <a href="{{ route('user.system-notifications.index') }}"
               class="portal-tab-btn {{ !request('status') ? 'active' : '' }} px-4 py-3.5 font-body text-[13px] whitespace-nowrap">
                {{ __('All') }}
                <span class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-tint-blue text-brand-blue text-[9px] font-bold">
                    {{ $notifications->total() }}
                </span>
            </a>
            <a href="{{ route('user.system-notifications.index', ['status' => 'unread']) }}"
               class="portal-tab-btn {{ request('status') === 'unread' ? 'active' : '' }} px-4 py-3.5 font-body text-[13px] whitespace-nowrap">
                {{ __('Unread') }}
            </a>
            <a href="{{ route('user.system-notifications.index', ['status' => 'read']) }}"
               class="portal-tab-btn {{ request('status') === 'read' ? 'active' : '' }} px-4 py-3.5 font-body text-[13px] whitespace-nowrap">
                {{ __('Read') }}
            </a>
        </div>

        {{-- Notifications List --}}
        <div class="divide-y divide-border-soft">
            @forelse($notifications as $notification)
                <a href="{{ $notification->getUrl() ?? '#' }}"
                   class="flex items-start gap-4 px-6 py-4 transition-colors hover:bg-bg-soft {{ !$notification->isRead() ? 'bg-tint-blue/30' : '' }}">
                    <div class="w-9 h-9 rounded-full inline-grid place-items-center flex-none mt-0.5 {{ match($notification->getType()) {
                        'success' => 'bg-tint-green text-brand-green',
                        'warning' => 'bg-warning-soft text-[#92400e]',
                        'danger'  => 'bg-danger-soft text-error',
                        default   => 'bg-tint-blue text-brand-blue',
                    } }}">
                        <i class="ph ph-{{ match($notification->getType()) {
                            'success' => 'check-circle',
                            'warning' => 'alert-triangle',
                            'danger'  => 'x-circle',
                            default   => 'bell',
                        } }} w-4 h-4"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-body text-[13px] text-text-strong {{ !$notification->isRead() ? 'font-semibold' : '' }}">
                                {{ $notification->getTitle() }}
                            </p>
                            <div class="flex shrink-0 items-center gap-2">
                                @if(!$notification->isRead())
                                    <span class="w-2 h-2 rounded-full bg-brand-blue flex-none"></span>
                                @endif
                                <span class="font-mono text-[11px] text-text-muted whitespace-nowrap">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <p class="font-body text-[12px] text-text-muted mt-0.5">{{ $notification->getBody() }}</p>
                    </div>
                </a>
            @empty
                <div class="py-16 text-center">
                    <div class="mx-auto mb-4 w-14 h-14 rounded-full bg-bg-soft inline-grid place-items-center">
                        <i class="ph ph-bell-slash w-6 h-6 text-text-light"></i>
                    </div>
                    <p class="font-body text-body-sm text-text-muted">{{ __('No notifications found.') }}</p>
                </div>
            @endforelse
        </div>

        <x-tables.pagination :paginator="$notifications" />
    </div>

</x-layouts.user>
