@extends('layouts.guest')

@section('title', __('Forgot Password'))

@section('content')
<div>
    <h2 class="heading-5 text-neutral-950 mb-1">{{ __('Forgot Password') }}</h2>
    <p class="text-sm text-neutral-400 mb-6">{{ __('Enter your email to receive a password reset link') }}</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-forms.input :label="__('Email Address')" name="email" type="email" :value="old('email')" required placeholder="you@example.com" icon="ph ph-envelope-simple" />

        <x-forms.submit :label="__('Send Reset Link')" class="w-full" />
    </form>

    <p class="mt-6 text-center text-sm text-neutral-400">
        {{ __('Remember your password?') }}
        <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">{{ __('Back to login') }}</a>
    </p>
</div>
@endsection
