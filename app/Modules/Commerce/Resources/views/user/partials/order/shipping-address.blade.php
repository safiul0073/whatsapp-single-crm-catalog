                @if($order->source !== 'pos' || $order->fulfillment_type === 'delivery')
                <section class="section-card space-y-1 text-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5 text-title">{{ __('Shipping address') }}</h2>
                        @if($isAddressLocked)<span class="text-xs text-body"><i class="ph ph-lock-simple"></i> {{ __('From customer') }}</span>@endif
                    </div>
                    @if($address)
                        @if($canViewCustomer)
                            <p class="text-title">{{ $address['name'] ?? '' }}</p>
                            <p class="text-body">{{ $address['phone'] ?? '' }}</p>
                        @endif
                        <p class="text-body">{{ $address['line1'] ?? '' }}@if(filled($address['line2'] ?? null)), {{ $address['line2'] }}@endif</p>
                        <p class="text-body">{{ collect([$address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null])->filter()->implode(', ') }}</p>
                        <p class="text-body">{{ $address['country'] ?? '' }}</p>
                    @else
                        <p class="text-body">{{ __('No address yet.') }}</p>
                    @endif
                    @if($order->delivery_method)<p class="pt-2 text-body">{{ __('Delivery') }}: {{ $order->delivery_method }}</p>@endif
                    @if(filled($order->delivery_notes))<p class="whitespace-pre-line pt-2 text-body">{{ __('Delivery notes') }}: {{ $order->delivery_notes }}</p>@endif
                    @if(filled($order->duties_disclosure))<p class="whitespace-pre-line pt-2 text-body">{{ __('Duties disclosure') }}: {{ $order->duties_disclosure }}</p>@endif
                </section>
                @endif
