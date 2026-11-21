<x-layouts.user :title="__('Live Settings')">
    @php
        $liveCfg = (array) ($liveSettings ?? []);
        $fbCfg = (array) ($liveCfg['facebook'] ?? []);
        $fbReadiness = (array) ($fbCfg['readiness'] ?? ['ready' => false, 'status' => 'unconfigured', 'message' => 'Not configured']);
        $defaultPlatforms = (array) ($liveCfg['default_platforms'] ?? ['storefront', 'facebook']);
        $fbPages = (array) ($fbCfg['pages'] ?? []);
        $fbSignup = (array) ($facebookSignup ?? []);
        $canConnectFacebook = ($fbSignup['enabled'] ?? false) && (auth()->user()?->can('meta-social.manage') ?? false);
        $fbLoginText = [
            'data-opening-text' => __('Opening Facebook… A login window will appear.'),
            'data-waiting-text' => __('Waiting for you in the Facebook window. Select your Page and keep every permission checked.'),
            'data-cancelled-text' => __('The Facebook window was closed before finishing. Nothing was saved.'),
            'data-popup-blocked-text' => __('Your browser blocked the Facebook window. Allow pop-ups for this site and try again.'),
            'data-sdk-error-text' => __('Could not load Facebook login. Check your connection or turn off ad blockers for this site.'),
            'data-finishing-text' => __('Saving your Facebook connection…'),
        ];
    @endphp

    <div class="mx-auto w-full max-w-5xl space-y-6 pb-12">
        @include('commerce::user.live.partials.header', [
            'active' => 'settings',
            'title' => __('Live Settings'),
            'subtitle' => __('Control storefront live streaming, WebRTC relay servers, Hero Slider automation, and Facebook Live simulcast.'),
            'liveSettings' => $liveCfg,
            'storeUrl' => $storeUrl ?? null,
        ])

        <form method="POST" action="{{ route('user.commerce.storefront.live.settings') }}" class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="_return" value="live-settings">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                        <i class="ph ph-broadcast text-xl"></i>
                    </span>
                    <div>
                        <h2 class="heading-4 text-title text-base font-semibold">{{ __('Live Shopping, WebRTC & Slider Settings') }}</h2>
                        <p class="text-xs text-body">{{ __('Control mobile-to-storefront WebRTC streaming, Laravel Reverb real-time chat, and video slider behavior.') }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="flex items-start gap-3 rounded-xl border border-neutral-200 p-3.5 cursor-pointer hover:border-primary/40 dark:border-neutral-800">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['enabled'] ?? true)>
                    <span class="space-y-0.5">
                        <span class="block text-xs font-semibold text-title">{{ __('Enable Live Shopping & Video Hub') }}</span>
                        <span class="block text-[11px] text-body">{{ __('Activates /live page on Storefront and Customer Mobile App.') }}</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-xl border border-neutral-200 p-3.5 cursor-pointer hover:border-primary/40 dark:border-neutral-800">
                    <input type="hidden" name="show_header_live_badge" value="0">
                    <input type="checkbox" name="show_header_live_badge" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['show_header_live_badge'] ?? true)>
                    <span class="space-y-0.5">
                        <span class="block text-xs font-semibold text-title">{{ __('Show Header "LIVE NOW" Indicator') }}</span>
                        <span class="block text-[11px] text-body">{{ __('Displays a pulsing live badge in Storefront navigation during broadcasts.') }}</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-xl border border-neutral-200 p-3.5 cursor-pointer hover:border-primary/40 dark:border-neutral-800">
                    <input type="hidden" name="auto_save_recording" value="0">
                    <input type="checkbox" name="auto_save_recording" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['auto_save_recording'] ?? true)>
                    <span class="space-y-0.5">
                        <span class="block text-xs font-semibold text-title">{{ __('Save Full Live to Public Storage') }}</span>
                        <span class="block text-[11px] text-body">{{ __('Stores recorded MP4 files in public/storage/commerce/lives for VOD & sliders.') }}</span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-xl border border-neutral-200 p-3.5 cursor-pointer hover:border-primary/40 dark:border-neutral-800">
                    <input type="hidden" name="auto_publish_to_slider" value="0">
                    <input type="checkbox" name="auto_publish_to_slider" value="1" class="form-checkbox mt-0.5 rounded text-primary" @checked($liveCfg['auto_publish_to_slider'] ?? false)>
                    <span class="space-y-0.5">
                        <span class="block text-xs font-semibold text-title">{{ __('Auto-Add Recorded Video to Hero Slider') }}</span>
                        <span class="block text-[11px] text-body">{{ __('Automatically promotes completed live recordings into the Storefront Hero Slider.') }}</span>
                    </span>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label text-xs font-semibold">{{ __('Default Broadcast Targets (Owner Mobile App)') }}</label>
                    <div class="mt-1.5 flex flex-wrap gap-3">
                        <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-xs font-medium dark:border-neutral-700">
                            <input type="checkbox" name="default_platforms[]" value="storefront" class="form-checkbox rounded text-primary" @checked(in_array('storefront', $defaultPlatforms, true))>
                            <span>{{ __('Storefront & Customer App') }}</span>
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-xs font-medium dark:border-neutral-700">
                            <input type="checkbox" name="default_platforms[]" value="facebook" class="form-checkbox rounded text-primary" @checked(in_array('facebook', $defaultPlatforms, true))>
                            <span>{{ __('Facebook Live (Simulcast)') }}</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="live_default_slider_id" class="form-label text-xs font-semibold">{{ __('Target Hero Slider for Saved Videos') }}</label>
                    <select id="live_default_slider_id" name="default_slider_id" class="form-input mt-1 w-full rounded-xl py-2.5 text-xs">
                        <option value="">{{ __('First Available Storefront Hero Slider') }}</option>
                        @foreach($sliders as $sl)
                            <option value="{{ $sl->id }}" @selected(($liveCfg['default_slider_id'] ?? null) == $sl->id)>{{ $sl->name }} (#{{ $sl->id }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2 border-t border-neutral-100 dark:border-neutral-800">
                <div>
                    <label for="live_stun_url" class="form-label text-xs font-semibold">{{ __('WebRTC STUN Server URL') }}</label>
                    <input id="live_stun_url" type="text" name="stun_url" value="{{ $liveCfg['stun_url'] ?? 'stun:stun.l.google.com:19302' }}" class="form-input mt-1 w-full rounded-xl text-xs font-mono" placeholder="stun:stun.l.google.com:19302">
                </div>
                <div>
                    <label for="live_turn_url" class="form-label text-xs font-semibold">{{ __('Optional TURN Relay Server URL') }}</label>
                    <input id="live_turn_url" type="text" name="turn_url" value="{{ $liveCfg['turn_url'] ?? '' }}" class="form-input mt-1 w-full rounded-xl text-xs font-mono" placeholder="turn:turn.example.com:3478">
                </div>
                <div>
                    <label for="live_turn_username" class="form-label text-xs font-semibold">{{ __('TURN Username') }}</label>
                    <input id="live_turn_username" type="text" name="turn_username" value="{{ $liveCfg['turn_username'] ?? '' }}" class="form-input mt-1 w-full rounded-xl text-xs">
                </div>
                <div>
                    <label for="live_turn_credential" class="form-label text-xs font-semibold">{{ __('TURN Credential / Password') }}</label>
                    <input id="live_turn_credential" type="password" name="turn_credential" value="{{ $liveCfg['turn_credential'] ?? '' }}" class="form-input mt-1 w-full rounded-xl text-xs">
                </div>
            </div>

            {{-- Facebook Live Simultaneous Streaming Configuration --}}
            <div class="rounded-xl border border-blue-200/70 bg-blue-50/40 p-4 space-y-4 dark:border-blue-900/50 dark:bg-blue-950/20">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <i class="ph ph-facebook-logo text-xl text-blue-600 dark:text-blue-400"></i>
                        <div>
                            <h3 class="text-xs font-bold text-title">{{ __('Facebook Live Simultaneous Broadcast (Simulcast)') }}</h3>
                            <p class="text-[11px] text-body">{{ __('Stream to Facebook Live and Storefront simultaneously from a single mobile camera when Facebook settings are up to date.') }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ ($fbReadiness['ready'] ?? false) ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                        <span class="size-1.5 rounded-full {{ ($fbReadiness['ready'] ?? false) ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        {{ ($fbReadiness['ready'] ?? false) ? __('Ready: ') . ($fbReadiness['page_name'] ?? 'Connected') : ($fbReadiness['message'] ?? __('Needs Setup')) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-2.5 text-xs font-medium text-title cursor-pointer">
                        <input type="hidden" name="facebook[enabled]" value="0">
                        <input type="checkbox" name="facebook[enabled]" value="1" class="form-checkbox rounded text-blue-600" @checked($fbCfg['enabled'] ?? false)>
                        <span>{{ __('Enable Facebook Live Integration') }}</span>
                    </label>

                    <label class="flex items-center gap-2.5 text-xs font-medium text-title cursor-pointer">
                        <input type="hidden" name="facebook[sync_facebook_comments]" value="0">
                        <input type="checkbox" name="facebook[sync_facebook_comments]" value="1" class="form-checkbox rounded text-blue-600" @checked($fbCfg['sync_facebook_comments'] ?? true)>
                        <span>{{ __('Sync Facebook Live Comments into Unified Reverb Chat') }}</span>
                    </label>

                    <label class="flex items-center gap-2.5 text-xs font-medium text-title cursor-pointer">
                        <input type="hidden" name="allow_guest_chat" value="0">
                        <input type="checkbox" name="allow_guest_chat" value="1" class="form-checkbox rounded text-primary" @checked($liveCfg['allow_guest_chat'] ?? true)>
                        <span>{{ __('Allow Guest Viewers to Comment on Storefront Live') }}</span>
                    </label>
                </div>

                <div class="rounded-lg border border-blue-200/70 bg-white p-3 dark:border-blue-900/50 dark:bg-neutral-900" data-facebook-live-connect>
                    @if ($fbPages === [])
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-title">{{ __('Connect your Facebook Page') }}</p>
                                <p class="text-[11px] text-body" data-meta-connect-status="business">{{ __('Log in with Facebook, pick your Page and allow Live Video. No Page ID or token to copy.') }}</p>
                            </div>
                            @if ($canConnectFacebook)
                                <button type="button" class="btn btn-primary btn-sm rounded-lg text-xs flex items-center gap-1.5 shrink-0" data-meta-connect="business" data-meta-connect-form="facebookLiveConnectForm" data-app-id="{{ $fbSignup['app_id'] ?? '' }}" data-config-id="{{ $fbSignup['config_id'] ?? '' }}" data-graph-api-version="{{ $fbSignup['graph_api_version'] ?? '' }}" @foreach ($fbLoginText as $attribute => $text) {{ $attribute }}="{{ $text }}" @endforeach>
                                    <i class="ph-fill ph-facebook-logo text-base"></i>
                                    <span>{{ __('Connect with Facebook') }}</span>
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <label for="fb_page_id" class="form-label text-[11px] font-semibold">{{ __('Facebook Page to stream to') }}</label>
                                <select id="fb_page_id" name="facebook[page_id]" class="form-input mt-1 w-full rounded-lg py-2 text-xs">
                                    <option value="">{{ __('Select a Page') }}</option>
                                    @foreach ($fbPages as $fbPage)
                                        <option value="{{ $fbPage['id'] }}" @selected(($fbCfg['page_id'] ?? '') === $fbPage['id'])>{{ $fbPage['name'] }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-body" data-meta-connect-status="business">{{ __('Page missing? Click Reconnect and select it in the Facebook window.') }}</p>
                            </div>
                            @if ($canConnectFacebook)
                                <button type="button" class="btn btn-outline btn-sm rounded-lg text-xs flex items-center gap-1.5 shrink-0" data-meta-connect="business" data-meta-connect-form="facebookLiveConnectForm" data-app-id="{{ $fbSignup['app_id'] ?? '' }}" data-config-id="{{ $fbSignup['config_id'] ?? '' }}" data-graph-api-version="{{ $fbSignup['graph_api_version'] ?? '' }}" @foreach ($fbLoginText as $attribute => $text) {{ $attribute }}="{{ $text }}" @endforeach>
                                    <i class="ph-fill ph-facebook-logo text-base"></i>
                                    <span>{{ __('Reconnect') }}</span>
                                </button>
                            @endif
                        </div>
                    @endif
                    @if (! ($fbSignup['enabled'] ?? false))
                        <p class="mt-2 text-[11px] text-amber-700 dark:text-amber-300">{{ __('Connecting with Facebook is not available yet. The platform administrator still has to finish the Meta app setup.') }}</p>
                    @elseif (! $canConnectFacebook)
                        <p class="mt-2 text-[11px] text-amber-700 dark:text-amber-300">{{ __('Ask an account owner with Meta channel access to connect Facebook.') }}</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                <span class="text-xs text-body">
                    {{ __('Reverb App Key:') }} <code class="rounded bg-neutral-100 px-1.5 py-0.5 font-mono text-[11px] dark:bg-neutral-800">{{ $liveCfg['reverb']['app_key'] ?? 'fashionfiest-live-key' }}</code>
                </span>
                <button type="submit" class="btn btn-primary btn-sm rounded-xl px-5 py-2.5 text-xs font-semibold flex items-center gap-2">
                    <i class="ph ph-floppy-disk text-base"></i>
                    <span>{{ __('Save Live & Facebook Settings') }}</span>
                </button>
            </div>
        </form>

        @if ($canConnectFacebook)
            <form id="facebookLiveConnectForm" method="POST" action="{{ route('user.commerce.storefront.live.facebook.connect') }}" class="hidden">
                @csrf
                <input type="hidden" name="_return" value="live-settings">
                <input type="hidden" name="code" value="">
            </form>
        @endif

        <form method="POST" action="{{ route('user.commerce.storefront.live.verify-facebook') }}" class="flex items-center justify-between rounded-2xl border border-neutral-200 bg-white px-5 py-4 shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
            @csrf
            <input type="hidden" name="_return" value="live-settings">
            <div class="space-y-0.5">
                <p class="text-xs font-semibold text-title">{{ __('Test & Verify Facebook Live Connection') }}</p>
                <p class="text-[11px] text-body">{{ __('Checks with Facebook that the selected Page can be reached before the owner goes live from the mobile app.') }}</p>
            </div>
            <button type="submit" class="btn btn-outline btn-sm rounded-xl px-4 py-2 text-xs font-semibold flex items-center gap-1.5 shrink-0">
                <i class="ph ph-check-circle text-base"></i>
                <span>{{ __('Verify Facebook Now') }}</span>
            </button>
        </form>
    </div>
</x-layouts.user>
