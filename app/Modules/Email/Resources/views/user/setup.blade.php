<x-layouts.user :title="__('Email Channel')">
    @php
        $isConnected = $channel && $channel->status?->value === 'connected';
        $credentials = $channel?->credentials ?? [];
        $providers = $providers ?? config('email.providers', []);
        $selectedMailer = old('mail_mailer', $credentials['mail_mailer'] ?? ($defaultProvider ?? config('email.default_provider', 'log')));
        $validationErrors = $errors ?? session('errors');
    @endphp

    <x-marketing-channels::setup-header
        provider="email"
        :channel="$channel"
        :description="__('Send email campaigns from your own SMTP, Mailgun or Sendmail setup.')"
    />

    <x-marketing-channels::status-banner />

    <x-marketing-channels::setup-layout>
        <section class="section-card">
            <x-marketing-channels::setup-steps :steps="$steps" />
        </section>

        @if ($channel)
            <x-marketing-channels::connected-summary
                :channel="$channel"
                provider="email"
                :fields="[
                    __('From address') => $channel->provider_display_id,
                    __('From name') => $credentials['mail_from_name'] ?? null,
                    __('Provider') => $providers[$credentials['mail_mailer'] ?? '']['label'] ?? ($credentials['mail_mailer'] ?? null),
                ]"
            >
                <x-slot:actions>
                    <form method="POST" action="{{ route('user.email.test', $channel) }}">
                        @csrf
                        <button type="submit" class="btn-sm btn-outline">
                            <i class="ph ph-plugs-connected text-base"></i>
                            {{ __('Test Connection') }}
                        </button>
                    </form>
                    @if ($isConnected)
                        <form method="POST" action="{{ route('user.email.destroy', $channel) }}" onsubmit="return confirm('{{ __('Disconnect this email channel?') }}')">
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
            :title="$isConnected ? __('Update credentials') : __('Connect your email provider')"
            :description="__('Choose a provider, then enter the sender details and credentials.')"
        >
            <form class="space-y-5" method="POST" action="{{ $isConnected ? route('user.email.update', $channel) : route('user.email.store') }}">
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
                        <label class="form-label" for="provider_display_id">{{ __('From Address') }} <span class="text-error">*</span></label>
                        <input id="provider_display_id" name="provider_display_id" type="email" class="form-input" value="{{ old('provider_display_id', $channel?->provider_display_id) }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="mail_from_name">{{ __('From Name') }}</label>
                        <input id="mail_from_name" name="mail_from_name" type="text" class="form-input" value="{{ old('mail_from_name', $credentials['mail_from_name'] ?? '') }}">
                    </div>
                    <div>
                        <label class="form-label" for="mail_mailer">{{ __('Mailer') }} <span class="text-error">*</span></label>
                        <select id="mail_mailer" name="mail_mailer" class="form-input" required>
                            @foreach ($providers as $providerKey => $provider)
                                <option value="{{ $providerKey }}" @selected($selectedMailer === $providerKey)>{{ $provider['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @foreach ($providers as $providerKey => $provider)
                    @php $fields = $provider['fields'] ?? []; @endphp
                    <section data-mailer-section="{{ $providerKey }}" class="rounded-xl border border-neutral-100 bg-section p-4 @if ($selectedMailer !== $providerKey) hidden @endif">
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
                        <i class="ph ph-envelope-simple text-base"></i>
                        {{ $isConnected ? __('Update Email Channel') : __('Connect Email Channel') }}
                    </button>
                    <span class="text-xs text-body">{{ __('We run a connection test automatically when you save.') }}</span>
                </div>
            </form>
        </x-marketing-channels::credentials-panel>

        <x-slot:aside>
            <x-marketing-channels::before-you-start :items="[
                __('A sender address on a domain you control, for example noreply@yourbrand.com.'),
                __('For SMTP: host, port, encryption, username and password from your email provider.'),
                __('For Mailgun: your sending domain and API secret.'),
                __('Pick Log (Testing) to try campaigns without sending real email.'),
            ]" />
        </x-slot:aside>
    </x-marketing-channels::setup-layout>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const mailer = document.getElementById('mail_mailer');
                if (! mailer) {
                    return;
                }
                const toggle = () => {
                    const value = mailer.value;
                    document.querySelectorAll('[data-mailer-section]').forEach(section => {
                        section.classList.toggle('hidden', section.dataset.mailerSection !== value);
                    });
                };
                mailer.addEventListener('change', toggle);
                toggle();
            });
        </script>
    @endpush
</x-layouts.user>
