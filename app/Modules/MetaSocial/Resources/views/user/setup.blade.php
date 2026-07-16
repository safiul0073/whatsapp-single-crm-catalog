<x-layouts.user :title="__('Social Channels')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">Social Channels</h2>
            <p class="m-text mt-1">Connect Messenger and Instagram accounts for shared inbox, chatbots, and campaigns.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mt-4 rounded-xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
            {{ session('error') }}
        </div>
    @endif

    <div class="mt-6 grid gap-5 xl:grid-cols-2">
        @foreach ([
            'messenger' => ['label' => 'Messenger', 'icon' => 'ph-messenger-logo', 'signup' => $messengerSignup, 'accounts' => $messengerAccounts, 'hint' => 'Connect Facebook Pages and receive Messenger conversations.'],
            'instagram' => ['label' => 'Instagram', 'icon' => 'ph-instagram-logo', 'signup' => $instagramSignup, 'accounts' => $instagramAccounts, 'hint' => 'Connect Instagram Business accounts for DMs, mentions, and comments.'],
        ] as $provider => $meta)
            <section class="card p-4 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-primary">
                            <i class="ph {{ $meta['icon'] }} text-2xl"></i>
                        </span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-title text-base font-bold text-title">{{ $meta['label'] }}</h3>
                                <span class="badge badge-success">{{ $meta['accounts']->count() }} connected</span>
                            </div>
                            <p class="mt-0.5 text-sm text-body">{{ $meta['hint'] }}</p>
                        </div>
                    </div>
                    @if ($meta['signup']['enabled'])
                        <span class="badge badge-soft text-primary">Embedded Signup ready</span>
                    @else
                        <span class="badge badge-soft">Admin setup required</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('user.meta-social.setup.embedded', $provider) }}" class="mt-5 rounded-xl bg-section p-4">
                    @csrf
                    <input type="hidden" name="code" value="">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @if ($provider === 'messenger')
                            <label class="block">
                                <span class="form-label">Facebook Page ID</span>
                                <input class="form-input" name="page_id" placeholder="e.g. 1122334455">
                            </label>
                            <label class="block">
                                <span class="form-label">Page Name</span>
                                <input class="form-input" name="page_name" placeholder="WaPro Support">
                            </label>
                        @else
                            <label class="block">
                                <span class="form-label">Instagram Account ID</span>
                                <input class="form-input" name="instagram_account_id" placeholder="e.g. 17841400000000000">
                            </label>
                            <label class="block">
                                <span class="form-label">Username</span>
                                <input class="form-input" name="username" placeholder="wapro.app">
                            </label>
                        @endif
                        <label class="block sm:col-span-2">
                            <span class="form-label">Page access token</span>
                            <input class="form-input" name="access_token" type="password" placeholder="Stored encrypted and never displayed">
                            <span class="form-hint">Embedded Signup can post a Meta code here later; manual token fallback keeps testing possible.</span>
                        </label>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button class="btn-sm btn-primary" type="submit">
                            <i class="ph ph-plugs-connected text-base"></i>
                            Connect {{ $meta['label'] }}
                        </button>
                        <span class="text-xs text-neutral-400"
                              data-meta-social-config
                              data-provider="{{ $provider }}"
                              data-app-id="{{ $meta['signup']['app_id'] }}"
                              data-config-id="{{ $meta['signup']['config_id'] }}"
                              data-graph-api-version="{{ $meta['signup']['graph_api_version'] }}">
                            {{ $meta['signup']['enabled'] ? 'Meta SDK configuration available.' : 'Configure Meta app settings in admin first.' }}
                        </span>
                    </div>
                </form>

                <div class="mt-5 space-y-3">
                    @forelse ($meta['accounts'] as $account)
                        <div class="rounded-xl border border-neutral-100 p-4 dark:border-neutral-800">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-title">{{ $account->name }}</p>
                                    <p class="mt-0.5 text-xs text-neutral-400">{{ $account->provider_display_id ?? $account->provider_account_id }}</p>
                                </div>
                                <span class="badge badge-success">{{ ucfirst($account->status->value) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <div class="code-box min-w-0 flex-1 basis-56">{{ $account->webhook_url }}</div>
                                <button type="button" class="btn-sm btn-outline" data-copy="{{ $account->webhook_url }}">
                                    <i class="ph ph-copy text-base"></i>
                                    Copy
                                </button>
                                <form method="POST" action="{{ route('user.meta-social.setup.disconnect', $account) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-sm btn-outline" type="submit">
                                        <i class="ph ph-plugs text-base"></i>
                                        Disconnect
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-neutral-200 p-4 text-sm text-body dark:border-neutral-800">
                            No {{ strtolower($meta['label']) }} account connected yet.
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.user>
