<div class="section-card">
    <h2 class="heading-5 text-neutral-950 mb-4">{{ __('System Information') }}</h2>
    <div class="space-y-2">
        <div class="flex justify-between text-sm">
            <span class="text-neutral-400">{{ __('PHP Version') }}</span>
            <span class="font-medium text-neutral-900">{{ $systemInfo['php_version'] }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-neutral-400">{{ __('Laravel Version') }}</span>
            <span class="font-medium text-neutral-900">{{ $systemInfo['laravel_version'] }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-neutral-400">{{ __('Server') }}</span>
            <span class="font-medium text-neutral-900">{{ $systemInfo['server_software'] }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-neutral-400">{{ __('Database') }}</span>
            <span class="font-medium text-neutral-900">{{ $systemInfo['database'] }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-neutral-400">{{ __('Environment') }}</span>
            <span class="font-medium text-neutral-900">{{ config('app.env') }}</span>
        </div>
    </div>
</div>
