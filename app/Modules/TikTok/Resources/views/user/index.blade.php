<x-layouts.user :title="__('TikTok')">
    <x-marketing-channels::status-banner />

    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="heading-4 text-title">{{ __('TikTok') }}</h1>
            <p class="mt-1 text-sm text-body">{{ __('Connect your TikTok account. Messaging and shop sync are not available through TikTok yet.') }}</p>
        </div>

        <section class="section-card">
            <h2 class="font-semibold text-title">{{ __('TikTok account') }}</h2>
            @if (! $loginEnabled)
                <p class="mt-2 text-sm text-body">{{ __('TikTok connections are turned off by the platform administrator.') }}</p>
            @elseif (! $ready)
                <p class="mt-2 text-sm text-body">{{ __('Admin setup required: TikTok is not configured yet.') }}</p>
            @else
                <form method="POST" action="{{ route('user.tiktok.connect') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn-sm btn-primary"><i class="ph ph-tiktok-logo text-base"></i> {{ $accounts->isEmpty() ? __('Connect TikTok') : __('Connect another account') }}</button>
                </form>
            @endif
        </section>

        <section class="section-card">
            <h2 class="font-semibold text-title">{{ __('TikTok Shop') }}</h2>
            <p class="mt-2 text-sm text-body">
                @if ($shopReady)
                    {{ __('TikTok Shop is enabled by your platform administrator. Connecting a shop will be available soon.') }}
                @else
                    {{ __('TikTok Shop is not available yet.') }}
                @endif
            </p>
        </section>

        @foreach ($accounts as $account)
            <section class="section-card flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-title">{{ $account->name }}</h2>
                    <p class="text-sm text-body">{{ str($account->status->value)->title() }}@if ($account->connected_at) · {{ __('Connected :date', ['date' => $account->connected_at->toFormattedDateString()]) }}@endif</p>
                    @if (data_get($account->settings, 'last_error'))
                        <p class="mt-1 text-sm text-error">{{ data_get($account->settings, 'last_error') }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('user.tiktok.disconnect', $account) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-sm btn-outline">{{ __('Disconnect') }}</button>
                </form>
            </section>
        @endforeach
    </div>
</x-layouts.user>
