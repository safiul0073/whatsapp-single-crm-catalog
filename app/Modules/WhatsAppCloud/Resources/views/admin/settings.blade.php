<x-layouts.admin :title="__('WhatsApp Cloud Settings')">
    @php
        $enabled = fn (string $key): bool => filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-950 dark:text-white">{{ __('WhatsApp Cloud Settings') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Configure Meta app defaults used by all WhatsApp Business connections.') }}</p>
        </div>

        <form method="POST" action="{{ route('admin.whatsapp-cloud.settings.update') }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                <x-forms.input
                    :label="__('Graph API Version')"
                    name="settings[whatsapp_graph_api_version]"
                    :value="$settings['whatsapp_graph_api_version']"
                    placeholder="v20.0"
                />

                <x-forms.input
                    :label="__('Meta App ID')"
                    name="settings[whatsapp_meta_app_id]"
                    :value="$settings['whatsapp_meta_app_id']"
                    placeholder="1234567890"
                />

                <x-forms.input
                    :label="__('Meta App Secret')"
                    name="settings[whatsapp_meta_app_secret]"
                    type="password"
                    :value="$settings['whatsapp_meta_app_secret']"
                    placeholder="App secret"
                />

                <x-forms.input
                    :label="__('Embedded Signup Configuration ID')"
                    name="settings[whatsapp_embedded_signup_config_id]"
                    :value="$settings['whatsapp_embedded_signup_config_id']"
                    placeholder="Meta configuration ID"
                />

                <x-forms.input
                    :label="__('Default Webhook Verify Token')"
                    name="settings[whatsapp_default_verify_token]"
                    :value="$settings['whatsapp_default_verify_token']"
                    placeholder="Optional default token"
                />

                <x-forms.input
                    :label="__('Webhook Base URL')"
                    name="settings[whatsapp_webhook_base_url]"
                    :value="$settings['whatsapp_webhook_base_url']"
                    placeholder="https://example.com"
                />
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Meta Embedded Signup') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Let users connect WhatsApp through the official Meta signup flow.') }}</p>
                        </div>
                        <x-forms.toggle name="settings[whatsapp_embedded_signup_enabled]" :checked="$enabled('whatsapp_embedded_signup_enabled')" />
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Auto-sync templates') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Refresh approved/rejected template status during Meta sync.') }}</p>
                        </div>
                        <x-forms.toggle name="settings[whatsapp_auto_sync_templates]" :checked="$enabled('whatsapp_auto_sync_templates')" />
                    </div>
                </div>

                <div class="rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Auto-sync phone numbers') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Create/update channel rows from Meta WABA phone numbers.') }}</p>
                        </div>
                        <x-forms.toggle name="settings[whatsapp_auto_sync_phone_numbers]" :checked="$enabled('whatsapp_auto_sync_phone_numbers')" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
