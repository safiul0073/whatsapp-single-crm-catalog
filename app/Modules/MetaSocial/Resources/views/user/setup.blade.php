<x-layouts.user :title="__('Social Channels')">
    @php
        $beforeYouStart = [
            'messenger' => [
                __('Admin access to the Facebook Page you want to connect.'),
                __('Use "Connect with Meta" on the Channels page and select the Page in the Meta window.'),
                __('No Page IDs or tokens needed. Meta shares access securely.'),
            ],
            'instagram' => [
                __('An Instagram Business or Creator account linked to a Facebook Page.'),
                __('Use "Connect with Meta" on the Channels page and select both the Page and the Instagram account.'),
                __('No account IDs or tokens needed. Meta shares access securely.'),
            ],
            'threads' => [
                __('The Threads account you publish under.'),
                __('Use "Connect with Threads" and approve access on Threads.'),
                __('No account IDs or tokens needed.'),
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
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
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
                $pendingAccounts = ($pendingSelection['provider'] ?? null) === $provider ? $pendingSelection['accounts'] : [];
            @endphp

            <section id="{{ $provider }}" class="section-card scroll-mt-24" data-social-section="{{ $provider }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
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
                                    <form method="POST" action="{{ $isThreads ? route('user.whatsapp-cloud.channel-setup.disconnect-channel', $account) : route('user.meta-social.setup.disconnect', $account) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            data-confirm
                                            data-confirm-title="{{ __('Disconnect Channel?') }}"
                                            data-confirm-message="{{ __('Disconnect this social channel from your workspace?') }}"
                                            data-confirm-button="{{ __('Disconnect') }}"
                                            data-confirm-variant="danger"
                                            data-confirm-icon="ph-plugs"
                                            class="btn-sm btn-outline text-error hover:border-error hover:text-error"
                                        >
                                            <i class="ph ph-plugs text-base"></i>
                                            {{ __('Disconnect') }}
                                        </button>
                                    </form>
                                </x-slot:actions>
                            </x-marketing-channels::connected-summary>

                            <x-marketing-channels::readiness-checklist :report="data_get($account->settings, 'readiness')" :channel="$account" />

                            @if ($provider === 'messenger')
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-neutral-200 p-4">
                                    <p class="text-sm text-body">{{ __('Offers on Messenger: :count subscribed customers', ['count' => $account->offer_subscribers]) }}</p>
                                    <a href="{{ route('user.meta-social.offers.index') }}" class="btn-sm btn-outline">{{ __('Manage offer subscribers') }}</a>
                                </div>
                            @endif
                        @endforeach

                        @if ($pendingAccounts !== [])
                            <div class="rounded-md border border-primary/30 bg-primary/5 p-4" data-meta-social-picker="{{ $provider }}">
                                <p class="text-sm font-semibold text-heading">{{ $provider === 'instagram' ? __('Choose Instagram accounts to connect') : __('Choose Facebook Pages to connect') }}</p>
                                <p class="mt-0.5 text-xs text-body">{{ __('Meta shared these accounts with us. Each one becomes its own channel in the shared inbox.') }}</p>
                                <form method="POST" action="{{ route('user.meta-social.setup.select', $provider) }}" class="mt-3 space-y-2">
                                    @csrf
                                    @foreach ($pendingAccounts as $pendingAccount)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950">
                                            <input type="checkbox" name="account_ids[]" value="{{ $pendingAccount['id'] }}" class="form-checkbox" checked>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-medium text-heading">{{ $provider === 'instagram' && filled($pendingAccount['username'] ?? null) ? '@'.$pendingAccount['username'] : $pendingAccount['name'] }}</span>
                                                @if ($provider === 'instagram')
                                                    <span class="block truncate text-xs text-body">{{ __('Linked Page: :page', ['page' => $pendingAccount['page_name']]) }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                    <div class="flex flex-wrap items-center gap-2 pt-1">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="ph ph-plugs-connected text-base"></i>
                                            {{ __('Connect selected') }}
                                        </button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('user.meta-social.setup.selection.cancel') }}" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-sm btn-outline">{{ __('Cancel') }}</button>
                                </form>
                            </div>
                        @endif

                        @if ($isThreads)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 p-4 dark:border-gray-800">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-heading">{{ $accounts->isNotEmpty() ? __('Reconnect Threads') : __('Connect with Threads') }}</p>
                                    <p class="mt-0.5 text-xs text-body">{{ $threadsLoginReady ? __('Sign in on Threads and approve access. No IDs or tokens needed.') : __('Ask an administrator to add the Threads App ID and secret in Meta App settings.') }}</p>
                                </div>
                                @if ($threadsLoginReady)
                                    <form method="POST" action="{{ route('user.threads.connect') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary" data-threads-connect>
                                            <i class="ph ph-threads-logo text-base"></i>
                                            {{ __('Connect with Threads') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-gray-200 p-4 dark:border-gray-800" data-social-connect-link="{{ $provider }}">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-heading">{{ $accounts->isNotEmpty() ? __('Add or reconnect :channel accounts', ['channel' => __($config['label'])]) : __('Connect :channel with Meta', ['channel' => __($config['label'])]) }}</p>
                                    <p class="mt-0.5 text-xs text-body">{{ __('WhatsApp, Messenger and Instagram connect together from the Channels page with one Meta login.') }}</p>
                                </div>
                                <a href="{{ route('user.channels.index') }}#meta-connect" class="btn {{ $accounts->isNotEmpty() ? 'btn-outline' : 'btn-primary' }}">
                                    <i class="ph-fill ph-meta-logo text-base"></i>
                                    {{ $accounts->isNotEmpty() ? __('Reconnect with Meta') : __('Connect with Meta') }}
                                </a>
                            </div>
                        @endif
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
