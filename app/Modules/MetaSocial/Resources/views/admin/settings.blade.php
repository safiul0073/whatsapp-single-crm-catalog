<x-layouts.admin :title="__('Meta Social Settings')">
    @php
        $enabled = filter_var($settings['meta_social_embedded_signup_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-950 dark:text-white">{{ __('Meta Social Settings') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Configure the shared Meta app used for Messenger and Instagram channels.') }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.meta-social.settings.update') }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Graph API Version') }}</span>
                    <input class="form-input" name="meta_social_graph_api_version" value="{{ old('meta_social_graph_api_version', $settings['meta_social_graph_api_version']) }}" placeholder="v20.0">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Webhook Base URL') }}</span>
                    <input class="form-input" name="meta_social_webhook_base_url" value="{{ old('meta_social_webhook_base_url', $settings['meta_social_webhook_base_url']) }}" placeholder="https://example.com">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Meta App ID') }}</span>
                    <input class="form-input" name="meta_social_app_id" value="{{ old('meta_social_app_id', $settings['meta_social_app_id']) }}" placeholder="1234567890">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Meta App Secret') }}</span>
                    <input class="form-input" name="meta_social_app_secret" type="password" value="{{ old('meta_social_app_secret', $settings['meta_social_app_secret']) }}" placeholder="App secret">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Messenger Configuration ID') }}</span>
                    <input class="form-input" name="meta_social_messenger_config_id" value="{{ old('meta_social_messenger_config_id', $settings['meta_social_messenger_config_id']) }}" placeholder="Messenger embedded signup config">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Instagram Configuration ID') }}</span>
                    <input class="form-input" name="meta_social_instagram_config_id" value="{{ old('meta_social_instagram_config_id', $settings['meta_social_instagram_config_id']) }}" placeholder="Instagram embedded signup config">
                </label>

                <label class="block md:col-span-2">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Default Webhook Verify Token') }}</span>
                    <input class="form-input" name="meta_social_default_verify_token" value="{{ old('meta_social_default_verify_token', $settings['meta_social_default_verify_token']) }}" placeholder="Optional default token">
                </label>
            </div>

            <div class="mt-6 rounded-xl border border-gray-100 p-4 dark:border-gray-800">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Meta Embedded Signup') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Enable official Messenger and Instagram account connection flows for users.') }}</p>
                    </div>
                    <x-forms.toggle name="meta_social_embedded_signup_enabled" :checked="$enabled" />
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
