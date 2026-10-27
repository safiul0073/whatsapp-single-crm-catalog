<x-layouts.admin :title="__('TikTok Settings')">
    @php
        $loginOn = filter_var(old('tiktok_login_enabled', $settings['tiktok_login_enabled']), FILTER_VALIDATE_BOOLEAN);
        $shopOn = filter_var(old('tiktok_shop_enabled', $settings['tiktok_shop_enabled']), FILTER_VALIDATE_BOOLEAN);
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-950 dark:text-white">{{ __('TikTok Settings') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Set up the platform TikTok apps once. Workspace owners then connect their own TikTok accounts from their dashboard.') }}</p>
        </div>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.tiktok.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('TikTok Login') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Lets workspace owners connect their TikTok account. Uses your TikTok developer app.') }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="tiktok_login_enabled" value="1" @checked($loginOn)>
                        {{ __('Enabled') }}
                    </label>
                </div>

                <div class="mt-5 grid gap-5">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Client key') }}</span>
                        <input class="form-input" name="tiktok_client_key" value="{{ old('tiktok_client_key', $settings['tiktok_client_key']) }}">
                        @error('tiktok_client_key')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Client secret') }}</span>
                        <input class="form-input" name="tiktok_client_secret" type="password" autocomplete="off" placeholder="{{ $secretSaved ? __('Saved. Leave blank to keep it.') : __('Client secret') }}">
                        @error('tiktok_client_secret')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Scopes') }}</span>
                        <input class="form-input" name="tiktok_scopes" value="{{ old('tiktok_scopes', $settings['tiktok_scopes']) }}" required>
                        <span class="mt-1 block text-xs text-gray-500">{{ __('Only request scopes your TikTok app is approved for.') }}</span>
                        @error('tiktok_scopes')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <div class="rounded-md border border-gray-100 p-4 text-sm dark:border-gray-800">
                        <p class="font-medium text-gray-700 dark:text-gray-300">{{ __('Redirect URI to register in the TikTok developer portal') }}</p>
                        <p class="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-400">{{ $redirectUri }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-md border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('TikTok Shop') }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Needs a TikTok Shop Partner Center app. Save the credentials here once TikTok approves it; shop sync is not available yet.') }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="tiktok_shop_enabled" value="1" @checked($shopOn)>
                        {{ __('Enabled') }}
                    </label>
                </div>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('App key') }}</span>
                        <input class="form-input" name="tiktok_shop_app_key" value="{{ old('tiktok_shop_app_key', $settings['tiktok_shop_app_key']) }}">
                        @error('tiktok_shop_app_key')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('App secret') }}</span>
                        <input class="form-input" name="tiktok_shop_app_secret" type="password" autocomplete="off" placeholder="{{ $shopSecretSaved ? __('Saved. Leave blank to keep it.') : __('App secret') }}">
                        @error('tiktok_shop_app_secret')<span class="mt-1 block text-sm text-red-600">{{ $message }}</span>@enderror
                    </label>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="btn-sm btn-primary">{{ __('Save settings') }}</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
