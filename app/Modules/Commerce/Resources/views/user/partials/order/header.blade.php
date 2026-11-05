<header class="section-card relative z-10 space-y-2 !overflow-visible">
    <a href="{{ route('user.commerce.orders.index') }}" class="inline-flex items-center gap-1 text-sm text-body hover:text-title"><i class="ph ph-arrow-left"></i> {{ __('Orders') }}</a>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 space-y-1">
            <p class="text-sm font-semibold text-primary">{{ $order->number }}</p>
            <h1 class="heading-3 text-title">{{ $canViewCustomer ? ($order->contact?->name ?? ($order->customer_snapshot['name'] ?? $address['name'] ?? __('Customer'))) : __('Order') }}</h1>
            <p class="text-sm text-body">{{ __('Placed') }} {{ $order->created_at?->format('M j, Y · g:i A') }} · {{ __('Source') }}: {{ str($order->source ?? 'manual')->replace('_', ' ')->title() }}@if($order->fulfillment_type) · {{ str($order->fulfillment_type)->title() }}@endif</p>
            <div class="flex flex-wrap items-center gap-2 pt-1">
                <span class="badge badge-soft">{{ str($order->status)->replace('_', ' ')->title() }}</span>
                @if($canViewPayments)<span class="badge badge-soft">{{ __('Payment') }}: {{ str($order->payment_state)->replace('_', ' ')->title() }}</span>@endif
            </div>
        </div>
        <div class="flex flex-wrap items-stretch gap-2">
            @if($primaryAction)
                <a href="{{ $primaryAction['href'] }}" class="btn btn-primary btn-sm"><i class="ph {{ $primaryAction['icon'] }}"></i> {{ $primaryAction['label'] }}</a>
            @endif
            <div class="relative flex" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" class="btn btn-outline btn-sm h-full" @click="open = !open" :aria-expanded="open" aria-haspopup="menu"><i class="ph ph-files"></i> {{ __('Documents') }} <i class="ph ph-caret-down text-xs"></i></button>
                <div x-show="open" x-cloak class="order-menu" role="menu">
                    <a class="order-menu-item" role="menuitem" href="{{ route('user.commerce.orders.invoice.print', $order) }}" target="_blank" rel="noopener"><i class="ph ph-printer"></i> {{ __('Print invoice') }}</a>
                    <a class="order-menu-item" role="menuitem" href="{{ route('user.commerce.orders.invoice.print', ['order' => $order, 'format' => 'receipt']) }}" target="_blank" rel="noopener"><i class="ph ph-receipt"></i> {{ __('Print receipt (80mm)') }}</a>
                    <a class="order-menu-item" role="menuitem" href="{{ route('user.commerce.orders.packing-slip', $order) }}" target="_blank" rel="noopener"><i class="ph ph-package"></i> {{ __('Print packing slip') }}</a>
                    <a class="order-menu-item" role="menuitem" href="{{ route('user.commerce.orders.invoice', ['order' => $order, 'download' => 1]) }}"><i class="ph ph-download-simple"></i> {{ __('Download invoice (PDF)') }}</a>
                    <button type="button" class="order-menu-item" role="menuitem" data-copy-invoice-link="{{ $order->invoiceUrl() }}" data-copied-label="{{ __('Link copied') }}"><i class="ph ph-link"></i><span>{{ __('Copy invoice link') }}</span></button>
                    @can('commerce.orders.update')
                        <form method="POST" action="{{ route('user.commerce.orders.invoice.send', $order) }}">
                            @csrf
                            <button type="submit" class="order-menu-item" role="menuitem"><i class="ph ph-whatsapp-logo"></i> {{ $order->invoice_sent_at ? __('Resend invoice on WhatsApp') : __('Send invoice on WhatsApp') }}</button>
                        </form>
                    @endcan
                    @if($order->invoice_sent_at)<p class="px-3 py-2 text-xs text-body">{{ __('Invoice sent on WhatsApp') }} {{ $order->invoice_sent_at->format('M j, Y · g:i A') }}</p>@endif
                </div>
            </div>
            @if($canCancel || $canDeleteOrders)
                <div class="relative flex" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="btn btn-outline btn-sm h-full" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" aria-label="{{ __('More actions') }}"><i class="ph ph-dots-three-vertical text-base"></i></button>
                    <div x-show="open" x-cloak class="order-menu" role="menu">
                        @if($canCancel)
                        <form method="POST" action="{{ route('user.commerce.orders.transition', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="order-menu-item text-error" role="menuitem" data-confirm data-confirm-title="{{ __('Cancel this order?') }}" data-confirm-message="{{ __('Stock is released and the customer is not charged. This cannot be undone.') }}" data-confirm-button="{{ __('Cancel order') }}" data-confirm-variant="danger"><i class="ph ph-x-circle"></i> {{ __('Cancel order') }}</button>
                        </form>
                        @endif
                        @if($canDeleteOrders)
                            <form method="POST" action="{{ route('user.commerce.orders.destroy', $order) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="order-menu-item text-error" role="menuitem" data-confirm data-confirm-title="{{ __('Delete this order?') }}" data-confirm-message="{{ $order->payments->isNotEmpty() || in_array($order->status, ['shipped', 'completed', 'delivered'], true) ? __('This order has payments or has already shipped. It disappears from your lists and reports. Stock it holds is released.') : __('The order disappears from your lists and reports. Stock it holds is released.') }}" data-confirm-button="{{ __('Delete order') }}" data-confirm-variant="danger"><i class="ph ph-trash"></i> {{ __('Delete order') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    @if($order->status === 'cancelled')
        <p role="status" class="rounded-md border border-danger/30 bg-danger/10 p-3 text-sm text-title">{{ __('This order was cancelled.') }}</p>
    @elseif($currentStepIndex !== false)
        <ol class="order-stepper grid grid-cols-2 {{ $order->source === 'pos' && $order->fulfillment_type === 'pickup' ? '' : 'sm:grid-cols-4 lg:grid-cols-8' }}">
            @foreach($progressSteps as $stepIndex => $step)
                <li class="order-step {{ $stepIndex < $currentStepIndex ? 'order-step-done' : ($stepIndex === $currentStepIndex ? 'order-step-current' : '') }}">
                    <i class="ph {{ $stepIndex < $currentStepIndex ? 'ph-check-circle' : ($stepIndex === $currentStepIndex ? 'ph-dot-outline' : 'ph-circle') }} text-base"></i>
                    {{ str($step)->replace('_', ' ')->title() }}
                </li>
            @endforeach
        </ol>
    @endif
</header>
