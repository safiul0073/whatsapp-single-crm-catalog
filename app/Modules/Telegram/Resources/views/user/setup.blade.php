<x-layouts.user :title="__('Telegram Channel')">
    @php
        $isConnected = $channel && $channel->status?->value === 'connected';
        $hasChannel = filled($channel);
        $settings = $channel?->settings ?? [];
        $telegramLinks = $telegramLinks ?? null;
        $validationErrors = $errors ?? session('errors');
    @endphp

    <x-marketing-channels::setup-header
        provider="telegram"
        :channel="$channel"
        :description="__('Connect a Telegram bot for subscriber campaigns, channel broadcasts and inbound messages.')"
    />

    <x-marketing-channels::status-banner />

    <x-marketing-channels::setup-layout>
        <section class="section-card">
            <x-marketing-channels::setup-steps :steps="$steps" />
        </section>

        @if ($hasChannel)
            <x-marketing-channels::connected-summary
                :channel="$channel"
                provider="telegram"
                :fields="[
                    __('Bot username') => $channel->provider_account_id,
                    __('Bot name') => $channel->provider_display_id,
                    __('Detected username') => $settings['telegram_bot_username'] ?? null,
                    __('Default channel') => $settings['default_channel_username'] ?? null,
                ]"
            >
                <x-slot:actions>
                    <form method="POST" action="{{ route('user.telegram.test', $channel) }}">
                        @csrf
                        <button type="submit" class="btn-sm btn-outline">
                            <i class="ph ph-plugs-connected text-base"></i>
                            {{ __('Test Connection') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.telegram.set-webhook', $channel) }}">
                        @csrf
                        <button type="submit" class="btn-sm btn-outline">
                            <i class="ph ph-webhooks-logo text-base"></i>
                            {{ __('Set Webhook') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.telegram.destroy', $channel) }}" onsubmit="return confirm('{{ __('Disconnect this Telegram channel?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-sm btn-outline text-error hover:border-error hover:text-error">
                            <i class="ph ph-plugs text-base"></i>
                            {{ __('Disconnect') }}
                        </button>
                    </form>
                </x-slot:actions>
            </x-marketing-channels::connected-summary>
        @endif

        @if ($isConnected && $telegramLinks)
            <section class="section-card" data-telegram-links>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="heading-5">{{ __('Share your bot') }}</h3>
                        <p class="mt-1 text-sm text-body">{{ __('Share these public links manually. Use contact-specific invite links when Telegram must map back to a CRM contact.') }}</p>
                    </div>
                    <span class="badge badge-success">{{ '@'.$telegramLinks['bot_username'] }}</span>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    <div>
                        <label class="form-label" for="telegram_bot_link">{{ __('General bot link') }}</label>
                        <div class="flex gap-2">
                            <input id="telegram_bot_link" type="text" readonly class="form-input" value="{{ $telegramLinks['bot_link'] }}">
                            <button type="button" class="btn-sm btn-outline shrink-0" data-copy="{{ $telegramLinks['bot_link'] }}" aria-label="{{ __('Copy bot link') }}">
                                <i class="ph ph-copy text-base"></i>
                                <span data-copy-label>{{ __('Copy') }}</span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="telegram_subscribe_link">{{ __('Generic subscribe link') }}</label>
                        <div class="flex gap-2">
                            <input id="telegram_subscribe_link" type="text" readonly class="form-input" value="{{ $telegramLinks['generic_subscribe_link'] }}">
                            <button type="button" class="btn-sm btn-outline shrink-0" data-copy="{{ $telegramLinks['generic_subscribe_link'] }}" aria-label="{{ __('Copy subscribe link') }}">
                                <i class="ph ph-copy text-base"></i>
                                <span data-copy-label>{{ __('Copy') }}</span>
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

        <x-marketing-channels::credentials-panel
            :open="! $isConnected || $validationErrors?->any()"
            :title="$isConnected ? __('Update bot credentials') : __('Connect your bot')"
            :description="__('Paste the token from @BotFather. We verify it and register the webhook for you.')"
        >
            <form class="space-y-5" method="POST" action="{{ $isConnected ? route('user.telegram.update', $channel) : route('user.telegram.store') }}">
                @csrf
                @if ($isConnected)
                    @method('PUT')
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
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
                        <label class="form-label" for="access_token">{{ __('Bot Token') }} @unless ($isConnected)<span class="text-error">*</span>@endunless</label>
                        <input id="access_token" name="access_token" type="password" class="form-input" placeholder="{{ $isConnected ? __('Saved (enter to change)') : '' }}" autocomplete="off">
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-100 bg-section p-4">
                    <div class="flex items-center gap-2.5">
                        <input id="supports_channels" name="supports_channels" type="checkbox" value="1" class="app-checkbox" @checked(old('supports_channels', $settings['supports_channels'] ?? false))>
                        <label for="supports_channels" class="text-sm font-semibold text-title">{{ __('Enable channel broadcasting') }}</label>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="default_channel_username">{{ __('Default Channel Username') }}</label>
                        <input id="default_channel_username" name="default_channel_username" type="text" class="form-input" value="{{ old('default_channel_username', $settings['default_channel_username'] ?? '') }}" placeholder="@mychannel">
                        <p class="form-hint mt-1">{{ __('The bot must be an administrator of this channel to broadcast into it.') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-telegram-logo text-base"></i>
                        {{ $isConnected ? __('Update Telegram Channel') : __('Connect Telegram Bot') }}
                    </button>
                </div>
            </form>
        </x-marketing-channels::credentials-panel>

        <x-slot:aside>
            <x-marketing-channels::before-you-start :items="[
                __('A bot token from @BotFather (run /newbot in Telegram).'),
                __('The bot username, for example wapro_support_bot.'),
                __('Optional: a Telegram channel where the bot is an administrator, for broadcasts.'),
            ]" />

            <x-marketing-channels::webhook-box
                :url="$webhookUrl"
                :hint="__('Registered automatically when the bot connects. Use Set Webhook to re-register it.')"
            />
        </x-slot:aside>
    </x-marketing-channels::setup-layout>
</x-layouts.user>
