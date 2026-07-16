@extends('layouts.guest')

@section('title', __('Verify Email'))

@section('content')
<div class="text-center">
    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
        <i class="ph-bold ph-envelope-simple text-3xl text-primary"></i>
    </div>

    <h2 class="heading-5 text-neutral-950 mb-2">{{ __('Verify Your Email') }}</h2>
    <p class="text-sm text-neutral-500 mb-6">
        {{ __('We\'ve sent a verification link to your email address. Please check your inbox and click the link to verify your account.') }}
    </p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-primary w-full">
            <i class="ph ph-paper-plane-tilt me-2"></i>
            {{ __('Resend Verification Email') }}
        </button>
    </form>

    <div class="mt-4 pt-4 border-t border-neutral-100">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-neutral-400 hover:text-neutral-600 transition-colors">
                {{ __('Sign out') }}
            </button>
        </form>
    </div>
</div>
@endsection
