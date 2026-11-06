<x-layouts.user :title="__('New post')">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('New post') }}</h2>
            <p class="m-text mt-1">{{ __('Publish to your Facebook Page and Instagram. Leave the time empty to post now.') }}</p>
        </div>
        <a href="{{ route('user.meta-social.posts.index') }}" class="btn-sm btn-outline"><i class="ph ph-arrow-left text-base"></i> {{ __('All posts') }}</a>
    </div>

    <form method="POST" action="{{ route('user.meta-social.posts.store') }}" class="section-card mt-6 space-y-5" data-meta-post-form x-data="{ instagram: {{ in_array('instagram', (array) old('targets', []), true) ? 'true' : 'false' }} }">
        @csrf

        @if (($errors ?? null)?->any())
            <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="page_id" class="form-label">{{ __('Facebook Page') }} <span class="text-error">*</span></label>
                <select id="page_id" name="page_id" class="form-input" required>
                    @foreach ($context['pages'] as $page)
                        <option value="{{ $page['id'] }}" @selected(old('page_id') === $page['id'])>{{ $page['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <p class="form-label">{{ __('Post to') }} <span class="text-error">*</span></p>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm text-body">
                        <input type="checkbox" name="targets[]" value="facebook" class="app-checkbox" @checked(in_array('facebook', (array) old('targets', ['facebook']), true))>
                        {{ __('Facebook Page') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-body">
                        <input type="checkbox" name="targets[]" value="instagram" class="app-checkbox" x-model="instagram" @checked(in_array('instagram', (array) old('targets', []), true)) @disabled($context['instagram_accounts'] === [])>
                        {{ __('Instagram') }}
                    </label>
                </div>
            </div>
            <div x-show="instagram" x-cloak>
                <label for="instagram_account_id" class="form-label">{{ __('Instagram account') }}</label>
                <select id="instagram_account_id" name="instagram_account_id" class="form-input">
                    @foreach ($context['instagram_accounts'] as $instagram)
                        <option value="{{ $instagram['id'] }}" @selected(old('instagram_account_id') === $instagram['id'])>{{ $instagram['name'] }}</option>
                    @endforeach
                </select>
                <p class="form-hint">{{ __('Instagram posts need a photo.') }}</p>
            </div>
        </div>

        <div>
            <label for="product_id" class="form-label">{{ __('Product (optional)') }}</label>
            <select id="product_id" name="product_id" class="form-input">
                <option value="">{{ __('No product') }}</option>
                @foreach ($context['products'] as $product)
                    <option value="{{ $product['id'] }}" @selected((int) old('product_id', $selectedProductId) === $product['id'])>{{ $product['name'] }}</option>
                @endforeach
            </select>
            <p class="form-hint">{{ __('Adds the product photo, price and a link to it on your store.') }}</p>
        </div>

        <div>
            <label for="message" class="form-label">{{ __('Post text') }}</label>
            <textarea id="message" name="message" rows="5" maxlength="5000" class="form-input" placeholder="{{ __('What do you want to tell your followers?') }}">{{ old('message') }}</textarea>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="link" class="form-label">{{ __('Link (optional)') }}</label>
                <input id="link" name="link" type="url" class="form-input" value="{{ old('link') }}" placeholder="https://">
            </div>
            <div>
                <label for="scheduled_at" class="form-label">{{ __('Post at (optional)') }}</label>
                <input id="scheduled_at" name="scheduled_at" type="datetime-local" class="form-input" value="{{ old('scheduled_at') }}">
                <p class="form-hint">{{ __('Leave empty to post now.') }}</p>
            </div>
        </div>

        <x-media.picker name="media_id" :value="old('media_id')" :label="__('Photo (optional)')" accept="image" />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary"><i class="ph ph-paper-plane-tilt text-base"></i> {{ __('Publish') }}</button>
        </div>
    </form>
</x-layouts.user>
