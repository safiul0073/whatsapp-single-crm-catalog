<x-layouts.user :title="__('New post')">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('New post') }}</h2>
            <p class="m-text mt-1">{{ __('Pick a product, let AI write the post and make a photo, check the preview, then publish.') }}</p>
        </div>
        <a href="{{ route('user.meta-social.posts.index') }}" class="btn-sm btn-outline"><i class="ph ph-arrow-left text-base"></i> {{ __('All posts') }}</a>
    </div>

    <form method="POST" action="{{ route('user.meta-social.posts.store') }}" class="meta-composer mt-6" data-meta-post-form
        x-data="metaPostComposer"
        data-assets-url="{{ route('user.meta-social.posts.product-assets', ['product' => '__ID__']) }}"
        data-caption-url="{{ route('user.meta-social.posts.ai-caption') }}"
        data-image-url="{{ route('user.meta-social.posts.ai-image') }}"
        data-product-id="{{ old('product_id', $selectedProductId) }}"
        data-media-id="{{ old('media_id') }}"
        data-image-preview="{{ old('image_url') }}"
        data-instagram="{{ in_array('instagram', (array) old('targets', []), true) ? '1' : '0' }}"
        data-schedule="{{ old('scheduled_at') ? '1' : '0' }}"
        data-error-label="{{ __('Something went wrong. Please try again.') }}">
        @csrf
        <input type="hidden" name="media_id" :value="mediaId">
        <input type="hidden" name="image_url" :value="mediaId ? '' : imageUrl">

        @if (($errors ?? null)?->any())
            <x-ui.alert type="error" class="mb-5">{{ $errors->first() }}</x-ui.alert>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-5">
                {{-- 1. Where --}}
                <section class="section-card space-y-4">
                    <h3 class="meta-composer-step"><span>1</span> {{ __('Where to post') }}</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="page_id" class="form-label">{{ __('Facebook Page') }} <span class="text-error">*</span></label>
                            <select id="page_id" name="page_id" class="form-input" required x-ref="page" x-on:change="pageName = $event.target.selectedOptions[0].text">
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
                                    <i class="ph ph-facebook-logo text-base"></i> {{ __('Facebook Page') }}
                                </label>
                                <label class="flex items-center gap-2 text-sm text-body">
                                    <input type="checkbox" name="targets[]" value="instagram" class="app-checkbox" x-model="instagram" @disabled($context['instagram_accounts'] === [])>
                                    <i class="ph ph-instagram-logo text-base"></i> {{ __('Instagram') }}
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
                </section>

                {{-- 2. Product --}}
                <section class="section-card space-y-4">
                    <h3 class="meta-composer-step"><span>2</span> {{ __('Product') }} <small>{{ __('optional') }}</small></h3>
                    <select id="product_id" name="product_id" class="form-input" x-model="productId" x-on:change="loadProduct()">
                        <option value="">{{ __('No product') }}</option>
                        @foreach ($context['products'] as $product)
                            <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="form-hint">{{ __('We fill in the photos, price and shop link for you.') }}</p>

                    <div x-show="loadingProduct" x-cloak class="m-text"><i class="ph ph-spinner animate-spin"></i> {{ __('Loading product…') }}</div>
                    <template x-if="product">
                        <div class="meta-composer-product">
                            <p class="font-medium text-heading" x-text="product.name"></p>
                            <template x-for="line in product.prices" :key="line"><p class="m-text" x-text="line"></p></template>
                        </div>
                    </template>
                </section>

                {{-- 3. Text --}}
                <section class="section-card space-y-4">
                    <h3 class="meta-composer-step"><span>3</span> {{ __('Post text') }}</h3>

                    <div class="meta-composer-ai">
                        <p class="meta-composer-ai-title"><i class="ph ph-sparkle"></i> {{ __('Write with AI') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <select class="form-input" x-model="tone" aria-label="{{ __('Tone') }}">
                                <option value="friendly">{{ __('Friendly') }}</option>
                                <option value="promotional">{{ __('Promotional / Sale') }}</option>
                                <option value="luxury">{{ __('Luxury') }}</option>
                                <option value="short">{{ __('Short & punchy') }}</option>
                            </select>
                            <select class="form-input" x-model="language" aria-label="{{ __('Language') }}">
                                <option value="en">English</option>
                                <option value="bn">বাংলা</option>
                            </select>
                        </div>
                        <input type="text" class="form-input" x-model="notes" maxlength="500" placeholder="{{ __('Extra notes for AI, e.g. "Eid offer, free delivery in Dhaka"') }}">
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="flex items-center gap-2 text-sm text-body"><input type="checkbox" class="app-checkbox" x-model="emojis"> {{ __('Emojis') }}</label>
                            <label class="flex items-center gap-2 text-sm text-body"><input type="checkbox" class="app-checkbox" x-model="hashtags"> {{ __('Hashtags') }}</label>
                            <button type="button" class="btn-sm btn-primary ms-auto" x-on:click="generateCaption()" :disabled="captionLoading || (!productId && !notes.trim())">
                                <i class="ph" :class="captionLoading ? 'ph-spinner animate-spin' : 'ph-sparkle'"></i>
                                <span x-text="message ? '{{ __('Rewrite') }}' : '{{ __('Generate text') }}'"></span>
                            </button>
                        </div>
                        <p class="form-error" x-show="captionError" x-text="captionError" x-cloak></p>
                    </div>

                    <div>
                        <textarea id="message" name="message" rows="7" maxlength="5000" class="form-input" x-model="message" placeholder="{{ __('What do you want to tell your followers?') }}">{{ old('message') }}</textarea>
                        <p class="form-hint text-end"><span x-text="message.length"></span>/5000</p>
                    </div>

                    <div>
                        <label for="link" class="form-label">{{ __('Link (optional)') }}</label>
                        <input id="link" name="link" type="url" class="form-input" x-model="link" value="{{ old('link') }}" placeholder="https://">
                    </div>
                </section>

                {{-- 4. Photo --}}
                <section class="section-card space-y-4">
                    <h3 class="meta-composer-step"><span>4</span> {{ __('Photo') }}</h3>
                    <div class="meta-composer-tabs" role="tablist">
                        <button type="button" role="tab" :class="{ 'is-active': tab === 'product' }" x-on:click="tab = 'product'"><i class="ph ph-t-shirt"></i> {{ __('Product photos') }}</button>
                        <button type="button" role="tab" :class="{ 'is-active': tab === 'library' }" x-on:click="tab = 'library'"><i class="ph ph-images"></i> {{ __('Media library') }}</button>
                        <button type="button" role="tab" :class="{ 'is-active': tab === 'ai' }" x-on:click="tab = 'ai'"><i class="ph ph-sparkle"></i> {{ __('AI image') }}</button>
                    </div>

                    <div x-show="tab === 'product'">
                        <p class="m-text" x-show="!product" x-cloak>{{ __('Choose a product above to see its photos.') }}</p>
                        <p class="m-text" x-show="product && product.images.length === 0" x-cloak>{{ __('This product has no photos yet.') }}</p>
                        <div class="meta-composer-thumbs">
                            <template x-for="image in (product ? product.images : [])" :key="image.media_id">
                                <button type="button" class="meta-composer-thumb" :class="{ 'is-selected': mediaId == image.media_id }" x-on:click="pickImage(image.media_id, image.url)">
                                    <img :src="image.url" alt="">
                                    <i class="ph-fill ph-check-circle"></i>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="tab === 'library'" x-cloak x-on:media-picker:selected="pickImage($event.detail.media.id, $event.detail.media.url)">
                        <x-media.picker name="library_media_id" :label="''" accept="image" />
                    </div>

                    <div x-show="tab === 'ai'" x-cloak class="space-y-3">
                        <textarea class="form-input" rows="3" maxlength="1000" x-model="imagePrompt" placeholder="{{ __('Describe the photo, e.g. "Model wearing this panjabi at an Eid evening, warm lights"') }}"></textarea>
                        <div class="flex flex-wrap items-center gap-3">
                            <select class="form-input w-auto" x-model="imageStyle" aria-label="{{ __('Style') }}">
                                <option value="studio">{{ __('Studio product shot') }}</option>
                                <option value="lifestyle">{{ __('Lifestyle') }}</option>
                                <option value="sale">{{ __('Sale banner') }}</option>
                            </select>
                            <button type="button" class="btn-sm btn-primary ms-auto" x-on:click="generateImage()" :disabled="imageLoading || imagePrompt.trim().length < 3">
                                <i class="ph" :class="imageLoading ? 'ph-spinner animate-spin' : 'ph-magic-wand'"></i>
                                <span x-text="imageLoading ? '{{ __('Creating… (up to a minute)') }}' : '{{ __('Generate image') }}'"></span>
                            </button>
                        </div>
                        <p class="form-error" x-show="imageError" x-text="imageError" x-cloak></p>
                        <div class="meta-composer-thumbs">
                            <template x-for="image in aiImages" :key="image.media_id">
                                <button type="button" class="meta-composer-thumb" :class="{ 'is-selected': mediaId == image.media_id }" x-on:click="pickImage(image.media_id, image.url)">
                                    <img :src="image.url" alt="">
                                    <i class="ph-fill ph-check-circle"></i>
                                </button>
                            </template>
                        </div>
                        <p class="form-hint">{{ __('AI images are saved to your Media library.') }}</p>
                    </div>

                    <button type="button" class="btn-sm btn-outline" x-show="imageUrl" x-cloak x-on:click="pickImage('', '')"><i class="ph ph-x"></i> {{ __('Remove photo') }}</button>
                </section>

                {{-- 5. When --}}
                <section class="section-card space-y-4">
                    <h3 class="meta-composer-step"><span>5</span> {{ __('When') }}</h3>
                    <div class="meta-composer-tabs">
                        <button type="button" :class="{ 'is-active': !schedule }" x-on:click="schedule = false"><i class="ph ph-lightning"></i> {{ __('Post now') }}</button>
                        <button type="button" :class="{ 'is-active': schedule }" x-on:click="schedule = true"><i class="ph ph-calendar"></i> {{ __('Schedule') }}</button>
                    </div>
                    <template x-if="schedule">
                        <input id="scheduled_at" name="scheduled_at" type="datetime-local" class="form-input" value="{{ old('scheduled_at') }}" required>
                    </template>
                </section>
            </div>

            {{-- Preview --}}
            <aside class="xl:sticky xl:top-24 xl:self-start space-y-4">
                <div class="meta-composer-preview">
                    <div class="meta-composer-preview-head">
                        <span class="meta-composer-avatar" x-text="(pageName || 'P').charAt(0)"></span>
                        <div>
                            <p class="font-semibold text-heading" x-text="pageName"></p>
                            <p class="text-xs text-muted" x-text="schedule ? '{{ __('Scheduled') }}' : '{{ __('Just now') }}'"></p>
                        </div>
                    </div>
                    <p class="meta-composer-preview-text" x-text="message || '{{ __('Your post text will appear here.') }}'" :class="{ 'text-muted': !message }"></p>
                    <template x-if="imageUrl"><img :src="imageUrl" alt="" class="meta-composer-preview-image"></template>
                    <template x-if="!imageUrl"><div class="meta-composer-preview-empty"><i class="ph ph-image"></i> {{ __('No photo') }}</div></template>
                    <p class="meta-composer-preview-link" x-show="link" x-cloak x-text="link"></p>
                    <div class="meta-composer-preview-actions"><span><i class="ph ph-thumbs-up"></i> {{ __('Like') }}</span><span><i class="ph ph-chat-circle"></i> {{ __('Comment') }}</span><span><i class="ph ph-share-fat"></i> {{ __('Share') }}</span></div>
                </div>
                <p class="form-error" x-show="instagram && !imageUrl" x-cloak>{{ __('Add a photo to post on Instagram.') }}</p>
                <button type="submit" class="btn btn-primary w-full justify-center" :disabled="(!message.trim() && !productId) || (instagram && !imageUrl)">
                    <i class="ph" :class="schedule ? 'ph-calendar-check' : 'ph-paper-plane-tilt'"></i>
                    <span x-text="schedule ? '{{ __('Schedule post') }}' : '{{ __('Publish now') }}'"></span>
                </button>
            </aside>
        </div>
    </form>
</x-layouts.user>
