<x-layouts.user :title="__('Create ad')">
    @php
        $firstPage = $context['pages'][0]['id'] ?? '';
        $firstInstagram = $context['instagram_accounts'][0]['id'] ?? '';
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('Create ad') }}</h2>
            <p class="m-text mt-1">{{ __('The ad is created paused in your Meta ad account. Nothing is spent until you press Start on My ads.') }}</p>
        </div>
        <a href="{{ route('user.meta-ads.ads.index') }}" class="btn-sm btn-outline"><i class="ph ph-arrow-left text-base"></i> {{ __('My ads') }}</a>
    </div>

    @include('meta-ads::user.partials.ads-access', ['context' => $context])

    @if ($context['can_manage'] && $context['ad_accounts'] !== [])
        <form
            method="POST"
            action="{{ route('user.meta-ads.ads.store') }}"
            class="section-card mt-6 space-y-6"
            data-meta-ad-form
            x-data="metaAdBuilder(@js([
                'kind' => old('kind', 'product'),
                'pageId' => old('page_id', $firstPage),
                'instagramId' => old('instagram_account_id', $firstInstagram),
                'postId' => old('post_id', ''),
                'contentId' => old('content_id', ''),
                'postsUrl' => route('user.meta-ads.ads.page-posts'),
                'contentUrl' => route('user.meta-ads.ads.partnership-content'),
                'permissionUrl' => route('user.meta-ads.ads.creator-permission'),
                'choosePageLabel' => __('Choose a Facebook Page first.'),
                'creatorLabel' => __('Choose your Instagram account and enter the creator\'s username.'),
                'failedLabel' => __('Meta did not answer. Try again.'),
            ]))"
        >
            @csrf

            @if (($errors ?? null)?->any())
                <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
            @endif

            <div>
                <p class="form-label">{{ __('What do you want to promote?') }}</p>
                <div class="mt-2 grid gap-3 md:grid-cols-3">
                    <label class="radio-card items-start p-3" :class="kind === 'product' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="kind" value="product" x-model="kind">
                        <span><span class="block font-semibold text-heading">{{ __('A product') }}</span><span class="block text-xs text-body">{{ __('Photo, price and a link to your store, or a button to chat.') }}</span></span>
                    </label>
                    <label class="radio-card items-start p-3" :class="kind === 'boost_post' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="kind" value="boost_post" x-model="kind">
                        <span><span class="block font-semibold text-heading">{{ __('Boost a Page post') }}</span><span class="block text-xs text-body">{{ __('Show one of your Facebook Page posts to more people.') }}</span></span>
                    </label>
                    <label class="radio-card items-start p-3" :class="kind === 'partnership' ? 'border-primary bg-primary/5' : ''" @if (! $context['can_partner']) title="{{ __('Reconnect Meta and allow branded content ads to use this.') }}" @endif>
                        <input type="radio" name="kind" value="partnership" x-model="kind" @disabled(! $context['can_partner'])>
                        <span><span class="block font-semibold text-heading">{{ __('Creator post (partnership ad)') }}</span><span class="block text-xs text-body">{{ __('Run a creator\'s post that tagged your brand as your ad.') }}</span></span>
                    </label>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="name" class="form-label">{{ __('Ad name') }} <span class="text-error">*</span></label>
                    <input id="name" name="name" type="text" maxlength="120" class="form-input" value="{{ old('name') }}" placeholder="{{ __('e.g. Eid panjabi sale') }}" required>
                </div>
                <div>
                    <label for="ad_account_id" class="form-label">{{ __('Ad account (pays for the ad)') }} <span class="text-error">*</span></label>
                    <select id="ad_account_id" name="ad_account_id" class="form-input" required>
                        @foreach ($context['ad_accounts'] as $account)
                            <option value="{{ $account['id'] }}" @selected(old('ad_account_id') === $account['id'])>{{ $account['name'] }} @if ($account['currency']) ({{ $account['currency'] }}) @endif</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="page_id" class="form-label">{{ __('Facebook Page') }} <span class="text-error">*</span></label>
                    <select id="page_id" name="page_id" class="form-input" x-model="pageId" required>
                        @foreach ($context['pages'] as $page)
                            <option value="{{ $page['id'] }}">{{ $page['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="instagram_account_id" class="form-label">{{ __('Instagram account (optional)') }}</label>
                    <select id="instagram_account_id" name="instagram_account_id" class="form-input" x-model="instagramId">
                        <option value="">{{ __('Facebook only') }}</option>
                        @foreach ($context['instagram_accounts'] as $instagram)
                            <option value="{{ $instagram['id'] }}">{{ $instagram['username'] ? '@'.$instagram['username'] : $instagram['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-4" x-show="kind === 'product'">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="product_id" class="form-label">{{ __('Product') }} <span class="text-error">*</span></label>
                        <select id="product_id" name="product_id" class="form-input">
                            @foreach ($context['products'] as $product)
                                <option value="{{ $product['id'] }}" @selected((int) old('product_id', $selectedProductId) === $product['id'])>{{ $product['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="destination" class="form-label">{{ __('When people tap the ad') }}</label>
                        <select id="destination" name="destination" class="form-input">
                            <option value="website" @selected(old('destination') === 'website')>{{ __('Open the product on your store') }}</option>
                            <option value="messenger" @selected(old('destination') === 'messenger')>{{ __('Start a Messenger chat') }}</option>
                            <option value="whatsapp" @selected(old('destination') === 'whatsapp')>{{ __('Start a WhatsApp chat') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="space-y-3" x-show="kind === 'boost_post'" x-cloak>
                <input type="hidden" name="post_id" :value="kind === 'boost_post' ? postId : ''">
                <button type="button" class="btn-sm btn-outline" @click="loadPosts()" :disabled="loading"><i class="ph ph-arrows-clockwise text-base"></i> {{ __('Load Page posts') }}</button>
                <div class="grid gap-3 md:grid-cols-3">
                    <template x-for="post in posts" :key="post.id">
                        <button type="button" class="rounded-md border p-3 text-left text-sm" :class="postId === post.id ? 'border-primary bg-primary/5' : 'border-neutral-200'" @click="postId = post.id">
                            <img x-show="post.picture" :src="post.picture" alt="" class="mb-2 h-28 w-full rounded object-cover">
                            <span class="block text-body" x-text="post.message || '{{ __('(no text)') }}'"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="space-y-3" x-show="kind === 'partnership'" x-cloak>
                <input type="hidden" name="content_id" :value="kind === 'partnership' ? contentId : ''">
                <input type="hidden" name="ad_code" :value="kind === 'partnership' ? adCode : ''">
                <input type="hidden" name="platform" :value="kind === 'partnership' ? platform : ''">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="min-w-48 flex-1">
                        <label for="partnership_search" class="form-label">{{ __('Search creator posts') }}</label>
                        <input id="partnership_search" type="text" class="form-input" x-model="search" placeholder="{{ __('Caption or creator') }}">
                    </div>
                    <button type="button" class="btn-sm btn-outline" @click="loadContent()" :disabled="loading"><i class="ph ph-magnifying-glass text-base"></i> {{ __('Find creator posts') }}</button>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <template x-for="item in content" :key="item.content_id">
                        <button type="button" class="rounded-md border p-3 text-left text-sm" :class="contentId === item.content_id ? 'border-primary bg-primary/5' : 'border-neutral-200'" @click="pickContent(item)">
                            <span class="block font-semibold text-heading" x-text="(item.creator || '') + ' · ' + item.platform"></span>
                            <span class="mt-1 block text-body" x-text="item.caption"></span>
                            <span class="mt-1 block text-xs text-warning" x-show="item.errors.length" x-text="item.errors.join(', ')"></span>
                        </button>
                    </template>
                </div>
                <div class="rounded-md border border-neutral-200 p-3">
                    <p class="text-sm font-semibold text-heading">{{ __('No posts yet? Ask a creator for permission') }}</p>
                    <div class="mt-2 flex flex-wrap items-end gap-2">
                        <input type="text" class="form-input min-w-48 flex-1" x-model="creatorUsername" placeholder="{{ __('@creator_username') }}" aria-label="{{ __('Creator Instagram username') }}">
                        <button type="button" class="btn-sm btn-outline" @click="askCreator()">{{ __('Send request') }}</button>
                    </div>
                </div>
            </div>

            <p class="text-sm text-error" x-show="error" x-text="error" x-cloak></p>
            <p class="text-sm text-success" x-show="notice" x-text="notice" x-cloak></p>

            <div x-show="kind !== 'boost_post'">
                <label for="message" class="form-label">{{ __('Ad text') }}</label>
                <textarea id="message" name="message" rows="3" maxlength="2000" class="form-input" placeholder="{{ __('e.g. New Eid collection — free delivery in Dhaka this week!') }}">{{ old('message') }}</textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="daily_budget" class="form-label">{{ __('Daily budget') }} <span class="text-error">*</span></label>
                    <input id="daily_budget" name="daily_budget" type="number" min="1" step="0.01" class="form-input" value="{{ old('daily_budget', '5') }}" required>
                    <p class="form-hint">{{ __('In your ad account currency.') }}</p>
                </div>
                <div>
                    <label for="starts_at" class="form-label">{{ __('Start date') }}</label>
                    <input id="starts_at" name="starts_at" type="date" class="form-input" value="{{ old('starts_at') }}">
                </div>
                <div>
                    <label for="ends_at" class="form-label">{{ __('End date') }}</label>
                    <input id="ends_at" name="ends_at" type="date" class="form-input" value="{{ old('ends_at') }}">
                </div>
                <div>
                    <label for="countries" class="form-label">{{ __('Countries') }} <span class="text-error">*</span></label>
                    <input id="countries" name="countries" type="text" class="form-input" value="{{ old('countries', 'BD') }}" placeholder="BD, IN" required>
                    <p class="form-hint">{{ __('Two-letter country codes, separated by commas.') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="age_min" class="form-label">{{ __('Age from') }}</label>
                        <input id="age_min" name="age_min" type="number" min="18" max="65" class="form-input" value="{{ old('age_min', 18) }}">
                    </div>
                    <div>
                        <label for="age_max" class="form-label">{{ __('to') }}</label>
                        <input id="age_max" name="age_max" type="number" min="18" max="65" class="form-input" value="{{ old('age_max', 65) }}">
                    </div>
                </div>
                <div>
                    <label for="gender" class="form-label">{{ __('People') }}</label>
                    <select id="gender" name="gender" class="form-input">
                        <option value="all">{{ __('Everyone') }}</option>
                        <option value="women" @selected(old('gender') === 'women')>{{ __('Women') }}</option>
                        <option value="men" @selected(old('gender') === 'men')>{{ __('Men') }}</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary"><i class="ph ph-megaphone text-base"></i> {{ __('Create paused ad') }}</button>
            </div>
        </form>
    @endif
</x-layouts.user>
