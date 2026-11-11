@if($subscriptionBanner)
    <div @class([
        'mb-5 flex flex-wrap items-center justify-between gap-3 rounded-md border px-4 py-3 text-sm font-medium',
        'border-error/20 bg-error/10 text-error' => $subscriptionBanner['tone'] === 'error',
        'border-warning/20 bg-warning/10 text-title' => $subscriptionBanner['tone'] === 'warning',
    ])>
        <span class="flex items-center gap-2">
            <i class="ph {{ $subscriptionBanner['tone'] === 'error' ? 'ph-lock-simple' : 'ph-clock-countdown' }} text-base"></i>
            {{ $subscriptionBanner['message'] }}
        </span>
        @if($subscriptionBanner['can_manage'])
            <a href="{{ route('user.subscription.show') }}" class="btn-sm btn-primary">{{ __('Renew plan') }}</a>
        @endif
    </div>
@endif
