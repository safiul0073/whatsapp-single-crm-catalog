<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Complete your WaPro checkout.">
    <title>{{ __('Checkout') }} - WaPro</title>
    @vite(['resources/css/wapro/home.css'])
</head>
<body class="overflow-x-hidden">
    <main class="relative isolate flex min-h-screen flex-col overflow-hidden bg-section px-5 py-8 sm:px-8">
        <div class="f-between">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary text-neutral-0 shadow-[0_6px_16px_-6px_rgba(31,170,83,0.7)]">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" /></svg>
                </span>
                <span class="font-title text-xl font-extrabold tracking-tight text-title">WaPro</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm font-medium text-body transition-colors hover:text-primary">{{ __('Sign out') }}</button>
            </form>
        </div>

        <div class="flex flex-1 items-center justify-center py-10">
            <div class="w-full max-w-xl rounded-3xl border border-neutral-200 bg-neutral-0 p-6 shadow-[0_30px_70px_-40px_rgba(10,27,20,0.35)] sm:p-8">
                <span class="eyebrow">{{ __('Checkout') }}</span>
                <h1 class="heading-2 mt-3">{{ __('Complete payment') }}</h1>

                @if (session('error'))
                    <div class="mt-5 rounded-2xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">{{ session('error') }}</div>
                @endif

                <div class="mt-7 rounded-2xl border border-neutral-200 p-5">
                    <div class="f-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-title">{{ $plan?->name ?? __('Selected plan') }}</p>
                            <p class="mt-1 text-sm text-body">{{ __('Payment ID') }}: {{ $payment->uuid }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-extrabold text-title">{{ $payment->formatted_amount }}</p>
                            <p class="mt-1 text-xs font-semibold uppercase text-primary">{{ ucfirst($payment->status) }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 space-y-3 text-sm text-body">
                    @if ($payment->gateway === 'checkout')
                        <p>{{ __('Choose a payment method to complete your subscription. Dashboard access unlocks only after successful payment.') }}</p>
                    @else
                        <p>{{ __('Complete the selected payment method, then return here to continue to your dashboard.') }}</p>
                        <p>{{ __('Gateway') }}: <span class="font-semibold text-title">{{ ucfirst($payment->gateway) }}</span></p>
                    @endif
                </div>

                @if ($payment->gateway === 'checkout')
                    <div class="mt-7">
                        <h2 class="text-sm font-semibold text-title">{{ __('Payment methods') }}</h2>

                        @if ($gateways === [])
                            <div class="mt-3 rounded-2xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
                                {{ __('No active payment gateways are available. Please contact support.') }}
                            </div>
                        @else
                            <div class="mt-3 grid gap-3">
                                @foreach ($gateways as $gateway)
                                    <form method="POST" action="{{ route('onboarding.checkout.pay', $payment) }}">
                                        @csrf
                                        <input type="hidden" name="gateway" value="{{ $gateway }}">
                                        <button type="submit" class="flex w-full items-center justify-between rounded-2xl border border-neutral-200 bg-neutral-0 px-4 py-3 text-left transition-colors hover:border-primary hover:text-primary">
                                            <span class="font-semibold text-title">{{ ucfirst($gateway) }}</span>
                                            <span class="text-sm text-body">{{ __('Pay now') }}</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-7 grid gap-3 {{ $payment->gateway === 'checkout' ? '' : 'sm:grid-cols-2' }}">
                    <a href="{{ route('onboarding.plan') }}" class="btn btn-outline w-full">{{ __('Choose another plan') }}</a>
                    @if ($payment->status === 'completed')
                        <form method="POST" action="{{ route('onboarding.checkout.pay', $payment) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-full">{{ __('Continue to dashboard') }}</button>
                        </form>
                    @elseif ($payment->gateway !== 'checkout')
                        <a href="{{ route('onboarding.checkout', $payment) }}" class="btn btn-primary w-full">{{ __('Refresh status') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </main>
</body>
</html>
