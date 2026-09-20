<x-layouts.user :title="__('SMS Channel')">
    @php
        $isConnected = $channel && $channel->status?->value === 'connected';
        $credentials = $channel?->credentials ?? [];
        $providers = $providers ?? config('sms.providers', []);
        $selectedProvider = old('sms_provider', $credentials['sms_provider'] ?? ($defaultProvider ?? config('sms.default_provider', 'log')));
        $validationErrors = $errors ?? session('errors');
    @endphp

    <x-marketing-channels::setup-header
        provider="sms"
        :channel="$channel"
        :description="__('Send SMS campaigns through Twilio or Vonage from a sender number you own.')"
    />

    <x-marketing-channels::status-banner />

    <x-marketing-channels::setup-layout>
        <section class="section-card">
            <x-marketing-channels::setup-steps :steps="$steps" />
        </section>

        @if ($channel)
            <x-marketing-channels::connected-summary
                :channel="$channel"
                provider="sms"
                :fields="[
                    __('From number') => $channel->provider_display_id,
                    __('Gateway') => $providers[$credentials['sms_provider'] ?? '']['label'] ?? ($credentials['sms_provider'] ?? null),
                ]"
            >
                <x-slot:actions>
                    <form method="POST" action="{{ route('user.sms.test', $channel) }}">
                        @csrf
                        <button type="submit" class="btn-sm btn-outline">
                            <i class="ph ph-plugs-connected text-base"></i>
                            {{ __('Test Connection') }}
                        </button>
                    </form>
                    @if ($isConnected)
                        <form method="POST" action="{{ route('user.sms.destroy', $channel) }}" onsubmit="return confirm('{{ __('Disconnect this SMS channel?') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-sm btn-outline text-error hover:border-error hover:text-error">
                                <i class="ph ph-plugs text-base"></i>
                                {{ __('Disconnect') }}
                            </button>
                        </form>
                    @endif
                </x-slot:actions>
            </x-marketing-channels::connected-summary>
        @endif

        <x-marketing-channels::credentials-panel
            :open="! $isConnected || $validationErrors?->any()"
            :title="$isConnected ? __('Update credentials') : __('Connect your SMS gateway')"
            :description="__('Choose a gateway, then enter the sender number and credentials.')"
        >
            <form class="space-y-5" method="POST" action="{{ $isConnected ? route('user.sms.update', $channel) : route('user.sms.store') }}">
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
                        <label class="form-label" for="provider_display_id">{{ __('From Number') }} <span class="text-error">*</span></label>
                        <input id="provider_display_id" name="provider_display_id" type="text" class="form-input" value="{{ old('provider_display_id', $channel?->provider_display_id) }}" required placeholder="+1234567890">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="sms_provider">{{ __('Gateway') }} <span class="text-error">*</span></label>
                        <select id="sms_provider" name="sms_provider" class="form-input" required>
                            @foreach ($providers as $providerKey => $provider)
                                <option value="{{ $providerKey }}" @selected($selectedProvider === $providerKey)>{{ $provider['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @foreach ($providers as $providerKey => $provider)
                    @php $fields = $provider['fields'] ?? []; @endphp
                    <section data-sms-provider-section="{{ $providerKey }}" class="rounded-xl border border-neutral-100 bg-section p-4 @if ($selectedProvider !== $providerKey) hidden @endif">
                        <div>
                            <p class="text-sm font-semibold text-title">{{ $provider['label'] }}</p>
                            @if (! empty($provider['description']))
                                <p class="mt-1 text-xs text-body">{{ $provider['description'] }}</p>
                            @endif
                        </div>

                        @if ($fields)
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                @foreach ($fields as $fieldName => $field)
                                    @php
                                        $type = $field['type'] ?? 'text';
                                        $value = old($fieldName, $credentials[$fieldName] ?? ($field['default'] ?? ''));
                                    @endphp
                                    <label class="block {{ $type === 'password' ? 'sm:col-span-2' : '' }}">
                                        <span class="form-label">{{ $field['label'] }} @if ($field['required'] ?? false)<span class="text-error">*</span>@endif</span>
                                        @if ($type === 'select')
                                            <select id="{{ $fieldName }}" name="{{ $fieldName }}" class="form-input">
                                                @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input id="{{ $fieldName }}" name="{{ $fieldName }}" type="{{ $type }}" class="form-input" value="{{ $type === 'password' ? '' : $value }}" placeholder="{{ ($field['secret'] ?? false) && $isConnected ? __('Saved (enter to change)') : ($field['placeholder'] ?? '') }}">
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <p class="mt-4 text-sm text-body">{{ __('No additional credentials are required for this provider.') }}</p>
                        @endif
                    </section>
                @endforeach

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-chat-text text-base"></i>
                        {{ $isConnected ? __('Update SMS Channel') : __('Connect SMS Channel') }}
                    </button>
                    <span class="text-xs text-body">{{ __('We run a connection test automatically when you save.') }}</span>
                </div>
            </form>
        </x-marketing-channels::credentials-panel>

        <x-slot:aside>
            <x-marketing-channels::before-you-start :items="[
                __('A sender number approved by your gateway, in international format.'),
                __('For Twilio: the Account SID and Auth Token from the Twilio console.'),
                __('For Vonage: your API key and API secret.'),
                __('Pick Log (Testing) to try campaigns without sending real SMS.'),
            ]" />
        </x-slot:aside>
    </x-marketing-channels::setup-layout>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const provider = document.getElementById('sms_provider');
                if (! provider) {
                    return;
                }
                const toggle = () => {
                    const value = provider.value;
                    document.querySelectorAll('[data-sms-provider-section]').forEach(section => {
                        section.classList.toggle('hidden', section.dataset.smsProviderSection !== value);
                    });
                };
                provider.addEventListener('change', toggle);
                toggle();
            });
        </script>
    @endpush
</x-layouts.user>
