@php
    $tabs = [
        'overview' => ['route' => 'user.commerce.visitors.index', 'icon' => 'ph-chart-line-up', 'label' => __('Overview')],
        'live' => ['route' => 'user.commerce.visitors.live-page', 'icon' => 'ph-broadcast', 'label' => __('Live')],
        'list' => ['route' => 'user.commerce.visitors.list', 'icon' => 'ph-users-three', 'label' => __('All visitors')],
    ];
    $ranges = ['today' => __('Today'), '7d' => __('7 days'), '30d' => __('30 days'), '90d' => __('90 days')];
    $showRange = $tab !== 'live';
@endphp
<header class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ __('Commerce Management') }}</p>
            <h1 class="heading-3 font-bold text-title">{{ __('Visitors') }}</h1>
            <p class="mt-1 text-sm text-body">{{ __('Where your storefront visitors come from, what they view and who is online now.') }}</p>
        </div>
        <nav class="visitor-tabs" aria-label="{{ __('Visitor views') }}">
            @foreach($tabs as $key => $item)
                <a href="{{ route($item['route'], $showRange && $key !== 'live' ? request()->only(['range', 'from', 'to']) : []) }}" class="visitor-tab {{ $tab === $key ? 'visitor-tab-active' : '' }}" @if($tab === $key) aria-current="page" @endif>
                    <i class="ph {{ $item['icon'] }}"></i> {{ $item['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
    @if($showRange)
        <div class="flex flex-wrap items-center gap-2">
            <div class="visitor-range" role="group" aria-label="{{ __('Date range') }}">
                @foreach($ranges as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['range' => $key, 'from' => null, 'to' => null, 'date' => null, 'page' => null]) }}" class="visitor-range-option {{ $range === $key ? 'visitor-range-active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" class="flex flex-wrap items-center gap-1.5">
                @foreach(request()->only(['country', 'city', 'device', 'os', 'customers']) as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <input type="hidden" name="range" value="custom">
                <input type="hidden" name="from" value="{{ $from->toDateString() }}" data-range-from>
                <input type="hidden" name="to" value="{{ $to->toDateString() }}" data-range-to>
                <label class="visitor-range-field {{ $range === 'custom' ? 'visitor-range-field-active' : '' }}">
                    <i class="ph ph-calendar-blank text-base"></i>
                    <input type="text" readonly class="visitor-range-input" data-visitor-range-picker data-max="{{ today()->toDateString() }}" value="{{ $from->format('M j, Y') }}@if(! $from->isSameDay($to)) – {{ $to->format('M j, Y') }}@endif" aria-label="{{ __('Custom date range') }}">
                    <i class="ph ph-caret-down text-xs"></i>
                </label>
            </form>
        </div>
    @endif
</header>
