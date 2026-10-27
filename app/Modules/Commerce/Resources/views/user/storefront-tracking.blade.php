@php
    $trackingConfig = array_replace(\App\Modules\Commerce\Storefront\Services\TrackingSettingsService::DEFAULTS, (array) ($tracking ?? []));
@endphp
<div x-show="activeTab === 'tracking'" x-cloak class="max-w-3xl">
    <form method="POST" action="{{ route('user.commerce.storefront.tracking') }}" class="space-y-5 rounded-2xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-900">
        @csrf
        @method('PUT')
        <input type="hidden" name="_tab" value="tracking">
        <div>
            <h2 class="text-xl font-semibold">{{ __('Ads & Tracking') }}</h2>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Add your pixels to measure ads. Page views, product views, add to cart, checkout and purchases are reported from the storefront.') }}</p>
        </div>
        @if (isset($errors) && $errors->any())
            <ul role="alert" class="list-inside list-disc text-sm text-red-600">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        @endif
        <fieldset class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <legend class="px-2 font-semibold">Meta (Facebook & Instagram)</legend>
            <label class="grid gap-1 text-sm">{{ __('Meta Pixel ID') }}<input name="meta_pixel_id" value="{{ old('meta_pixel_id', $trackingConfig['meta_pixel_id']) }}" inputmode="numeric" maxlength="20" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            <input type="hidden" name="meta_capi_enabled" value="0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="meta_capi_enabled" value="1" @checked(old('meta_capi_enabled', $trackingConfig['meta_capi_enabled']))>{{ __('Also send purchases from our server (Conversions API)') }}</label>
            <label class="grid gap-1 text-sm">{{ __('Conversions API access token') }}<input type="password" name="meta_capi_token" autocomplete="off" placeholder="{{ ($trackingConfig['meta_capi_token_set'] ?? false) ? __('Saved. Leave blank to keep it.') : __('Paste the token from Events Manager') }}" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            @if ($trackingConfig['meta_capi_token_set'] ?? false)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="clear_token" value="1">{{ __('Remove the saved token') }}</label>
            @endif
            <label class="grid gap-1 text-sm">{{ __('Test event code (optional)') }}<input name="meta_test_event_code" value="{{ old('meta_test_event_code', $trackingConfig['meta_test_event_code']) }}" maxlength="30" class="rounded-lg border border-neutral-300 bg-transparent p-2"><span class="text-xs text-neutral-500">{{ __('Use while testing in Events Manager, then clear it. Test events are not counted for ads.') }}</span></label>
        </fieldset>
        <fieldset class="space-y-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <legend class="px-2 font-semibold">TikTok</legend>
            <label class="grid gap-1 text-sm">{{ __('TikTok Pixel ID') }}<input name="tiktok_pixel_id" value="{{ old('tiktok_pixel_id', $trackingConfig['tiktok_pixel_id']) }}" maxlength="30" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            <input type="hidden" name="tiktok_events_enabled" value="0">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="tiktok_events_enabled" value="1" @checked(old('tiktok_events_enabled', $trackingConfig['tiktok_events_enabled']))>{{ __('Also send purchases from our server (Events API)') }}</label>
            <label class="grid gap-1 text-sm">{{ __('Events API access token') }}<input type="password" name="tiktok_events_token" autocomplete="off" placeholder="{{ ($trackingConfig['tiktok_events_token_set'] ?? false) ? __('Saved. Leave blank to keep it.') : __('Paste the token from TikTok Events Manager') }}" class="rounded-lg border border-neutral-300 bg-transparent p-2"></label>
            @if ($trackingConfig['tiktok_events_token_set'] ?? false)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="clear_tiktok_token" value="1">{{ __('Remove the saved token') }}</label>
            @endif
            <label class="grid gap-1 text-sm">{{ __('Test event code (optional)') }}<input name="tiktok_test_event_code" value="{{ old('tiktok_test_event_code', $trackingConfig['tiktok_test_event_code']) }}" maxlength="40" class="rounded-lg border border-neutral-300 bg-transparent p-2"><span class="text-xs text-neutral-500">{{ __('Use while testing in TikTok Events Manager, then clear it.') }}</span></label>
        </fieldset>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">{{ __('Save tracking settings') }}</button>
    </form>
</div>
