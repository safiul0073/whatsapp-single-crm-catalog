<section class="section-card space-y-3 text-sm">
    <h2 class="heading-5 text-title">{{ __('Activity') }}</h2>
    <dl class="grid gap-2 sm:grid-cols-2">
        <div><dt class="text-body">{{ __('Tracking code') }}</dt><dd class="font-medium text-title">{{ $order->tracking_code ?? '—' }}</dd></div>
        @if($order->tracking_number)<div><dt class="text-body">{{ __('Tracking number') }}</dt><dd class="font-medium text-title">{{ $order->tracking_number }}</dd></div>@endif
        @if($order->tracking_url)<div class="sm:col-span-2"><dt class="text-body">{{ __('Carrier URL') }}</dt><dd class="break-all text-title">{{ $order->tracking_url }}</dd></div>@endif
    </dl>
    <ol class="order-timeline">
        @foreach($order->trackingTimeline() as $step)
            <li class="order-timeline-item {{ ($step['state'] ?? '') === 'complete' ? 'order-timeline-done' : '' }}">
                <span class="font-medium text-title">{{ $step['label'] }}</span>
                <span class="text-xs text-body">{{ ucfirst($step['state'] ?? '') }}@if(! empty($step['date'])) · {{ \Illuminate\Support\Carbon::parse($step['date'])->format('M j, Y · g:i A') }}@endif</span>
            </li>
        @endforeach
    </ol>
</section>
