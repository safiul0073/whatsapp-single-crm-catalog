<x-layouts.admin :title="__('Meta App Settings')">
    @php
        $enabled = fn (string $key): bool => filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-950 dark:text-white">{{ __('Meta App Settings') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('One Meta app powers WhatsApp, Messenger, Instagram and Ads. Each product only needs its own Facebook Login for Business configuration ID.') }}</p>
        </div>

        @if ($hasSeparateLegacyApps)
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                {{ __('WhatsApp and Messenger/Instagram currently use different Meta apps. Saving here moves new connections to the app below. Existing channels keep working, and webhooks signed by the old apps are still accepted.') }}
            </div>
        @endif

        <section class="rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950" data-meta-platform-check>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Platform checklist') }}</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __('What must be finished in Meta for Developers before customers can connect.') }}
                        @if ($platformCheck)
                            {{ __('Last checked :time.', ['time' => \Illuminate\Support\Carbon::parse($platformCheck['checked_at'])->diffForHumans()]) }}
                        @endif
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.meta-app.settings.check') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline">{{ $platformCheck ? __('Check again') : __('Run check') }}</button>
                </form>
            </div>

            @if ($platformCheck)
                <ul class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($platformCheck['items'] as $item)
                        <li class="flex gap-3 py-2.5">
                            <i class="ph {{ $item['ok'] === true ? 'ph-check-circle text-success' : ($item['ok'] === false ? 'ph-x-circle text-error' : 'ph-minus-circle text-gray-400') }} mt-0.5 text-lg"></i>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</p>
                                <p class="text-xs text-gray-500 break-words">{{ $item['detail'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <details class="mt-4 rounded-md border border-gray-100 px-3 py-2 dark:border-gray-800">
                <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">{{ __('Before going live (done in Meta, not checkable from here)') }}</summary>
                <ol class="mt-2 list-decimal space-y-1 pl-5 text-xs text-gray-600 dark:text-gray-400">
                    <li>{{ __('Switch the app to Live mode. In Development mode only people with a role on the app can connect.') }}</li>
                    <li>{{ __('Verify your own business in Business Settings → Security Center.') }}</li>
                    <li>{{ __('Become a WhatsApp Tech Provider (WhatsApp → Getting started) so customers can onboard numbers.') }}</li>
                    <li>{{ __('Request Advanced Access in App Review for each permission below, with a screen recording of how it is used.') }}</li>
                    <li>{{ __('Add the webhook callback URLs and verify token from this page in the WhatsApp, Messenger and Instagram webhook settings.') }}</li>
                </ol>
                <ul class="mt-3 grid gap-1 text-xs text-gray-600 dark:text-gray-400 sm:grid-cols-2">
                    @foreach ($platformPermissions as $permission => $use)
                        <li><code>{{ $permission }}</code> · {{ __($use) }}</li>
                    @endforeach
                </ul>
            </details>
        </section>

        <form method="POST" action="{{ route('admin.meta-app.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Meta app') }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('From Meta for Developers → your app → App settings → Basic.') }}</p>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <x-forms.input :label="__('Meta App ID')" name="meta_app_id" :value="$settings['meta_app_id']" placeholder="1234567890" />
                    <x-forms.input :label="__('Meta App Secret')" name="meta_app_secret" type="password" :value="$settings['meta_app_secret']" :placeholder="__('App secret')" />
                    <x-forms.input :label="__('Graph API Version')" name="meta_graph_api_version" :value="$settings['meta_graph_api_version']" placeholder="v24.0" required />
                    <x-forms.input :label="__('Webhook Base URL')" name="meta_webhook_base_url" :value="$settings['meta_webhook_base_url']" placeholder="https://app.example.com" :hint="__('Leave empty to use the application URL.')" />
                    <x-forms.input :label="__('Default Webhook Verify Token')" name="meta_default_verify_token" :value="$settings['meta_default_verify_token']" :placeholder="__('Optional default token')" />
                </div>
            </section>

            <section class="rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('WhatsApp') }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('Configuration created with the "WhatsApp Embedded Signup" login variation.') }}</p>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <x-forms.input :label="__('Embedded Signup Configuration ID')" name="whatsapp_embedded_signup_config_id" :value="$settings['whatsapp_embedded_signup_config_id']" :placeholder="__('Meta configuration ID')" />
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="flex items-center justify-between gap-4 rounded-md border border-gray-100 p-4 dark:border-gray-800">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Embedded Signup') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Let users connect WhatsApp in one click.') }}</p>
                        </div>
                        <x-forms.toggle name="whatsapp_embedded_signup_enabled" :checked="$enabled('whatsapp_embedded_signup_enabled')" />
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-md border border-gray-100 p-4 dark:border-gray-800">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Auto-sync templates') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Refresh template status during Meta sync.') }}</p>
                        </div>
                        <x-forms.toggle name="whatsapp_auto_sync_templates" :checked="$enabled('whatsapp_auto_sync_templates')" />
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-md border border-gray-100 p-4 dark:border-gray-800">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Auto-sync phone numbers') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Refresh number details from the WABA.') }}</p>
                        </div>
                        <x-forms.toggle name="whatsapp_auto_sync_phone_numbers" :checked="$enabled('whatsapp_auto_sync_phone_numbers')" />
                    </div>
                </div>
            </section>

            <section class="rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Messenger & Instagram') }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('Facebook Login for Business configurations with Page and Instagram messaging permissions.') }}</p>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <x-forms.input :label="__('Messenger Configuration ID')" name="meta_social_messenger_config_id" :value="$settings['meta_social_messenger_config_id']" :placeholder="__('Meta configuration ID')" />
                    <x-forms.input :label="__('Instagram Configuration ID')" name="meta_social_instagram_config_id" :value="$settings['meta_social_instagram_config_id']" :placeholder="__('Meta configuration ID')" />
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-3">
                    <div class="flex items-center justify-between gap-4 rounded-md border border-gray-100 p-4 dark:border-gray-800">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('Embedded Signup') }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ __('Let users connect Pages and Instagram in one click.') }}</p>
                        </div>
                        <x-forms.toggle name="meta_social_embedded_signup_enabled" :checked="$enabled('meta_social_embedded_signup_enabled')" />
                    </div>
                </div>
            </section>

            <section class="rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Business & Ads') }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('Optional. Used by the "Connect your Meta Business" flow and Meta Ads features.') }}</p>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <x-forms.input :label="__('Business (all assets) Configuration ID')" name="meta_business_config_id" :value="$settings['meta_business_config_id']" :placeholder="__('Meta configuration ID')" />
                    <x-forms.input :label="__('Ads Configuration ID')" name="meta_ads_config_id" :value="$settings['meta_ads_config_id']" :placeholder="__('Meta configuration ID')" />
                </div>
            </section>

            <section class="rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Threads') }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ __('From the Threads use case in your Meta app (it has its own app ID and secret). Add this redirect URI there:') }} <code class="break-all">{{ route('user.threads.callback') }}</code></p>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <x-forms.input :label="__('Threads App ID')" name="meta_threads_app_id" :value="$settings['meta_threads_app_id']" :placeholder="__('Threads app ID')" />
                    <x-forms.input :label="__('Threads App Secret')" name="meta_threads_app_secret" type="password" :value="$settings['meta_threads_app_secret']" :placeholder="__('Threads app secret')" />
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
