@props(['channel' => null, 'status' => null])

@php
    $status ??= app(\App\Modules\MarketingChannels\Services\ChannelHubService::class)->statusFor($channel);
@endphp

<span {{ $attributes->merge(['class' => 'badge badge-'.$status['variant'].' inline-flex items-center gap-1.5']) }} data-channel-status="{{ $status['value'] }}">
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ __($status['label']) }}
</span>
