<x-layouts.user :title="__('WhatsApp Cloud')">
    @php
        $catalogs = $catalogs ?? collect();
        $embeddedSignup = $embeddedSignup ?? ['enabled' => false, 'app_id' => '', 'config_id' => '', 'graph_api_version' => 'v20.0'];
        $validationErrors = $errors ?? session('errors');
        $displayWabaId = $channel?->provider_account_id;
        $displayBusinessId = data_get($channel?->settings, 'business_id');
        $displayPhoneNumberId = $channel?->provider_phone_id;
        $displayVerifyToken = $channel?->webhook_verify_token;
        $hasAccessToken = filled($channel?->credential('access_token'));
        $isConnected = $channel && $channel->status?->value === 'connected' && $hasAccessToken;
    @endphp

    <x-marketing-channels::setup-header
        provider="whatsapp"
        :channel="$channel"
        :description="__('Connect a WhatsApp Business number for inbox, campaigns, templates and automation.')"
    />

    <x-marketing-channels::status-banner />
    @include('whatsapp-cloud::user.setup-tabs')

    <x-marketing-channels::setup-layout>
        <section class="section-card">
            <x-marketing-channels::setup-steps :steps="$steps" />
        </section>

        @if ($channel)
            <x-marketing-channels::connected-summary
                :channel="$channel"
                provider="whatsapp"
                :fields="[
                    __('WhatsApp Business Account ID') => $displayWabaId,
                    __('Business ID') => $displayBusinessId,
                    __('Phone Number ID') => $displayPhoneNumberId,
                    __('Display phone') => $channel->provider_display_id,
                    __('Verified name') => data_get($channel->settings, 'verified_name'),
                ]"
            >
                <x-slot:actions>
                    @if ($isConnected)
                        <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.test-channel', $channel) }}">
                            @csrf
                            <button type="submit" class="btn-sm btn-outline">
                                <i class="ph ph-plugs-connected text-base"></i>
                                {{ __('Test Connection') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.sync') }}">
                            @csrf
                            <button type="submit" class="btn-sm btn-outline">
                                <i class="ph ph-arrows-clockwise text-base"></i>
                                {{ __('Sync from Meta') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.disconnect') }}">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                data-confirm
                                data-confirm-title="{{ __('Disconnect WhatsApp Channel?') }}"
                                data-confirm-message="{{ __('Disconnect this WhatsApp channel from your workspace?') }}"
                                data-confirm-button="{{ __('Disconnect') }}"
                                data-confirm-variant="danger"
                                data-confirm-icon="ph-plugs"
                                class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                            >
                                <i class="ph ph-plugs text-base"></i>
                                {{ __('Disconnect') }}
                            </button>
                        </form>
                    @else
                        <a href="#whatsapp-connect" class="btn-sm btn-primary">
                            <i class="ph ph-plugs-connected text-base"></i>
                            {{ __('Reconnect Channel') }}
                        </a>
                        <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.destroy', $channel) }}">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                data-confirm
                                data-confirm-title="{{ __('Remove WhatsApp Channel?') }}"
                                data-confirm-message="{{ __('Permanently remove this WhatsApp channel configuration from your workspace?') }}"
                                data-confirm-button="{{ __('Remove Channel') }}"
                                data-confirm-variant="danger"
                                data-confirm-icon="ph-trash"
                                class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                            >
                                <i class="ph ph-trash text-base"></i>
                                {{ __('Remove') }}
                            </button>
                        </form>
                    @endif
                </x-slot:actions>
            </x-marketing-channels::connected-summary>

            <x-marketing-channels::readiness-checklist :report="$readinessReport" :channel="$channel" data-whatsapp-readiness />
        @endif

        <section class="section-card" data-whatsapp-connect id="whatsapp-connect">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
                        <i class="ph-fill ph-meta-logo text-2xl"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-base font-bold text-title">{{ $channel ? __('Switch or reconnect the number') : __('Connect WhatsApp with Meta') }}</p>
                        <p class="mt-0.5 text-xs font-medium text-body">
                            {{ $channel
                                ? __('Reconnect when Meta access expired, or add another number. It uses the same Meta login on the Channels page.')
                                : __('WhatsApp, Messenger and Instagram connect together from the Channels page with one Meta login.') }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('user.channels.index') }}#meta-connect" class="btn {{ $channel ? 'btn-outline' : 'btn-primary' }} shrink-0" data-meta-connect-link>
                    <i class="ph-fill ph-meta-logo text-base"></i>
                    {{ $channel ? __('Reconnect with Meta') : __('Connect with Meta') }}
                </a>
            </div>
            @if (! $embeddedSignup['enabled'])
                <x-ui.alert type="warning" class="mt-4">{{ __('Connecting WhatsApp is not available yet. The platform administrator still has to finish the Meta app setup.') }}</x-ui.alert>
            @endif
        </section>

        <x-slot:aside>
            <x-marketing-channels::before-you-start :items="[
                __('Admin access to the Meta Business portfolio that should own the WhatsApp Business Account.'),
                __('The phone number to connect, able to receive an SMS or call, or your WhatsApp Business app to keep your existing number.'),
                __('Meta creates the access token and webhook automatically. No IDs or tokens to copy.'),
            ]">
                <a href="https://developers.facebook.com/docs/whatsapp/embedded-signup" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-semibold text-primary hover:underline">
                    {{ __('About Meta Embedded Signup') }}
                    <i class="ph ph-arrow-square-out"></i>
                </a>
            </x-marketing-channels::before-you-start>

            <x-marketing-channels::webhook-box
                :url="$webhookUrl"
                :verify-token="$displayVerifyToken"
                :hint="__($provider['webhook_hint'] ?? 'Use this provider webhook URL in Meta app webhook settings.')"
            />

        </x-slot:aside>
    </x-marketing-channels::setup-layout>

</x-layouts.user>
