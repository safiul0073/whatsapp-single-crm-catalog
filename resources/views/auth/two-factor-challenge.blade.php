@extends('layouts.guest')

@section('title', __('Two-Factor Authentication'))

@section('content')
<div>
    <h2 class="heading-5 text-neutral-950 mb-1">{{ __('Two-Factor Authentication') }}</h2>
    <p class="text-sm text-neutral-400 mb-6">{{ __('Enter the 6-digit code sent to :destination.', ['destination' => $maskedDestination ?? __('your verified contact method')]) }}</p>

    {{-- OTP Code Form --}}
    <form method="POST" action="{{ route("{$panelKey}.two-factor.verify") }}" class="space-y-4">
        @csrf
        <x-forms.input :label="__('Verification Code')" name="code" type="text" required placeholder="000000" icon="ph ph-shield-check" inputmode="numeric" autocomplete="one-time-code" autofocus />
        <x-forms.submit :label="__('Verify')" class="w-full" />
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route("{$panelKey}.two-factor.challenge", ['resend' => 1]) }}" class="text-sm text-primary hover:underline">{{ __('Resend code') }}</a>
    </div>

    @php
        $logoutRoute = Route::has("{$panelKey}.logout") ? route("{$panelKey}.logout") : route('logout');
    @endphp
    <p class="mt-6 text-center text-sm text-neutral-400">
        <a href="{{ $logoutRoute }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="font-medium text-primary hover:underline">{{ __('Sign out') }}</a>
    </p>
    <form id="logout-form" action="{{ $logoutRoute }}" method="POST" class="hidden">@csrf</form>
</div>
@endsection
