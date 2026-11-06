@use('App\Modules\TikTok\Models\TikTokPost')
<x-layouts.user :title="__('New TikTok video')">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('Post to TikTok') }}</h2>
            <p class="m-text mt-1">{{ __('Upload an MP4, MOV or WebM video.') }}</p>
        </div>
        <a href="{{ route('user.tiktok.posts.index') }}" class="btn-sm btn-outline"><i class="ph ph-arrow-left text-base"></i> {{ __('TikTok posts') }}</a>
    </div>

    @if (! $account)
        <section class="section-card mt-6">
            <h3 class="heading-5">{{ __('Connect a TikTok account first') }}</h3>
            <a href="{{ route('user.tiktok.index') }}" class="btn btn-primary mt-4">{{ __('Connect TikTok') }}</a>
        </section>
    @elseif (! $canDirectPost && ! $canSendDraft)
        <section class="section-card mt-6">
            <h3 class="heading-5">{{ __('Allow video posting') }}</h3>
            <p class="mt-1 text-sm text-body">{{ __('This TikTok connection was made without video permissions. Disconnect it and connect TikTok again.') }}</p>
            <a href="{{ route('user.tiktok.index') }}" class="btn btn-primary mt-4">{{ __('TikTok accounts') }}</a>
        </section>
    @else
        <form
            method="POST"
            action="{{ route('user.tiktok.posts.store') }}"
            enctype="multipart/form-data"
            class="section-card mt-6 space-y-5"
            data-tiktok-post-form
            x-data="tiktokPostForm(@js([
                'mode' => $canDirectPost && $creator ? 'direct' : 'draft',
                'maxDuration' => $creator['max_video_post_duration_sec'] ?? 0,
                'durationLabel' => __('This account can post videos up to :max seconds long.'),
                'promotionalLabel' => __('Your photo/video will be labeled as \'Promotional content\''),
                'paidPartnershipLabel' => __('Your photo/video will be labeled as \'Paid partnership\''),
            ]))"
        >
            @csrf
            <input type="hidden" name="channel_account_id" value="{{ $account->id }}">

            @if (($errors ?? null)?->any())
                <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    @if ($creator['avatar_url'] ?? null)
                        <img src="{{ $creator['avatar_url'] }}" alt="" class="h-10 w-10 rounded-full object-cover">
                    @endif
                    <div>
                        <p class="text-sm text-body">{{ __('Posting as') }}</p>
                        <p class="font-semibold text-heading" data-tiktok-creator>{{ $creator['nickname'] ?? $account->name }}</p>
                    </div>
                </div>
                @if ($accounts->count() > 1)
                    <div class="flex flex-wrap gap-2">
                        @foreach ($accounts as $other)
                            <a href="{{ route('user.tiktok.posts.create', ['account' => $other->id]) }}" class="btn-sm {{ $other->id === $account->id ? 'btn-primary' : 'btn-outline' }}">{{ $other->name }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($creatorError)
                <x-ui.alert type="error">{{ $creatorError }}</x-ui.alert>
            @endif

            <div>
                <p class="form-label">{{ __('How to send it') }}</p>
                <div class="mt-2 grid gap-3 md:grid-cols-2">
                    <label class="radio-card items-start p-3" :class="mode === 'direct' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="mode" value="direct" x-model="mode" @disabled(! $canDirectPost || ! $creator)>
                        <span><span class="block font-semibold text-heading">{{ __('Post directly') }}</span><span class="block text-xs text-body">{{ $canDirectPost ? __('Publish to the TikTok profile now.') : __('Reconnect TikTok and allow posting to use this.') }}</span></span>
                    </label>
                    <label class="radio-card items-start p-3" :class="mode === 'draft' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="mode" value="draft" x-model="mode" @disabled(! $canSendDraft)>
                        <span><span class="block font-semibold text-heading">{{ __('Send to TikTok drafts') }}</span><span class="block text-xs text-body">{{ __('Finish editing (sounds, effects) in the TikTok app, then post.') }}</span></span>
                    </label>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_260px]">
                <div class="space-y-4">
                    <div>
                        <label for="video" class="form-label">{{ __('Video') }} <span class="text-error">*</span></label>
                        <input id="video" name="video" type="file" accept="video/mp4,video/quicktime,video/webm" class="form-input" required @change="pickVideo($event)">
                        <p class="text-xs text-error mt-1" x-show="durationError" x-text="durationError" x-cloak></p>
                    </div>
                    <div>
                        <label for="title" class="form-label">{{ __('Caption') }}</label>
                        <textarea id="title" name="title" rows="3" maxlength="2200" class="form-input" placeholder="{{ __('Describe your video, add #hashtags') }}">{{ old('title') }}</textarea>
                    </div>
                    <div>
                        <label for="product_id" class="form-label">{{ __('Product shown in the video (optional, for your records)') }}</label>
                        <select id="product_id" name="product_id" class="form-input">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected((int) old('product_id', $selectedProductId) === $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <p class="form-label">{{ __('Preview') }}</p>
                    <video x-show="previewUrl" :src="previewUrl" controls playsinline class="mt-2 max-h-96 w-full rounded-md bg-black" @loadedmetadata="checkDuration($event)" x-cloak></video>
                    <p x-show="! previewUrl" class="mt-2 rounded-md border border-dashed border-neutral-300 p-6 text-center text-xs text-body">{{ __('Choose a video to preview it.') }}</p>
                </div>
            </div>

            @if ($creator)
                <div class="space-y-4 rounded-md border border-neutral-200 p-4" x-show="mode === 'direct'" x-cloak>
                    <div>
                        <label for="privacy_level" class="form-label">{{ __('Who can watch this video') }} <span class="text-error">*</span></label>
                        <select id="privacy_level" name="privacy_level" class="form-input" x-model="privacy" @change="choosePrivacy($event.target.value)">
                            <option value="" disabled>{{ __('Choose…') }}</option>
                            @foreach ($creator['privacy_level_options'] as $level)
                                <option value="{{ $level }}" @if ($level === 'SELF_ONLY') :disabled="brandContent" @endif>{{ TikTokPost::privacyLabel($level) }}</option>
                            @endforeach
                        </select>
                        <p class="form-hint" x-show="brandContent" x-cloak>{{ __('Branded content visibility cannot be set to private.') }}</p>
                    </div>

                    <div>
                        <p class="form-label">{{ __('Allow users to') }}</p>
                        <div class="mt-2 flex flex-wrap gap-4">
                            @foreach (['allow_comment' => [__('Comment'), $creator['comment_disabled']], 'allow_duet' => [__('Duet'), $creator['duet_disabled']], 'allow_stitch' => [__('Stitch'), $creator['stitch_disabled']]] as $field => [$label, $disabled])
                                <label class="flex items-center gap-2 text-sm {{ $disabled ? 'text-neutral-400' : 'text-body' }}">
                                    <input type="hidden" name="{{ $field }}" value="0">
                                    <input type="checkbox" name="{{ $field }}" value="1" class="app-checkbox" @disabled($disabled)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-heading">
                            <input type="hidden" name="disclose_commercial" value="0">
                            <input type="checkbox" name="disclose_commercial" value="1" class="app-checkbox" x-model="disclose">
                            {{ __('Disclose video content') }}
                        </label>
                        <p class="form-hint">{{ __('Turn on if this video promotes yourself, a third party, or both.') }}</p>
                        <div class="mt-3 space-y-2 pl-6" x-show="disclose" x-cloak>
                            <label class="flex items-start gap-2 text-sm text-body">
                                <input type="hidden" name="brand_organic" value="0">
                                <input type="checkbox" name="brand_organic" value="1" class="app-checkbox mt-0.5" x-model="brandOrganic">
                                <span><span class="font-semibold text-heading">{{ __('Your brand') }}</span><br>{{ __('You are promoting yourself or your own business.') }}</span>
                            </label>
                            <label class="flex items-start gap-2 text-sm text-body">
                                <input type="hidden" name="brand_content" value="0">
                                <input type="checkbox" name="brand_content" value="1" class="app-checkbox mt-0.5" x-model="brandContent" :disabled="privacy === 'SELF_ONLY'">
                                <span><span class="font-semibold text-heading">{{ __('Branded content') }}</span><br>{{ __('You are promoting another brand or a third party.') }}</span>
                            </label>
                            <p class="text-xs font-semibold text-heading" x-show="disclosureLabel" x-text="disclosureLabel"></p>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-body">
                        <input type="hidden" name="is_aigc" value="0">
                        <input type="checkbox" name="is_aigc" value="1" class="app-checkbox">
                        {{ __('This video is AI-generated') }}
                    </label>

                    <label class="flex items-start gap-2 text-sm text-body">
                        <input type="checkbox" name="consent" value="1" class="app-checkbox mt-0.5" :required="mode === 'direct'">
                        <span x-show="! brandContent">{{ __('By posting, you agree to TikTok\'s') }} <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener" class="text-primary underline">{{ __('Music Usage Confirmation') }}</a>.</span>
                        <span x-show="brandContent" x-cloak>{{ __('By posting, you agree to TikTok\'s') }} <a href="https://www.tiktok.com/legal/page/global/bc-policy/en" target="_blank" rel="noopener" class="text-primary underline">{{ __('Branded Content Policy') }}</a> {{ __('and') }} <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener" class="text-primary underline">{{ __('Music Usage Confirmation') }}</a>.</span>
                    </label>
                    <p class="text-xs text-body">{{ __('After posting, TikTok can take a few minutes to process the video before it appears on your profile.') }}</p>
                </div>
            @endif

            <p class="text-xs text-body" x-show="mode === 'draft'">{{ __('The video goes to your TikTok inbox. Open the TikTok app notification to finish editing and post it.') }}</p>

            <div class="flex justify-end">
                <span :title="disclosureMissing ? '{{ __('You need to indicate if your content promotes yourself, a third party, or both.') }}' : ''">
                    <button type="submit" class="btn btn-primary" :disabled="! canSubmit"><i class="ph ph-tiktok-logo text-base"></i> <span x-text="mode === 'direct' ? '{{ __('Post to TikTok') }}' : '{{ __('Send to TikTok drafts') }}'"></span></button>
                </span>
            </div>
        </form>
    @endif
</x-layouts.user>
