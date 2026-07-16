<div class="section-card">
    <h2 class="heading-5 text-neutral-950 mb-4">{{ __('Welcome back') }}, {{ $user->name }}!</h2>
    <p class="text-neutral-500">{{ __('You are logged in as') }} <strong>{{ $user->email }}</strong></p>
    <p class="text-neutral-400 mt-1">{{ __('Role') }}: <x-ui.badge variant="primary">{{ $user->roles->pluck('name')->join(', ') ?: __('User') }}</x-ui.badge></p>
</div>
