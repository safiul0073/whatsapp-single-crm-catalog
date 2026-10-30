@php($seo = (array) data_get($cfg, 'seo', []))
{{-- SEO Configuration Card --}}
<div
    class="rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5"
    x-data="{
        seoTitle: {{ Illuminate\Support\Js::from($seo['title'] ?? '') }},
        seoDescription: {{ Illuminate\Support\Js::from($seo['description'] ?? '') }},
        ogImage: {{ Illuminate\Support\Js::from($seo['og_image'] ?? '') }},
        chooseOgImage() {
            if (typeof window.openMediaLibrary !== 'function') {
                return;
            }
            window.openMediaLibrary((media) => {
                if (media && media.url) {
                    this.ogImage = media.url;
                }
            }, { type: 'image' });
        },
    }"
>
    <div class="flex items-center gap-2.5 border-b border-neutral-100 pb-4 dark:border-neutral-800">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
            <i class="ph ph-magnifying-glass text-lg"></i>
        </span>
        <div>
            <h2 class="heading-4 text-title text-base font-semibold">{{ __('SEO Configuration') }}</h2>
            <p class="text-xs text-body">{{ __('Default search engine and social sharing details. Individual pages and products can override them.') }}</p>
        </div>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3 dark:border-neutral-700 dark:bg-neutral-800/50">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-500">{{ __('Search preview') }}</p>
        <p class="mt-1 truncate text-base font-medium text-blue-700" x-text="seoTitle || form.name"></p>
        <p class="line-clamp-2 text-xs text-body" x-text="seoDescription || form.footer_text"></p>
    </div>

    <div>
        <label class="form-label" for="seo_title">{{ __('Default meta title') }}</label>
        <input id="seo_title" type="text" name="config[seo][title]" x-model="seoTitle" maxlength="70" class="form-input" placeholder="{{ __('Defaults to your store name') }}">
        <p class="mt-1 text-xs text-body"><span x-text="seoTitle.length"></span>/70 — {{ __('Used for the home page title when no page title is set.') }}</p>
    </div>

    <div>
        <label class="form-label" for="seo_description">{{ __('Default meta description') }}</label>
        <textarea id="seo_description" name="config[seo][description]" x-model="seoDescription" rows="3" maxlength="320" class="form-input" placeholder="{{ __('Defaults to your footer description') }}"></textarea>
        <p class="mt-1 text-xs text-body"><span x-text="seoDescription.length"></span>/320 — {{ __('Aim for 120–160 characters.') }}</p>
    </div>

    <div>
        <label class="form-label" for="seo_keywords">{{ __('Keywords') }}</label>
        <input id="seo_keywords" type="text" name="config[seo][keywords]" value="{{ $seo['keywords'] ?? '' }}" maxlength="300" class="form-input" placeholder="{{ __('wholesale apparel, streetwear, hoodies') }}">
    </div>

    <div>
        <label class="form-label" for="seo_og_image">{{ __('Social share image') }}</label>
        <div class="flex items-center gap-3">
            <div class="grid h-14 w-24 shrink-0 place-items-center overflow-hidden rounded-md border border-dashed border-neutral-300 bg-white text-body">
                <img x-show="ogImage" x-cloak :src="ogImage" alt="" class="h-full w-full object-cover">
                <i x-show="! ogImage" class="ph ph-image text-xl"></i>
            </div>
            <input id="seo_og_image" type="text" name="config[seo][og_image]" x-model="ogImage" class="form-input text-xs font-mono" placeholder="https://... or /storage/media/share.jpg">
            <button type="button" class="btn btn-outline rounded-sm! shrink-0" @click="chooseOgImage()">{{ __('Choose') }}</button>
        </div>
        <p class="mt-1 text-xs text-body">{{ __('Shown when your store is shared on WhatsApp, Facebook or X. Recommended 1200×630px. Falls back to the logo.') }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label class="form-label" for="seo_twitter">{{ __('X (Twitter) handle') }}</label>
            <input id="seo_twitter" type="text" name="config[seo][twitter_handle]" value="{{ $seo['twitter_handle'] ?? '' }}" maxlength="16" class="form-input" placeholder="@yourstore">
        </div>
        <div>
            <label class="form-label" for="seo_google">{{ __('Google Search Console code') }}</label>
            <input id="seo_google" type="text" name="config[seo][google_verification]" value="{{ $seo['google_verification'] ?? '' }}" maxlength="120" class="form-input font-mono text-xs" placeholder="{{ __('verification code only') }}">
        </div>
        <div>
            <label class="form-label" for="seo_bing">{{ __('Bing Webmaster code') }}</label>
            <input id="seo_bing" type="text" name="config[seo][bing_verification]" value="{{ $seo['bing_verification'] ?? '' }}" maxlength="120" class="form-input font-mono text-xs" placeholder="{{ __('verification code only') }}">
        </div>
    </div>

    <input type="hidden" name="config[seo][noindex]" value="0">
    <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
        <input type="checkbox" class="app-checkbox mt-0.5" name="config[seo][noindex]" value="1" @checked(! empty($seo['noindex']))>
        <span>
            <span class="block text-sm font-medium text-title">{{ __('Hide store from search engines') }}</span>
            <span class="block text-xs text-body">{{ __('Adds noindex and blocks crawlers in robots.txt. Use while the store is still being set up.') }}</span>
        </span>
    </label>
</div>
