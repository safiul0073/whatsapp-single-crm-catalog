@extends('layouts.admin-guest')

@section('title', __('Two-Factor Authentication'))

@section('content')
<div x-data="{ showRecovery: false }">
    <h2 class="heading-5 text-neutral-950 mb-1">{{ __('Two-Factor Authentication') }}</h2>
    <p class="text-sm text-neutral-400 mb-6" x-show="!showRecovery">{{ __('Enter the 6-digit code from your authenticator app to continue.') }}</p>
    <p class="text-sm text-neutral-400 mb-6" x-show="showRecovery" x-cloak>{{ __('Enter one of your recovery codes to continue.') }}</p>

    {{-- OTP Code Form --}}
    <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="space-y-4" x-show="!showRecovery">
        @csrf
        <x-forms.input :label="__('Authentication Code')" name="code" type="text" required placeholder="000000" icon="ph ph-shield-check" inputmode="numeric" autocomplete="one-time-code" autofocus />
        <x-forms.submit :label="__('Verify')" class="w-full" />
    </form>

    {{-- Recovery Code Form --}}
    <form method="POST" action="{{ route('admin.two-factor.verify-recovery') }}" class="space-y-4" x-show="showRecovery" x-cloak>
        @csrf
        <x-forms.input :label="__('Recovery Code')" name="recovery_code" type="text" required :placeholder="__('XXXX-XXXX')" icon="ph ph-key" autofocus />
        <x-forms.submit :label="__('Verify')" class="w-full" />
    </form>

    <div class="mt-4 text-center">
        <button type="button" class="text-sm text-primary hover:underline" @click="showRecovery = !showRecovery">
            <span x-show="!showRecovery">{{ __('Use a recovery code') }}</span>
            <span x-show="showRecovery" x-cloak>{{ __('Use an authentication code') }}</span>
        </button>
    </div>

    <p class="mt-6 text-center text-sm text-neutral-400">
        <a href="{{ route('admin.logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="font-medium text-primary hover:underline">{{ __('Sign out') }}</a>
    </p>
    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="hidden">@csrf</form>
</div>
@endsection
