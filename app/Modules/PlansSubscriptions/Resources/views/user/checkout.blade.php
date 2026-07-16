@php
    $checkoutAmount = currency_format($payment->default_amount, currency_default_code());
@endphp

<x-layouts.user :title="__('Checkout')">
    <div class="max-w-3xl mx-auto py-8">
        <div>
            <h2 class="heading-2">{{ __('Checkout') }}</h2>
            <p class="m-text mt-1">{{ __('Complete payment to activate your plan.') }}</p>
        </div>

        @if (session('error'))
            <div class="mt-5 rounded-2xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
                {{ session('error') }}
            </div>
        @endif

        <div class="mt-6 grid gap-6 md:grid-cols-3">
            {{-- Plan summary details card --}}
            <div class="md:col-span-2 space-y-6">
                <div class="app-card p-5 sm:p-6">
                    <h3 class="heading-4">{{ __('Subscription Plan') }}</h3>
                    <div class="mt-4 rounded-2xl border border-neutral-200 p-5 bg-section">
                        <div class="f-between gap-4">
                            <div>
                                <p class="text-sm font-bold text-title">{{ $plan?->name ?? __('Selected plan') }}</p>
                                <p class="mt-1 text-xs text-body">{{ __('Payment ID') }}: <span class="font-mono text-[11px]">{{ $payment->uuid }}</span></p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-extrabold text-title">{{ $checkoutAmount }}</p>
                                <p class="mt-1 text-xs font-semibold uppercase text-primary">{{ ucfirst($payment->status) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3 text-sm text-body leading-relaxed">
                        @if ($payment->gateway === 'checkout')
                            <p>{{ __('Choose a payment method below to complete your plan subscription upgrade.') }}</p>
                        @else
                            <p>{{ __('Please complete the selected payment process, then click Refresh status to continue.') }}</p>
                            <p>{{ __('Selected Gateway') }}: <span class="font-semibold text-title">{{ ucfirst($payment->gateway) }}</span></p>
                        @endif
                    </div>
                </div>

                {{-- Payment options --}}
                @if ($payment->gateway === 'checkout')
                    <div class="app-card p-5 sm:p-6">
                        <h3 class="heading-4">{{ __('Select Payment Method') }}</h3>

                        @if ($gateways === [])
                            <div class="mt-4 rounded-2xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
                                {{ __('No active payment gateways are available. Please contact support.') }}
                            </div>
                        @else
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                @foreach ($gateways as $gateway)
                                    @php
                                        $gatewayCharge = $gatewayCharges[$gateway] ?? null;
                                    @endphp
                                    <form method="POST" action="{{ route('user.subscription.checkout.pay', $payment) }}" class="w-full">
                                        @csrf
                                        <input type="hidden" name="gateway" value="{{ $gateway }}">
                                        <button type="submit" class="group relative flex w-full items-center justify-between rounded-2xl border border-neutral-200 bg-neutral-0 p-4 text-left transition-all duration-200 hover:-translate-y-0.5 hover:border-primary hover:shadow-md cursor-pointer">
                                            <div class="flex items-center gap-3">
                                                <div class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary transition-all duration-200 group-hover:bg-primary group-hover:text-white">
                                                    <i class="ph {{ match($gateway) {
                                                        'stripe' => 'ph-credit-card',
                                                        'paypal' => 'ph-paypal-logo',
                                                        'razorpay' => 'ph-wallet',
                                                        'sslcommerz' => 'ph-shield-check',
                                                        'paystack' => 'ph-bank',
                                                        'flutterwave' => 'ph-lightning',
                                                        default => 'ph-currency-dollar'
                                                    } }} text-xl"></i>
                                                </div>
                                                <div>
                                                    <span class="block font-bold text-title text-sm group-hover:text-primary transition-colors duration-200">{{ match($gateway) {
                                                        'stripe' => 'Stripe',
                                                        'paypal' => 'PayPal',
                                                        'razorpay' => 'Razorpay',
                                                        'sslcommerz' => 'SSLCommerz',
                                                        'paystack' => 'Paystack',
                                                        'flutterwave' => 'Flutterwave',
                                                        default => ucfirst($gateway)
                                                    } }}</span>
                                                    <span class="block text-[11px] text-neutral-400 mt-0.5">{{ match($gateway) {
                                                        'stripe' => __('Credit / Debit Card'),
                                                        'paypal' => __('PayPal Account or Card'),
                                                        'razorpay' => __('Cards, Netbanking or UPI'),
                                                        'sslcommerz' => __('Mobile & Local Payments'),
                                                        'paystack' => __('Secure African Gateway'),
                                                        'flutterwave' => __('Global Card & Mobile Money'),
                                                        default => __('Secure Payment Gateway')
                                                    } }}</span>
                                                    @if ($gatewayCharge)
                                                        <span class="block text-[11px] text-neutral-500 mt-0.5">
                                                            {{ __('Charge') }}:
                                                            {{ currency_format($gatewayCharge['charge_amount'], currency_default_code()) }}
                                                            · {{ __('Total') }}:
                                                            {{ currency_format($gatewayCharge['payable_amount'], currency_default_code()) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            <i class="ph ph-caret-right text-lg text-neutral-400 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-primary"></i>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Checkout side controls --}}
            <div class="space-y-4">
                <div class="app-card p-5">
                    <h4 class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Summary') }}</h4>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="f-between">
                            <span class="text-body">{{ __('Subtotal') }}</span>
                            <span class="font-semibold text-title">{{ $checkoutAmount }}</span>
                        </div>
                        <div class="f-between border-t border-neutral-100 pt-2">
                            <span class="font-bold text-title">{{ __('Total Amount') }}</span>
                            <span class="font-bold text-title">{{ $checkoutAmount }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    @if ($payment->status === 'completed')
                        <form method="POST" action="{{ route('user.subscription.checkout.pay', $payment) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-full">{{ __('Activate subscription') }}</button>
                        </form>
                    @elseif ($payment->gateway !== 'checkout')
                        <a href="{{ route('user.subscription.checkout.page', $payment) }}" class="btn btn-primary w-full">
                            <i class="ph ph-arrow-counter-clockwise"></i>
                            {{ __('Refresh status') }}
                        </a>
                    @endif
                    <a href="{{ route('user.subscription.show') }}" class="btn btn-outline w-full">{{ __('Cancel & Return') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
