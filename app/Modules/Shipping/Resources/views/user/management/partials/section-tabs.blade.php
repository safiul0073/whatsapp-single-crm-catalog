@php
    $sections = [
        'rates' => [
            'label' => __('Rates and quotes'),
            'tabs' => [
                ['route' => 'user.shipping.calculator', 'active' => 'user.shipping.calculator*', 'icon' => 'ph-calculator', 'label' => __('Rate calculator'), 'permission' => 'shipping.shipments.view'],
                ['route' => 'user.shipping.comparison', 'active' => 'user.shipping.comparison', 'icon' => 'ph-scales', 'label' => __('Rate comparison'), 'permission' => 'shipping.costs.view'],
                ['route' => 'user.shipping.remote-areas.index', 'active' => 'user.shipping.remote-areas.*', 'icon' => 'ph-map-pin-line', 'label' => __('Remote areas'), 'permission' => 'shipping.shipments.view'],
            ],
        ],
        'billing' => [
            'label' => __('Bills and payments'),
            'tabs' => [
                ['route' => 'user.shipping.invoices.index', 'active' => 'user.shipping.invoices.*', 'icon' => 'ph-file-text', 'label' => __('Courier invoices'), 'permission' => 'shipping.billing.view'],
                ['route' => 'user.shipping.payments.index', 'active' => 'user.shipping.payments.*', 'icon' => 'ph-wallet', 'label' => __('Agent payments'), 'permission' => 'shipping.billing.view'],
                ['route' => 'user.shipping.billing.index', 'active' => 'user.shipping.billing.*', 'icon' => 'ph-receipt', 'label' => __('Cost per shipment'), 'permission' => 'shipping.billing.view'],
            ],
        ],
        'partners' => [
            'label' => __('Couriers and agents'),
            'tabs' => [
                ['route' => 'user.shipping.agents.index', 'active' => 'user.shipping.agents.*', 'icon' => 'ph-handshake', 'label' => __('Shipping agents'), 'permission' => 'shipping.agents.view'],
                ['route' => 'user.shipping.carriers.index', 'active' => 'user.shipping.carriers.*', 'icon' => 'ph-plugs-connected', 'label' => __('Courier accounts'), 'permission' => 'shipping.carriers.manage'],
            ],
        ],
        'storefront' => [
            'label' => __('Storefront shipping'),
            'tabs' => [
                ['route' => 'user.shipping.index', 'active' => ['user.shipping.index', 'user.shipping.zones.*', 'user.shipping.rates.*'], 'icon' => 'ph-globe-hemisphere-west', 'label' => __('Zones and rates'), 'permission' => 'shipping.manage'],
                ['route' => 'user.shipping.methods.index', 'active' => 'user.shipping.methods.*', 'icon' => 'ph-truck', 'label' => __('Methods'), 'permission' => 'shipping.manage'],
                ['route' => 'user.shipping.settings', 'active' => 'user.shipping.settings*', 'icon' => 'ph-gear', 'label' => __('Settings'), 'permission' => 'shipping.manage'],
            ],
        ],
    ];
    $current = $sections[$section];
    $visibleTabs = array_values(array_filter($current['tabs'], fn (array $tab): bool => auth()->user()?->can($tab['permission']) ?? false));
@endphp
@if(count($visibleTabs) > 1)
    <nav class="shipping-tabs mb-6" aria-label="{{ $current['label'] }}">
        @foreach($visibleTabs as $tab)
            @php $isActive = request()->routeIs(...(array) $tab['active']); @endphp
            <a href="{{ route($tab['route']) }}" @class(['shipping-tab', 'shipping-tab-active' => $isActive]) @if($isActive) aria-current="page" @endif>
                <i class="ph {{ $tab['icon'] }}"></i> {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
@endif
