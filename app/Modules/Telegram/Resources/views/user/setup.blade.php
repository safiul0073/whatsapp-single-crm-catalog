<x-layouts.user :title="__('Telegram Channel')">
    @php
        $config = config('marketing-channels.providers.telegram');
        $isConnected = $channel && $channel->status?->value === 'connected';
        $hasChannel = filled($channel);
        $credentials = $channel?->credentials ?? [];
        $settings = $channel?->settings ?? [];
        $telegramLinks = $telegramLinks ?? null;
    @endphp

    <div class="flex items-center gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
            <i class="ph {{ $config['icon'] }} text-xl"></i>
        </span>
        <div>
            <h2 class="heading-2">{{ __('Telegram Channel') }}</h2>
            <p class="m-text mt-1">{{ __('Configure a Telegram bot for subscriber and channel campaigns.') }}</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mt-4 rounded-xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
            {{ session('error') }}
        </div>
    @endif

    <form class="app-card mt-6 space-y-5 p-5 sm:p-6" method="POST" action="{{ $isConnected ? route('user.telegram.update', $channel) : route('user.telegram.store') }}">
        @csrf
        @if ($isConnected)
            @method('PUT')
        @endif

        <div>
            <label class="form-label" for="name">{{ __('Channel Name') }} <span class="text-error">*</span></label>
            <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $channel?->name) }}" required>
        </div>

        <div>
            <label class="form-label" for="provider_account_id">{{ __('Bot Username') }} <span class="text-error">*</span></label>
            <input id="provider_account_id" name="provider_account_id" type="text" class="form-input" value="{{ old('provider_account_id', $channel?->provider_account_id) }}" required placeholder="wapro_support_bot">
        </div>

        <div>
            <label class="form-label" for="provider_display_id">{{ __('Bot Name') }}</label>
            <input id="provider_display_id" name="provider_display_id" type="text" class="form-input" value="{{ old('provider_display_id', $channel?->provider_display_id) }}" placeholder="WaPro Support Bot">
        </div>

        <div>
            <label class="form-label" for="access_token">{{ __('Bot Token') }} <span class="text-error">*</span></label>
            <input id="access_token" name="access_token" type="password" class="form-input" placeholder="{{ $isConnected ? 'Saved (enter to change)' : '' }}">
        </div>

        <div class="flex items-center gap-2.5">
            <input id="supports_channels" name="supports_channels" type="checkbox" value="1" class="app-checkbox" @checked(old('supports_channels', $settings['supports_channels'] ?? false))>
            <label for="supports_channels" class="text-sm font-semibold text-title">{{ __('Enable channel broadcasting') }}</label>
        </div>

        <div>
            <label class="form-label" for="default_channel_username">{{ __('Default Channel Username') }}</label>
            <input id="default_channel_username" name="default_channel_username" type="text" class="form-input" value="{{ old('default_channel_username', $settings['default_channel_username'] ?? '') }}" placeholder="@mychannel">
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            <button type="submit" class="btn btn-primary">{{ $isConnected ? __('Update Telegram Channel') : __('Connect Telegram Bot') }}</button>
        </div>
    </form>

    @if ($isConnected && $telegramLinks)
        <section class="app-card mt-4 p-5 sm:p-6" x-data="{ copied: null, copy(value, key) { navigator.clipboard?.writeText(value); this.copied = key; setTimeout(() => this.copied = null, 1800); } }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="heading-4">{{ __('Telegram deep links') }}</h3>
                    <p class="m-text mt-1">{{ __('Share these public links manually. Use contact-specific invite links when you need Telegram to map back to a CRM contact.') }}</p>
                </div>
                <span class="badge badge-success">{{ '@'.$telegramLinks['bot_username'] }}</span>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="form-label" for="telegram_bot_link">{{ __('General bot link') }}</label>
                    <div class="flex gap-2">
                        <input id="telegram_bot_link" type="text" readonly class="form-input" value="{{ $telegramLinks['bot_link'] }}">
                        <button type="button" class="btn-sm btn-outline shrink-0" @click="copy('{{ $telegramLinks['bot_link'] }}', 'bot')">
                            <i class="ph ph-copy"></i>
                            <span x-text="copied === 'bot' ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="form-label" for="telegram_subscribe_link">{{ __('Generic subscribe link') }}</label>
                    <div class="flex gap-2">
                        <input id="telegram_subscribe_link" type="text" readonly class="form-input" value="{{ $telegramLinks['generic_subscribe_link'] }}">
                        <button type="button" class="btn-sm btn-outline shrink-0" @click="copy('{{ $telegramLinks['generic_subscribe_link'] }}', 'subscribe')">
                            <i class="ph ph-copy"></i>
                            <span x-text="copied === 'subscribe' ? '{{ __('Copied') }}' : '{{ __('Copy') }}'">{{ __('Copy') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="info-banner mt-4">
                <i class="ph ph-info text-lg text-primary"></i>
                <p class="text-sm text-body">{{ __('A generic link can create an inbound Telegram conversation, but it cannot reliably identify an existing contact unless the customer shares their phone. Contact-specific invite links include a private token for matching.') }}</p>
            </div>
        </section>
    @endif

    @if ($hasChannel)
        <div class="app-card mt-4 flex flex-wrap items-center gap-3 p-5 sm:p-6">
            <form method="POST" action="{{ route('user.telegram.test', $channel) }}">
                @csrf
                <button type="submit" class="btn btn-outline">{{ __('Test Connection') }}</button>
            </form>
            <form method="POST" action="{{ route('user.telegram.set-webhook', $channel) }}">
                @csrf
                <button type="submit" class="btn btn-outline">{{ __('Set Webhook') }}</button>
            </form>
            <form method="POST" action="{{ route('user.telegram.destroy', $channel) }}" onsubmit="return confirm('Disconnect this Telegram channel?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline text-error hover:border-error hover:text-error">{{ __('Disconnect') }}</button>
            </form>
        </div>
    @endif
</x-layouts.user>
