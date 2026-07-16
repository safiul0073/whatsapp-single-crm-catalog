@extends('layouts.admin-guest')

@section('title', __('Admin Reset Password'))

@section('content')
<div>
    <h2 class="heading-5 text-neutral-950 mb-1">{{ __('Reset Password') }}</h2>
    <p class="text-sm text-neutral-400 mb-6">{{ __('Enter your new password below') }}</p>

    <form method="POST" action="{{ route('admin.password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-forms.input :label="__('Email Address')" name="email" type="email" :value="old('email', $email)" required placeholder="admin@example.com" icon="ph ph-envelope-simple" />
        <x-forms.input :label="__('New Password')" name="password" type="password" required :placeholder="__('Enter new password')" icon="ph ph-lock" />
        <x-forms.input :label="__('Confirm Password')" name="password_confirmation" type="password" required :placeholder="__('Confirm new password')" icon="ph ph-lock" />
        <x-forms.submit :label="__('Reset Password')" class="w-full" />
    </form>
</div>
@endsection
