<x-layouts.user :title="__('Email Channel')">
    @php
        $config = config('marketing-channels.providers.email');
        $isConnected = $channel && $channel->status?->value === 'connected';
        $credentials = $channel?->credentials ?? [];
        $providers = $providers ?? config('email.providers', []);
        $selectedMailer = old('mail_mailer', $credentials['mail_mailer'] ?? ($defaultProvider ?? config('email.default_provider', 'log')));
    @endphp

    <div class="flex items-center gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
            <i class="ph {{ $config['icon'] }} text-xl"></i>
        </span>
        <div>
            <h2 class="heading-2">{{ __('Email Channel') }}</h2>
            <p class="m-text mt-1">{{ __('Configure workspace email delivery for campaigns.') }}</p>
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

    <form class="app-card mt-6 space-y-5 p-5 sm:p-6" method="POST" action="{{ $isConnected ? route('user.email.update', $channel) : route('user.email.store') }}">
        @csrf
        @if ($isConnected)
            @method('PUT')
        @endif

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
                                    <input id="{{ $fieldName }}" name="{{ $fieldName }}" type="{{ $type }}" class="form-input" value="{{ $type === 'password' ? '' : $value }}" placeholder="{{ ($field['secret'] ?? false) && $isConnected ? 'Saved (enter to change)' : ($field['placeholder'] ?? '') }}">
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
            <button type="submit" class="btn btn-primary">{{ $isConnected ? __('Update Email Channel') : __('Connect Email Channel') }}</button>
            @if ($isConnected)
                <a href="{{ route('user.email.test', $channel) }}" class="btn btn-outline">{{ __('Test Connection') }}</a>
                <form method="POST" action="{{ route('user.email.destroy', $channel) }}" class="inline" onsubmit="return confirm('Disconnect this email channel?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline text-error hover:border-error hover:text-error">{{ __('Disconnect') }}</button>
                </form>
            @endif
        </div>
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const mailer = document.getElementById('mail_mailer');
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
