<div class="section-card">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="heading-5 text-neutral-950">{{ __('Recent Payments') }}</h2>
        @if(Route::has('admin.payments.index'))
            <a href="{{ route('admin.payments.index') }}" class="text-sm text-primary hover:underline">{{ __('View All') }}</a>
        @endif
    </div>
    @if($recentPayments->isNotEmpty())
        <div class="space-y-2">
            @foreach($recentPayments as $payment)
                <div class="flex items-center gap-3 rounded-md border border-neutral-100 bg-neutral-0 p-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-success/10 text-success">
                        <i class="ph ph-credit-card"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-neutral-900">
                            {{ $payment->formatted_amount }}
                            <span class="text-neutral-400">/ {{ ucfirst($payment->gateway) }}</span>
                        </p>
                        <p class="truncate text-xs text-neutral-400">
                            {{ $payment->user?->name ?? $payment->user?->email ?? __('Guest') }} &middot; {{ $payment->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <div class="shrink-0">
                        @switch($payment->status)
                            @case('completed') <x-ui.badge variant="success">{{ __('Completed') }}</x-ui.badge> @break
                            @case('pending') <x-ui.badge variant="warning">{{ __('Pending') }}</x-ui.badge> @break
                            @case('processing') <x-ui.badge variant="neutral">{{ __('Processing') }}</x-ui.badge> @break
                            @case('failed') <x-ui.badge variant="danger">{{ __('Failed') }}</x-ui.badge> @break
                            @default <x-ui.badge variant="neutral">{{ ucfirst($payment->status) }}</x-ui.badge>
                        @endswitch
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-sm text-neutral-400">{{ __('No payments found.') }}</p>
    @endif
</div>
