<x-layouts.user :title="__('WhatsApp Cloud')">
    @php
        $catalogs = $catalogs ?? collect();
        $embeddedSignup = $embeddedSignup ?? ['enabled' => false, 'app_id' => '', 'config_id' => '', 'graph_api_version' => 'v20.0'];
        $validationErrors = $errors ?? session('errors');
        $displayWabaId = $channel?->provider_account_id;
        $displayBusinessId = data_get($channel?->settings, 'business_id');
        $displayPhoneNumberId = $channel?->provider_phone_id;
        $displayVerifyToken = $channel?->webhook_verify_token;
        $hasAccessToken = filled($channel?->credential('access_token'));
        $readiness = [
            ['label' => __('Access token'), 'ok' => $hasAccessToken, 'detail' => $hasAccessToken ? __('Configured') : __('Missing')],
            ['label' => __('WABA ID'), 'ok' => filled($displayWabaId), 'detail' => $displayWabaId ?: __('Missing')],
            ['label' => __('Phone number ID'), 'ok' => filled($displayPhoneNumberId), 'detail' => $displayPhoneNumberId ?: __('Missing')],
            ['label' => __('Webhook URL'), 'ok' => filled($webhookUrl ?? null), 'detail' => $webhookUrl ?? __('Missing')],
            ['label' => __('Approved templates'), 'ok' => ($approvedTemplatesCount ?? 0) > 0, 'detail' => (string) ($approvedTemplatesCount ?? 0)],
            ['label' => __('Active catalogs'), 'ok' => $catalogs->isNotEmpty(), 'detail' => (string) $catalogs->count()],
        ];
    @endphp

    <x-marketing-channels::setup-header
        provider="whatsapp"
        :channel="$channel"
        :description="__('Connect a WhatsApp Business number for inbox, campaigns, templates and automation.')"
    />

    <x-marketing-channels::status-banner />

    <x-marketing-channels::setup-layout>
        <section class="section-card">
            <x-marketing-channels::setup-steps :steps="$steps" />
        </section>

        @if ($channel)
            <x-marketing-channels::connected-summary
                :channel="$channel"
                provider="whatsapp"
                :fields="[
                    __('WhatsApp Business Account ID') => $displayWabaId,
                    __('Business ID') => $displayBusinessId,
                    __('Phone Number ID') => $displayPhoneNumberId,
                    __('Display phone') => $channel->provider_display_id,
                    __('Verified name') => data_get($channel->settings, 'verified_name'),
                ]"
            >
                <x-slot:actions>
                    <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.test-channel', $channel) }}">
                        @csrf
                        <button type="submit" class="btn-sm btn-outline">
                            <i class="ph ph-plugs-connected text-base"></i>
                            {{ __('Test Connection') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.sync') }}">
                        @csrf
                        <button type="submit" class="btn-sm btn-outline">
                            <i class="ph ph-arrows-clockwise text-base"></i>
                            {{ __('Sync from Meta') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.disconnect') }}" onsubmit="return confirm('{{ __('Disconnect this WhatsApp channel?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-sm btn-outline text-error hover:border-error hover:text-error">
                            <i class="ph ph-plugs text-base"></i>
                            {{ __('Disconnect') }}
                        </button>
                    </form>
                </x-slot:actions>
            </x-marketing-channels::connected-summary>

            <section class="section-card" data-whatsapp-readiness>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="eyebrow">{{ __('Production health') }}</p>
                        <h3 class="heading-5 mt-1">{{ __('WhatsApp Cloud readiness') }}</h3>
                        <p class="mt-1 text-sm text-body">{{ __('Everything required for live inbox, campaigns, templates, webhooks and catalog sync.') }}</p>
                    </div>
                    <span class="badge badge-soft">{{ $embeddedSignup['graph_api_version'] ?? 'v24.0' }}</span>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($readiness as $item)
                        <div class="rounded-xl border border-neutral-100 bg-section p-3">
                            <div class="flex items-start gap-2">
                                <i class="ph {{ $item['ok'] ? 'ph-check-circle text-success' : 'ph-warning-circle text-warning' }} mt-0.5 text-lg"></i>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-title">{{ $item['label'] }}</p>
                                    <p class="mt-0.5 truncate text-xs text-body">{{ $item['detail'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="section-card" data-whatsapp-connect>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[#1877F2]/10 text-[#1877F2]">
                        <i class="ph-fill ph-facebook-logo text-2xl"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-base font-bold text-title">{{ __('Connect with Meta') }}</p>
                            @if ($embeddedSignup['enabled'])
                                <span class="badge badge-soft">{{ __('Recommended') }}</span>
                            @else
                                <span class="badge badge-warning">{{ __('Admin setup required') }}</span>
                            @endif
                        </div>
                        @if ($embeddedSignup['enabled'])
                            <p class="mt-0.5 text-xs font-medium text-body" data-whatsapp-embedded-status>{{ __('Official WhatsApp Business connection flow. Keep your existing number and chat history, or register a new one.') }}</p>
                        @else
                            <p class="mt-0.5 text-xs font-medium text-body">{{ __('The one-click Meta flow becomes available once an administrator configures Embedded Signup.') }}</p>
                        @endif
                    </div>
                </div>

                @if ($embeddedSignup['enabled'])
                    <form id="whatsappEmbeddedSignupForm" method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.embedded') }}" class="hidden">
                        @csrf
                        <input type="hidden" name="code" data-embedded-field="code">
                        <input type="hidden" name="waba_id" data-embedded-field="waba_id">
                        <input type="hidden" name="phone_number_id" data-embedded-field="phone_number_id">
                        <input type="hidden" name="business_id" data-embedded-field="business_id">
                        <input type="hidden" name="display_name" data-embedded-field="display_name">
                    </form>

                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            class="btn btn-primary"
                            data-whatsapp-embedded-signup
                            data-signup-mode="coexistence"
                            data-app-id="{{ $embeddedSignup['app_id'] }}"
                            data-config-id="{{ $embeddedSignup['config_id'] }}"
                            data-graph-api-version="{{ $embeddedSignup['graph_api_version'] }}"
                        >
                            <i class="ph-fill ph-whatsapp-logo text-base"></i>
                            {{ __('Connect existing number') }}
                        </button>
                        <button
                            type="button"
                            class="btn btn-outline"
                            data-whatsapp-embedded-signup
                            data-signup-mode="new"
                            data-app-id="{{ $embeddedSignup['app_id'] }}"
                            data-config-id="{{ $embeddedSignup['config_id'] }}"
                            data-graph-api-version="{{ $embeddedSignup['graph_api_version'] }}"
                        >
                            <i class="ph-fill ph-facebook-logo text-base"></i>
                            {{ __('Register new number') }}
                        </button>
                    </div>
                @endif
            </div>

            @if (! $embeddedSignup['enabled'])
                <div class="mt-4 rounded-xl border border-warning/20 bg-warning/10 p-3 text-sm font-medium text-warning">
                    {{ __('Ask an administrator to enable Embedded Signup and add the Meta App ID, App Secret, and Configuration ID.') }}
                </div>
            @endif
        </section>

        <x-marketing-channels::credentials-panel
            :open="! $channel || $validationErrors?->any()"
            :title="$channel ? __('Update credentials') : __('Set up manually')"
            :description="__('Enter the Meta Business details yourself instead of using Embedded Signup.')"
        >
            <form class="space-y-4" method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.store') }}">
                @csrf
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-neutral-100"></div>
                    <span class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Manual setup') }}</span>
                    <div class="h-px flex-1 bg-neutral-100"></div>
                </div>
                <div>
                    <label class="form-label" for="whatsapp_name">{{ __('Channel Name') }}</label>
                    <input id="whatsapp_name" name="name" type="text" class="form-input mt-1" placeholder="{{ __('e.g. WaPro Coffee Co.') }}" value="{{ old('name', $channel?->name ?? 'WhatsApp Business') }}" required>
                    <p class="form-hint mt-1">{{ __('Shown in the inbox and channel list.') }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="form-label">{{ __('WhatsApp Business Account ID') }}</span>
                        <input name="waba_id" type="text" inputmode="numeric" class="form-input mt-1" placeholder="e.g. 102938475610293" value="{{ old('waba_id', $displayWabaId) }}" required>
                    </label>
                    <label class="block">
                        <span class="form-label">{{ __('Business ID') }}</span>
                        <input name="business_id" type="text" inputmode="numeric" class="form-input mt-1" placeholder="e.g. 784512309876543" value="{{ old('business_id', $displayBusinessId) }}" required>
                    </label>
                </div>
                <div>
                    <label class="form-label" for="whatsapp_phone_number_id">{{ __('Phone Number ID') }}</label>
                    <input id="whatsapp_phone_number_id" name="phone_number_id" type="text" inputmode="numeric" class="form-input mt-1" placeholder="e.g. 1069382741050193" value="{{ old('phone_number_id', $displayPhoneNumberId) }}" required>
                </div>
                <div>
                    <label class="form-label" for="whatsapp_access_token">{{ __('Permanent Access Token') }}</label>
                    <p class="form-hint">{{ __('Generate a System User token with') }} <code class="text-primary">whatsapp_business_management</code> {{ __('and') }} <code class="text-primary">whatsapp_business_messaging</code> {{ __('permissions.') }}</p>
                    <input id="whatsapp_access_token" name="access_token" type="password" class="form-input mt-1" placeholder="EAAxxxxxxxxxxxxxxxx" required autocomplete="off">
                </div>
                <div>
                    <label class="form-label" for="whatsapp_webhook_verify_token">{{ __('Webhook Verify Token') }}</label>
                    <input id="whatsapp_webhook_verify_token" name="webhook_verify_token" type="text" class="form-input mt-1" placeholder="{{ __('Choose a private random token') }}" value="{{ old('webhook_verify_token', $displayVerifyToken) }}" required>
                    <p class="form-hint mt-1">{{ __('Paste the same value into the Meta app webhook settings together with the webhook URL.') }}</p>
                </div>
                <div class="pt-1">
                    <button type="submit" class="btn btn-primary w-full justify-center">
                        <i class="ph ph-whatsapp-logo text-base"></i>
                        {{ $channel ? __('Update WhatsApp') : __('Connect WhatsApp') }}
                    </button>
                </div>
            </form>
        </x-marketing-channels::credentials-panel>

        <x-slot:aside>
            <x-marketing-channels::before-you-start :items="[
                __('Admin access to the Meta Business Manager that owns the WhatsApp Business Account.'),
                __('The WhatsApp Business Account ID and Business ID from Meta Business Settings.'),
                __('The Phone Number ID of the number you want to connect.'),
                __('A permanent System User token with whatsapp_business_management and whatsapp_business_messaging.'),
                __('A private verify token of your choice for the webhook.'),
            ]">
                <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-semibold text-primary hover:underline">
                    {{ __('Meta Cloud API getting started guide') }}
                    <i class="ph ph-arrow-square-out"></i>
                </a>
            </x-marketing-channels::before-you-start>

            <x-marketing-channels::webhook-box
                :url="$webhookUrl"
                :verify-token="$displayVerifyToken"
                :hint="__($provider['webhook_hint'] ?? 'Use this provider webhook URL in Meta app webhook settings.')"
            />

            @include('commerce::user.partials.help', ['helpKey' => 'channel'])
        </x-slot:aside>
    </x-marketing-channels::setup-layout>

    @if ($embeddedSignup['enabled'])
      @push('scripts')
        <script>
          window.fbAsyncInit = function () {
            const trigger = document.querySelector('[data-whatsapp-embedded-signup]');

            if (! trigger || ! window.FB) {
              return;
            }

            window.FB.init({
              appId: trigger.dataset.appId,
              autoLogAppEvents: true,
              xfbml: true,
              version: trigger.dataset.graphApiVersion || 'v20.0',
            });
          };

          (function (document, id) {
            if (document.getElementById(id)) {
              return;
            }

            const script = document.createElement('script');
            script.id = id;
            script.async = true;
            script.defer = true;
            script.crossOrigin = 'anonymous';
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            document.head.appendChild(script);
          })(document, 'facebook-jssdk');

          document.addEventListener('DOMContentLoaded', () => {
            const trigger = document.querySelector('[data-whatsapp-embedded-signup]');
            const form = document.getElementById('whatsappEmbeddedSignupForm');
            const status = document.querySelector('[data-whatsapp-embedded-status]');

            if (! trigger || ! form) {
              return;
            }

            let signupData = {};
            let pendingCode = null;
            let submitted = false;

            const setStatus = (message, isError = false) => {
              if (! status) {
                return;
              }

              status.textContent = message;
              status.classList.toggle('text-error', isError);
              status.classList.toggle('text-body', ! isError);
            };

            const field = (name) => form.querySelector(`[data-embedded-field="${name}"]`);

            const read = (keys) => {
              for (const key of keys) {
                const value = key.split('.').reduce((carry, part) => carry && carry[part], signupData);

                if (value !== undefined && value !== null && value !== '') {
                  return value;
                }
              }

              return '';
            };

            const submit = () => {
              if (submitted || ! pendingCode) {
                return;
              }

              submitted = true;
              field('code').value = pendingCode;
              field('waba_id').value = read(['waba_id', 'whatsapp_business_account_id', 'data.waba_id', 'data.whatsapp_business_account_id']);
              field('phone_number_id').value = read(['phone_number_id', 'data.phone_number_id']);
              field('business_id').value = read(['business_id', 'data.business_id']);
              field('display_name').value = read(['display_name', 'verified_name', 'data.display_name', 'data.verified_name']);
              setStatus('Finishing your WhatsApp connection...');
              form.submit();
            };

            window.addEventListener('message', (event) => {
              if (! event.origin.endsWith('facebook.com')) {
                return;
              }

              let payload = event.data;

              if (typeof payload === 'string') {
                try {
                  payload = JSON.parse(payload);
                } catch (error) {
                  return;
                }
              }

              if (payload?.type !== 'WA_EMBEDDED_SIGNUP') {
                return;
              }

              if (payload.event === 'FINISH' || payload.event === 'FINISH_ONLY_WABA' || payload.event === 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING') {
                signupData = payload.data || {};
                submit();
              }

              if (payload.event === 'CANCEL') {
                setStatus('Embedded Signup was cancelled before a WhatsApp account was connected.', true);
              }

              if (payload.event === 'ERROR') {
                setStatus(payload.data?.error_message || 'Meta Embedded Signup returned an error. Please try again.', true);
              }
            });

            trigger.addEventListener('click', () => {
              if (! window.FB) {
                setStatus('Meta SDK is still loading. Please try again in a moment.', true);

                return;
              }

              submitted = false;
              pendingCode = null;
              signupData = {};

              const isCoexistence = trigger.dataset.signupMode === 'coexistence';
              const extras = {
                feature: 'whatsapp_embedded_signup',
                sessionInfoVersion: '3',
                setup: {},
              };

              if (isCoexistence) {
                extras.featureType = 'whatsapp_business_app_onboarding';
              }

              setStatus(isCoexistence
                ? 'Opening Meta Embedded Signup. You will receive a verification code on WhatsApp to paste into your WhatsApp Business app.'
                : 'Opening Meta Embedded Signup...');

              window.FB.login((response) => {
                if (response.authResponse?.code || response.authResponse?.accessToken) {
                  pendingCode = response.authResponse.code || response.authResponse.accessToken;
                  window.setTimeout(submit, 800);
                  return;
                }

                if (response.status !== 'connected') {
                  setStatus('Meta login was not completed. No credentials were saved.', true);
                } else {
                  setStatus('Meta login completed, but no code was returned. Try again or check permissions.', true);
                }
              }, {
                config_id: trigger.dataset.configId,
                response_type: 'code',
                override_default_response_type: true,
                extras,
              });
            });
          });
        </script>
      @endpush
    @endif
</x-layouts.user>
