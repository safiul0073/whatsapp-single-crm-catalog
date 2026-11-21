                @if($canViewCustomer)
                <section class="section-card space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5 text-title">{{ __('Customer') }}</h2>
                        @can('commerce.orders.customer.edit')
                        <button type="button" class="btn btn-outline btn-sm rounded-sm text-xs py-1 px-2.5" data-modal-open="editCustomerModal">
                            <i class="ph ph-pencil-simple"></i> {{ __('Edit Contact') }}
                        </button>
                        @endcan
                    </div>
                    <div class="space-y-1">
                        <p class="font-medium text-title">{{ $customerName ?: '—' }}</p>
                        @if($customerPhone)<p class="text-body flex items-center gap-1.5"><i class="ph ph-phone text-muted"></i>{{ $customerPhone }}</p>@endif
                        @if($customerEmail)<p class="text-body flex items-center gap-1.5"><i class="ph ph-envelope-simple text-muted"></i>{{ $customerEmail }}</p>@endif
                    </div>
                    <div class="border-t border-border pt-2 space-y-0.5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">{{ __('Contact Address') }}</p>
                        @if(!empty($contactAddr) && (filled($contactAddr['line1'] ?? null) || filled($contactAddr['city'] ?? null)))
                            @if(filled($contactAddr['line1'] ?? null))<p class="text-body">{{ $contactAddr['line1'] }}@if(filled($contactAddr['line2'] ?? null)), {{ $contactAddr['line2'] }}@endif</p>@endif
                            @php
                                $cityStateZip = collect([$contactAddr['city'] ?? null, $contactAddr['state'] ?? null, $contactAddr['postal_code'] ?? null])->filter()->implode(', ');
                            @endphp
                            @if(filled($cityStateZip))<p class="text-body">{{ $cityStateZip }}</p>@endif
                            @if(filled($contactAddr['country'] ?? null))<p class="text-body">{{ $contactAddr['country'] }}</p>@endif
                        @else
                            <p class="text-xs text-muted">{{ __('No contact address saved.') }}</p>
                        @endif
                    </div>
                    @if(($canMessageCustomers ?? false) && !empty($orderMessagePayload))
                        <div class="border-t border-border pt-3 space-y-2">
                            <button
                                type="button"
                                class="btn btn-outline btn-sm w-full justify-center"
                                data-order-message-trigger="{{ $order->id }}"
                                data-has-channels="{{ !empty($orderMessagePayload['channels']) ? '1' : '0' }}"
                                x-on:click="$dispatch('open-order-message', {{ Js::from($orderMessagePayload) }})"
                            >
                                <i class="ph ph-paper-plane-tilt"></i> {{ __('Message customer') }}
                            </button>
                            @if(!empty($orderMessagePayload['channels']))
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($orderMessagePayload['channels'] as $availableChannel)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-700">
                                            <i class="ph {{ $availableChannel['icon'] }} text-xs text-primary"></i>
                                            <span>{{ $availableChannel['provider_label'] }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-muted">{{ __('No active channels available yet. Click above to connect one.') }}</p>
                            @endif
                        </div>
                    @endif
                    @if($order->conversation)
                        <div class="pt-1">
                            <a href="{{ route('user.inbox.index', ['conversation' => $order->conversation->id]) }}" class="text-primary hover:underline inline-flex items-center gap-1"><i class="ph ph-chat-circle"></i> {{ __('Open conversation') }}</a>
                        </div>
                    @endif
                </section>
                @endif
