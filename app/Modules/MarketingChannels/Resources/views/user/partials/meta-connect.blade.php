@php
    $businessConnection = $metaBusiness['connection'] ?? null;
    $businessSignup = $metaBusiness['signup'] ?? ['enabled' => false];
    $whatsappSignup = $metaConnect['whatsapp'] ?? ['enabled' => false];
    $socialSignups = array_filter([
        'messenger' => $metaConnect['messenger'] ?? null,
        'instagram' => $metaConnect['instagram'] ?? null,
    ], fn ($signup): bool => (bool) ($signup['enabled'] ?? false));
    $canSocial = auth()->user()?->can('meta-social.manage') ?? false;
    $canWhatsApp = auth()->user()?->can('channels.manage') ?? false;
    $showBusiness = $canSocial && ($businessSignup['enabled'] || $businessConnection);
    $showWhatsApp = $canWhatsApp && $whatsappSignup['enabled'];
    $showSocialFallback = $canSocial && ! $businessSignup['enabled'] && $socialSignups !== [];
    $loginText = [
        'data-opening-text' => __('Opening Meta… A Facebook window will appear.'),
        'data-waiting-text' => __('Waiting for you in the Meta window. Pick your business and keep every permission checked.'),
        'data-cancelled-text' => __('The Meta window was closed before finishing. Nothing was saved. You can try again any time.'),
        'data-popup-blocked-text' => __('Your browser blocked the Meta window. Allow pop-ups for this site and press the button again.'),
        'data-sdk-error-text' => __('Could not load Meta\'s login. Check your internet connection or turn off ad blockers for this site.'),
    ];
@endphp

@if ($canSocial || $canWhatsApp)
    <section id="meta-connect" class="card mt-6 scroll-mt-24 border-primary/30 p-5" data-meta-business-card>
        <div class="flex min-w-0 items-start gap-4">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
                <i class="ph-fill ph-meta-logo text-2xl"></i>
            </span>
            <div class="min-w-0">
                <h3 class="heading-5">
                    {{ $businessConnection ? __('Meta Business: :name', ['name' => $businessConnection->business_name ?: __('connected')]) : __('Connect with Meta') }}
                </h3>
                <p class="mt-0.5 text-sm text-body">{{ __('One login connects WhatsApp, Facebook Messenger, Instagram, ads and catalogs. No IDs or tokens to copy.') }}</p>
                <ol class="mt-3 grid gap-2 text-sm text-body sm:grid-cols-3" data-meta-connect-steps>
                    <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">1</span>{{ __('Log in with Facebook and choose your business.') }}</li>
                    <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">2</span>{{ __('Pick the numbers, Pages and accounts to turn on.') }}</li>
                    <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">3</span>{{ __('Finish the checklist each channel shows, so messages can flow.') }}</li>
                </ol>
            </div>
        </div>

        @if (! $showBusiness && ! $showWhatsApp && ! $showSocialFallback)
            <x-ui.alert type="warning" class="mt-4" data-meta-connect-unavailable>
                {{ __('Connecting with Meta is not available yet. The platform administrator still has to finish the Meta app setup.') }}
            </x-ui.alert>
        @endif

        @if ($showBusiness)
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-md border border-neutral-100 p-4 dark:border-gray-800" data-meta-connect-block="business">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-title">{{ $businessConnection ? __('Your Meta Business is connected') : __('Connect your existing Meta accounts') }}</p>
                    <p class="mt-0.5 text-xs text-body" data-meta-connect-status="business">
                        @if ($businessConnection)
                            {{ __(':whatsapp WhatsApp numbers, :pages Pages, :instagram Instagram accounts, :ads ad accounts and :catalogs catalogs found.', [
                                'whatsapp' => count($businessConnection->assetsOfType('whatsapp')),
                                'pages' => count($businessConnection->assetsOfType('messenger')),
                                'instagram' => count($businessConnection->assetsOfType('instagram')),
                                'ads' => count($businessConnection->assetsOfType('ad_accounts')),
                                'catalogs' => count($businessConnection->assetsOfType('catalogs')),
                            ]) }}
                        @else
                            {{ __('Brings in the WhatsApp numbers, Facebook Pages, Instagram accounts, ad accounts and catalogs you already have.') }}
                        @endif
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($businessConnection)
                        <a href="{{ route('user.meta-social.business.show') }}" class="btn btn-primary">
                            <i class="ph ph-sliders-horizontal text-base"></i>
                            {{ __('Add or remove channels') }}
                        </a>
                    @endif
                    @if ($businessSignup['enabled'])
                        <form id="metaConnectForm-business" method="POST" action="{{ route('user.meta-social.business.connect') }}" class="hidden">
                            @csrf
                            <input type="hidden" name="code" value="">
                        </form>
                        <button
                            type="button"
                            class="btn {{ $businessConnection ? 'btn-outline' : 'btn-primary' }}"
                            data-meta-connect="business"
                            data-meta-connect-form="metaConnectForm-business"
                            data-app-id="{{ $businessSignup['app_id'] }}"
                            data-config-id="{{ $businessSignup['config_id'] }}"
                            data-graph-api-version="{{ $businessSignup['graph_api_version'] }}"
                            data-finishing-text="{{ __('Checking what you shared with the app…') }}"
                            @foreach ($loginText as $attribute => $text) {{ $attribute }}="{{ $text }}" @endforeach
                        >
                            <i class="ph-fill ph-facebook-logo text-base"></i>
                            {{ $businessConnection ? __('Reconnect') : __('Connect with Meta') }}
                        </button>
                    @endif
                    @if ($businessConnection)
                        <form method="POST" action="{{ route('user.meta-social.business.disconnect') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline" title="{{ __('Connected channels keep working.') }}">{{ __('Remove login') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        @if ($showWhatsApp)
            <div class="mt-3 rounded-md border border-neutral-100 p-4 dark:border-gray-800" data-meta-connect-block="whatsapp">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-title">{{ __('Add a WhatsApp number') }}</p>
                        <p class="mt-0.5 text-xs text-body" data-meta-connect-status="whatsapp">
                            {{ $showBusiness
                                ? __('For a number that is not on the WhatsApp Business Platform yet. Numbers you already use with the platform appear after Connect with Meta.')
                                : __('Connect the number you use in the WhatsApp Business app, or register a new number.') }}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        @include('marketing-channels::user.partials.whatsapp-signup-buttons', ['signup' => $whatsappSignup, 'loginText' => $loginText])
                    </div>
                </div>
                <ul class="mt-3 grid gap-1 text-xs text-body sm:grid-cols-2">
                    <li><span class="font-semibold text-title">{{ __('Existing number') }}:</span> {{ __('keeps the WhatsApp Business app and your chat history. Meta sends a code to the app to confirm.') }}</li>
                    <li><span class="font-semibold text-title">{{ __('New number') }}:</span> {{ __('the number must not be on WhatsApp already and must receive an SMS or call for the code.') }}</li>
                </ul>
            </div>
        @endif

        @if ($showSocialFallback)
            @foreach ($socialSignups as $provider => $signup)
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border border-neutral-100 p-4 dark:border-gray-800" data-meta-connect-block="{{ $provider }}">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-title">{{ $provider === 'instagram' ? __('Connect Instagram') : __('Connect Facebook Messenger') }}</p>
                        <p class="mt-0.5 text-xs text-body" data-meta-connect-status="{{ $provider }}">
                            {{ $provider === 'instagram' ? __('Pick the Instagram professional accounts linked to your Facebook Pages.') : __('Pick the Facebook Pages whose messages should come to the inbox.') }}
                        </p>
                    </div>
                    <form id="metaConnectForm-{{ $provider }}" method="POST" action="{{ route('user.meta-social.setup.embedded', $provider) }}" class="hidden">
                        @csrf
                        <input type="hidden" name="code" value="">
                    </form>
                    <button
                        type="button"
                        class="btn btn-outline"
                        data-meta-connect="{{ $provider }}"
                        data-meta-connect-form="metaConnectForm-{{ $provider }}"
                        data-app-id="{{ $signup['app_id'] }}"
                        data-config-id="{{ $signup['config_id'] }}"
                        data-graph-api-version="{{ $signup['graph_api_version'] }}"
                        data-finishing-text="{{ __('Checking what you shared with the app…') }}"
                        @foreach ($loginText as $attribute => $text) {{ $attribute }}="{{ $text }}" @endforeach
                    >
                        <i class="ph-fill ph-facebook-logo text-base"></i>
                        {{ __('Connect with Facebook') }}
                    </button>
                </div>
            @endforeach
        @endif
    </section>
@endif
