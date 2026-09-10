<x-layouts.user :title="__('Social Channels')">
    @php
        $validationErrors = $errors ?? session('errors');
        $threadsFields = config('marketing-channels.providers.threads.fields', []);
        $beforeYouStart = [
            'messenger' => [
                __('Admin access to the Facebook Page you want to connect.'),
                __('The Facebook Page ID from Page settings.'),
                __('A Page access token, or Embedded Signup enabled by an administrator.'),
            ],
            'instagram' => [
                __('An Instagram Business or Creator account linked to a Facebook Page.'),
                __('The Instagram account ID from Meta Business settings.'),
                __('A Page access token with Instagram messaging permissions.'),
            ],
            'threads' => [
                __('A Threads account with API access enabled.'),
                __('The Threads account ID and the handle you publish under.'),
                __('A long-lived Threads access token.'),
            ],
        ];
    @endphp

    <div class="space-y-4">
        <a href="{{ route('user.channels.index') }}" class="inline-flex items-center gap-1.5 rounded-md text-sm font-medium text-body transition hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/20" data-channel-hub-link>
            <i class="ph ph-arrow-left text-base"></i>
            {{ __('All channels') }}
        </a>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                    <i class="ph ph-share-network text-2xl"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="heading-2">{{ __('Social Channels') }}</h2>
                    <p class="m-text mt-1">{{ __('Connect Messenger, Instagram and Threads for shared inbox, automation and publishing.') }}</p>
                </div>
            </div>
            <nav class="flex flex-wrap gap-2">
                @foreach ($sections as $section)
                    <a href="#{{ $section['key'] }}" class="btn-sm btn-outline">
                        <i class="ph {{ $section['config']['icon'] }} text-base"></i>
                        {{ __($section['config']['label']) }}
                    </a>
                @endforeach
            </nav>
        </div>
    </div>

    <x-marketing-channels::status-banner />

    <div class="mt-6 space-y-6">
        @foreach ($sections as $section)
            @php
                $provider = $section['key'];
                $config = $section['config'];
                $accounts = $section['accounts'];
                $signup = $section['signup'];
                $isThreads = $provider === 'threads';
                $threadsAccount = $isThreads ? $section['primary'] : null;
            @endphp

            <section id="{{ $provider }}" class="section-card scroll-mt-24" data-social-section="{{ $provider }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                            <i class="ph {{ $config['icon'] }} text-2xl"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="heading-5">{{ __($config['title']) }}</h3>
                                <x-marketing-channels::status-badge :channel="$section['primary']" />
                            </div>
                            <p class="mt-0.5 text-sm text-body">{{ __($config['description']) }}</p>
                        </div>
                    </div>
                    @if ($signup)
                        @if ($signup['enabled'])
                            <span class="badge badge-soft">{{ __('Embedded Signup ready') }}</span>
                        @else
                            <span class="badge badge-warning">{{ __('Admin setup required') }}</span>
                        @endif
                    @endif
                </div>

                <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <div class="min-w-0 space-y-5">
                        <x-marketing-channels::setup-steps :steps="$section['steps']" />

                        @foreach ($accounts as $account)
                            <x-marketing-channels::connected-summary
                                :channel="$account"
                                :provider="$provider"
                                :fields="[
                                    __($config['identifier_label']) => $account->provider_account_id,
                                    __($config['display_label']) => $account->provider_display_id,
                                ]"
                            >
                                <x-slot:actions>
                                    <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.test-channel', $account) }}">
                                        @csrf
                                        <button type="submit" class="btn-sm btn-outline">
                                            <i class="ph ph-plugs-connected text-base"></i>
                                            {{ __('Test Connection') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ $isThreads ? route('user.whatsapp-cloud.channel-setup.disconnect-channel', $account) : route('user.meta-social.setup.disconnect', $account) }}" onsubmit="return confirm('{{ __('Disconnect this channel?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-sm btn-outline text-error hover:border-error hover:text-error">
                                            <i class="ph ph-plugs text-base"></i>
                                            {{ __('Disconnect') }}
                                        </button>
                                    </form>
                                </x-slot:actions>
                            </x-marketing-channels::connected-summary>
                        @endforeach

                        <x-marketing-channels::credentials-panel
                            :open="$accounts->isEmpty() || $validationErrors?->any()"
                            :title="$accounts->isNotEmpty() ? __('Connect another account') : __('Connect :channel', ['channel' => __($config['label'])])"
                        >
                            @if ($isThreads)
                                <form class="space-y-4" method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.store-generic') }}">
                                    @csrf
                                    <input type="hidden" name="provider" value="threads">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        @foreach ($threadsFields as $field)
                                            @php
                                                $fieldName = $field['name'];
                                                $type = $field['type'] ?? 'text';
                                                $current = match ($fieldName) {
                                                    'name' => $threadsAccount?->name,
                                                    'provider_account_id' => $threadsAccount?->provider_account_id,
                                                    'provider_display_id' => $threadsAccount?->provider_display_id,
                                                    default => null,
                                                };
                                            @endphp
                                            <label class="block {{ $type === 'password' ? 'sm:col-span-2' : '' }}">
                                                <span class="form-label">{{ __($field['label']) }} @if ($field['required'] ?? false)<span class="text-error">*</span>@endif</span>
                                                <input
                                                    name="{{ $fieldName }}"
                                                    type="{{ $type }}"
                                                    class="form-input mt-1"
                                                    value="{{ $type === 'password' ? '' : old($fieldName, $current) }}"
                                                    placeholder="{{ $type === 'password' && $threadsAccount ? __('Saved (enter to change)') : __($field['placeholder'] ?? '') }}"
                                                    @if ($field['required'] ?? false) required @endif
                                                    @if ($type === 'password') autocomplete="off" @endif
                                                >
                                            </label>
                                        @endforeach
                                        <label class="block sm:col-span-2">
                                            <span class="form-label">{{ __('Webhook Verify Token') }}</span>
                                            <input name="webhook_verify_token" type="text" class="form-input mt-1" value="{{ old('webhook_verify_token', $threadsAccount?->webhook_verify_token) }}" placeholder="{{ __('Choose a private random token') }}">
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph ph-threads-logo text-base"></i>
                                        {{ $threadsAccount ? __('Update Threads') : __('Connect Threads') }}
                                    </button>
                                </form>
                            @else
                                <form class="space-y-4" method="POST" action="{{ route('user.meta-social.setup.embedded', $provider) }}">
                                    @csrf
                                    <input type="hidden" name="code" value="">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        @if ($provider === 'messenger')
                                            <label class="block">
                                                <span class="form-label">{{ __('Facebook Page ID') }}</span>
                                                <input class="form-input mt-1" name="page_id" value="{{ old('page_id') }}" placeholder="e.g. 1122334455">
                                            </label>
                                            <label class="block">
                                                <span class="form-label">{{ __('Page Name') }}</span>
                                                <input class="form-input mt-1" name="page_name" value="{{ old('page_name') }}" placeholder="WaPro Support">
                                            </label>
                                        @else
                                            <label class="block">
                                                <span class="form-label">{{ __('Instagram Account ID') }}</span>
                                                <input class="form-input mt-1" name="instagram_account_id" value="{{ old('instagram_account_id') }}" placeholder="e.g. 17841400000000000">
                                            </label>
                                            <label class="block">
                                                <span class="form-label">{{ __('Username') }}</span>
                                                <input class="form-input mt-1" name="username" value="{{ old('username') }}" placeholder="wapro.app">
                                            </label>
                                        @endif
                                        <label class="block sm:col-span-2">
                                            <span class="form-label">{{ __('Page access token') }}</span>
                                            <input class="form-input mt-1" name="access_token" type="password" placeholder="{{ __('Stored encrypted and never displayed') }}" autocomplete="off">
                                            <span class="form-hint">{{ __('Embedded Signup can post a Meta code here later; a manual token keeps testing possible.') }}</span>
                                        </label>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="ph ph-plugs-connected text-base"></i>
                                            {{ __('Connect :channel', ['channel' => __($config['label'])]) }}
                                        </button>
                                        <span class="text-xs text-body"
                                              data-meta-social-config
                                              data-provider="{{ $provider }}"
                                              data-app-id="{{ $signup['app_id'] }}"
                                              data-config-id="{{ $signup['config_id'] }}"
                                              data-graph-api-version="{{ $signup['graph_api_version'] }}">
                                            {{ $signup['enabled'] ? __('Meta SDK configuration available.') : __('Configure Meta app settings in admin first.') }}
                                        </span>
                                    </div>
                                </form>
                            @endif
                        </x-marketing-channels::credentials-panel>
                    </div>

                    <aside class="space-y-4">
                        <x-marketing-channels::before-you-start :items="$beforeYouStart[$provider]" />

                        @if ($isThreads)
                            <x-marketing-channels::webhook-box
                                :url="$section['webhook_url']"
                                :hint="__($config['webhook_hint'] ?? '')"
                            />
                        @elseif ($section['primary'])
                            <x-marketing-channels::webhook-box
                                :url="$section['primary']->webhook_url"
                                :hint="__($config['webhook_hint'] ?? '')"
                            />
                        @endif
                    </aside>
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.user>
