<x-layouts.user :title="__('Channel Setup')">
@php
  $connectedCount = $allChannels->count();
  $displayWabaId = $channel?->provider_account_id;
  $displayBusinessId = data_get($channel?->settings, 'business_id');
  $displayPhoneNumberId = $channel?->provider_phone_id;
  $displayVerifyToken = $channel?->webhook_verify_token;
  $validationErrors = $errors ?? session('errors');
  $embeddedSignup = $embeddedSignup ?? ['enabled' => false, 'app_id' => '', 'config_id' => '', 'graph_api_version' => 'v20.0'];
  $connectableChannelProviders = collect($channelProviders)
      ->reject(fn (array $provider): bool => ($provider['connect_mode'] ?? null) === 'internal')
      ->all();
  $credentialProviderSelections = collect($channelProviders)->mapWithKeys(function (array $provider, string $providerKey) use ($channelsByProvider): array {
      $fieldName = $provider['credential_provider_field'] ?? null;

      if (! $fieldName) {
          return [$providerKey => []];
      }

      $field = collect($provider['fields'] ?? [])->firstWhere('name', $fieldName) ?? [];
      $options = $field['options'] ?? [];
      $default = $provider['credential_provider_default'] ?? array_key_first($options) ?? '';
      $channel = $channelsByProvider->get($providerKey);

      return [
          $providerKey => [
              $fieldName => old($fieldName, $channel?->credential($fieldName, $default) ?? $default),
          ],
      ];
  })->all();
@endphp

<div>
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="min-w-0">
            <h2 class="heading-2">Channel Setup</h2>
            <p class="m-text mt-1">Connect and manage provider channels for inbox, campaigns, automation, and webhooks.</p>
          </div>
          <button type="button" class="btn-sm btn-primary" data-drawer-trigger="channelSettingsDrawer">
            <i class="ph ph-plus text-base"></i>
            Add channel
          </button>
        </div>

        <div class="mt-4">
          @include('commerce::user.partials.help', ['helpKey' => 'channel'])
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

        @if ($validationErrors?->any())
          <div class="mt-4 rounded-xl border border-error/20 bg-error/5 px-4 py-3 text-sm font-medium text-error">
            {{ $validationErrors->first() }}
          </div>
        @endif

        @if ($connectedCount === 0)
          <section class="card mt-6 overflow-hidden p-0">
            <div class="grid gap-0 lg:grid-cols-[1.05fr_0.95fr]">
              <div class="p-6 sm:p-8">
                <span class="grid h-14 w-14 place-items-center rounded-xl bg-primary/10 text-primary">
                  <i class="ph ph-plugs-connected text-3xl"></i>
                </span>
                <h3 class="mt-5 font-title text-xl font-bold text-title">No channel connected yet</h3>
                <p class="m-text mt-2 max-w-2xl">Add your first channel to start receiving messages, syncing provider details, and routing conversations into this workspace.</p>
                <button type="button" class="btn btn-primary mt-6" data-drawer-trigger="channelSettingsDrawer">
                  <i class="ph ph-plus text-base"></i>
                  Choose a channel
                </button>
              </div>
              <div class="border-t border-neutral-100 bg-section p-6 lg:border-l lg:border-t-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Available channels</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                  @foreach ($connectableChannelProviders as $providerKey => $provider)
                    <button type="button" class="rounded-xl border border-neutral-100 bg-white p-4 text-left transition hover:border-primary/40 hover:bg-primary/5" data-drawer-trigger="channelSettingsDrawer" data-drawer-provider="{{ $providerKey }}">
                      <span class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                          <i class="ph {{ $provider['icon'] }} text-xl"></i>
                        </span>
                        <span>
                          <span class="block text-sm font-bold text-title">{{ $provider['label'] }}</span>
                          <span class="mt-0.5 block text-xs text-body">{{ $provider['connect_mode'] === 'whatsapp' ? 'Live validation' : 'Manual setup' }}</span>
                        </span>
                      </span>
                    </button>
                  @endforeach
                </div>
                <div class="mt-4 rounded-xl border border-neutral-100 bg-white p-4">
                  <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Example provider webhook</p>
                  <p class="mt-2 truncate font-mono text-xs text-body">{{ $webhookUrls['whatsapp'] ?? '' }}</p>
                </div>
              </div>
            </div>
          </section>
        @else
          <div class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
            @foreach ($allChannels as $connectedChannel)
              @php
                $provider = $channelProviders[$connectedChannel->provider] ?? null;
                $settings = $connectedChannel->settings ?? [];
                $status = $connectedChannel->status?->value ?? 'draft';
                $webhook = $connectedChannel->webhook_url ?? ($webhookUrls[$connectedChannel->provider] ?? '');
              @endphp
              @continue(! $provider)
              <article class="card p-4 transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-sm">
                <button type="button" class="block w-full text-left" data-modal-open="channelDetails{{ $connectedChannel->id }}">
                  <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                      <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph {{ $provider['icon'] }} text-2xl"></i>
                      </span>
                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                          <h3 class="truncate font-title text-base font-bold text-title">{{ $connectedChannel->name }}</h3>
                          <span class="badge {{ $status === 'connected' ? 'badge-success' : ($status === 'error' ? 'badge-error' : 'badge-warning') }}">{{ ucfirst($status) }}</span>
                        </div>
                        <p class="mt-0.5 text-sm text-body">{{ $provider['title'] }}</p>
                      </div>
                    </div>
                    <i class="ph ph-caret-right shrink-0 text-lg text-neutral-400"></i>
                  </div>

                  <div class="mt-5 grid gap-3 rounded-xl bg-section p-4">
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $provider['identifier_label'] }}</p>
                      <p class="mt-1 truncate font-mono text-sm font-semibold text-title">{{ $connectedChannel->provider_phone_id ?? $connectedChannel->provider_account_id ?? 'Not set' }}</p>
                    </div>
                    <div>
                      <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $provider['display_label'] }}</p>
                      <p class="mt-1 truncate text-sm font-semibold text-title">{{ $connectedChannel->provider_display_id ?? $connectedChannel->name }}</p>
                    </div>
                    @if ($provider['webhook_required'] ?? false)
                      <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Webhook URL</p>
                        <p class="mt-1 truncate font-mono text-xs text-body">{{ $webhook }}</p>
                      </div>
                    @endif
                  </div>

                  <div class="mt-4 flex flex-wrap gap-2">
                    @foreach (($provider['capabilities'] ?? []) as $capability)
                      <span class="badge badge-soft">{{ $capability }}</span>
                    @endforeach
                  </div>
                  @if (filled($settings['last_error'] ?? null))
                    <div class="mt-4 rounded-xl border border-error/20 bg-error/5 p-3 text-sm font-medium text-error">
                      {{ $settings['last_error'] }}
                    </div>
                  @endif
                </button>
              </article>
            @endforeach
          </div>
        @endif



    @push('drawers')
    <div
      class="drawer drawer-full sm:!w-[680px]"
      id="channelSettingsDrawer"
      x-data="{
        step: 1,
        selectedProvider: null,
        providers: {{ Js::from($connectableChannelProviders) }},
        webhookUrls: {{ Js::from($webhookUrls) }},
        providerSelections: {{ Js::from($credentialProviderSelections) }},
        connectedProviders: {{ Js::from($channelsByProvider->map(fn ($account) => [
          'id' => $account->id,
          'name' => $account->name,
          'provider_account_id' => $account->provider_account_id,
          'provider_phone_id' => $account->provider_phone_id,
          'provider_display_id' => $account->provider_display_id,
          'webhook_verify_token' => $account->webhook_verify_token,
          'webhook_url' => $account->webhook_url,
        ])->all()) }},
        selectProvider(provider) {
          this.selectedProvider = provider;
          this.step = 2;
        },
        backToProviders() {
          this.step = 1;
          this.selectedProvider = null;
        },
        providerMeta() {
          return this.selectedProvider ? (this.providers[this.selectedProvider] || null) : null;
        },
        providerWebhook() {
          return this.selectedProvider ? (this.connectedProvider()?.webhook_url || this.webhookUrls[this.selectedProvider] || '') : '';
        },
        connectedProvider() {
          return this.selectedProvider ? (this.connectedProviders[this.selectedProvider] || null) : null;
        }
      }"
      x-init="new MutationObserver(() => {
        if (!$el.classList.contains('active')) {
          return;
        }

        if ($el.dataset.openProvider) {
          selectedProvider = $el.dataset.openProvider;
          step = 2;
          delete $el.dataset.openProvider;
          return;
        }

        if ($el.dataset.resetOnOpen !== '0') {
          step = 1;
          selectedProvider = null;
        }
      }).observe($el, { attributes: true, attributeFilter: ['class'] })"
    >
      <div class="flex h-full flex-col bg-neutral-0" role="dialog" aria-modal="true" aria-labelledby="channelSettingsDrawerTitle">
        <div class="border-b border-neutral-100 p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 id="channelSettingsDrawerTitle" class="heading-4" x-text="step === 1 ? 'Channel connection settings' : ((connectedProvider() ? 'Update ' : 'Add ') + (providerMeta()?.label || 'channel'))"></h3>
              <p class="mt-1 text-sm text-body" x-text="step === 1 ? 'Choose a provider to add or update its connection settings.' : (providerMeta()?.description || '')"></p>
            </div>
            <button type="button" class="row-action" data-drawer-close aria-label="Close">
              <i class="ph ph-x text-base"></i>
            </button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-5 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-3">
          <div class="rounded-xl p-4" :class="step === 1 ? 'bg-primary/10 text-primary' : 'bg-section'">
            <p class="text-xs font-semibold uppercase tracking-wide" :class="step === 1 ? 'text-primary' : 'text-neutral-400'">Step 1</p>
            <p class="mt-1 text-sm font-semibold text-title">Choose provider</p>
          </div>
          <div class="rounded-xl p-4" :class="step === 2 ? 'bg-primary/10 text-primary' : 'bg-section'">
            <p class="text-xs font-semibold uppercase tracking-wide" :class="step === 2 ? 'text-primary' : 'text-neutral-400'">Step 2</p>
            <p class="mt-1 text-sm font-semibold text-title">Add credentials</p>
          </div>
          <div class="rounded-xl p-4" :class="step === 2 ? 'bg-primary/10 text-primary' : 'bg-section'">
            <p class="text-xs font-semibold uppercase tracking-wide" :class="step === 2 ? 'text-primary' : 'text-neutral-400'">Step 3</p>
            <p class="mt-1 text-sm font-semibold text-title">Save channel</p>
          </div>
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2" x-show="step === 1" x-cloak>
          @foreach ($connectableChannelProviders as $providerKey => $provider)
            @php $isConnected = $channelsByProvider->has($providerKey); @endphp
            <button type="button" class="rounded-xl border border-neutral-100 p-4 text-left transition hover:border-primary/40 hover:bg-primary/5" @click="selectProvider('{{ $providerKey }}')">
              <span class="flex items-start gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                  <i class="ph {{ $provider['icon'] }} text-2xl"></i>
                </span>
                <span class="min-w-0">
                  <span class="flex flex-wrap items-center gap-2">
                    <span class="font-title text-sm font-bold text-title">{{ $provider['label'] }}</span>
                    <span class="badge {{ $isConnected ? 'badge-success' : 'badge-soft' }}">{{ $isConnected ? 'Connected' : 'Available' }}</span>
                  </span>
                  <span class="mt-1 block text-xs text-body">{{ $provider['description'] }}</span>
                </span>
              </span>
            </button>
          @endforeach
        </div>

        <div x-show="step === 2 && selectedProvider" x-cloak>
          <div class="mt-5 flex items-center justify-between gap-3">
            <button type="button" class="btn-sm btn-outline" @click="backToProviders()">
              <i class="ph ph-arrow-left text-base"></i>
              Back
            </button>
            <div class="flex min-w-0 items-center gap-3">
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                <i class="ph" :class="providerMeta()?.icon || ''"></i>
              </span>
              <div class="min-w-0 text-right">
                <p class="truncate text-sm font-semibold text-title" x-text="providerMeta()?.title || ''"></p>
                <p class="truncate text-xs text-body" x-text="connectedProvider() ? 'Saved channel will be updated' : 'New channel will be created'"></p>
              </div>
            </div>
          </div>

          <div class="mt-5 rounded-xl border border-neutral-100 bg-section p-4" x-show="providerMeta()?.webhook_required" x-cloak>
            <p class="form-label">Provider webhook URL</p>
            <p class="form-hint" x-text="providerMeta()?.webhook_hint || ''"></p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
              <div class="code-box min-w-0 flex-1 basis-56" x-text="providerWebhook()"></div>
              <button type="button" class="btn-sm btn-outline" :data-copy="providerWebhook()">
                <i class="ph ph-copy text-base"></i>
                <span data-copy-label>Copy</span>
              </button>
            </div>
          </div>

          <div x-show="selectedProvider === 'whatsapp'" x-cloak>
            <div class="mt-5 rounded-xl border border-neutral-100 bg-section p-4">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-bold text-title">Connect with Meta</p>
                    @if ($embeddedSignup['enabled'])
                      <span class="badge badge-soft text-primary">Recommended</span>
                    @else
                      <span class="badge badge-warning">Admin setup required</span>
                    @endif
                  </div>
                  <p class="mt-1 text-sm text-body">Use Meta Embedded Signup so each workspace owner connects their own WhatsApp Business account through the official flow.</p>
                </div>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                  <i class="ph ph-facebook-logo text-xl"></i>
                </span>
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
                <div class="mt-4 flex flex-wrap items-center gap-3">
                  <button
                    type="button"
                    class="btn btn-primary"
                    data-whatsapp-embedded-signup
                    data-app-id="{{ $embeddedSignup['app_id'] }}"
                    data-config-id="{{ $embeddedSignup['config_id'] }}"
                    data-graph-api-version="{{ $embeddedSignup['graph_api_version'] }}"
                  >
                    <i class="ph ph-facebook-logo text-base"></i>
                    Connect with Meta
                  </button>
                  <p class="text-xs font-medium text-body" data-whatsapp-embedded-status>Meta will ask you to choose a Business Manager, WABA, and phone number.</p>
                </div>
              @else
                <div class="mt-4 rounded-xl border border-warning/20 bg-warning/10 p-3 text-sm font-medium text-warning">
                  Ask an administrator to enable Embedded Signup and add the Meta App ID, App Secret, and Configuration ID.
                </div>
              @endif
            </div>

            <form class="mt-5 space-y-4" method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.store') }}">
              @csrf
              <div class="flex items-center gap-3">
                <div class="h-px flex-1 bg-neutral-100"></div>
                <span class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Manual setup</span>
                <div class="h-px flex-1 bg-neutral-100"></div>
              </div>
              <div>
                <label class="form-label" for="wizard_name">Channel Name</label>
                <input id="wizard_name" name="name" type="text" class="form-input mt-1" placeholder="e.g. WaPro Coffee Co." value="{{ old('name', $channel?->name ?? 'WhatsApp Business') }}" required />
                <p class="form-hint mt-1">Shown in the inbox and channel list.</p>
              </div>
              <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                  <span class="form-label">WhatsApp Business Account ID</span>
                  <input name="waba_id" type="text" inputmode="numeric" class="form-input mt-1" placeholder="e.g. 102938475610293" value="{{ old('waba_id', $displayWabaId) }}" required />
                </label>
                <label class="block">
                  <span class="form-label">Business ID</span>
                  <input name="business_id" type="text" inputmode="numeric" class="form-input mt-1" placeholder="e.g. 784512309876543" value="{{ old('business_id', $displayBusinessId) }}" required />
                </label>
              </div>
              <div>
                <label class="form-label" for="wizard_phone_number_id">Phone Number ID</label>
                <input id="wizard_phone_number_id" name="phone_number_id" type="text" inputmode="numeric" class="form-input mt-1" placeholder="e.g. 1069382741050193" value="{{ old('phone_number_id', $displayPhoneNumberId) }}" required />
              </div>
              <div>
                <label class="form-label" for="wizard_access_token">Permanent Access Token</label>
                <p class="form-hint">Generate a System User token with <code class="text-primary">whatsapp_business_management</code> and <code class="text-primary">whatsapp_business_messaging</code> permissions.</p>
                <input id="wizard_access_token" name="access_token" type="password" class="form-input mt-1" placeholder="EAAxxxxxxxxxxxxxxxx" required autocomplete="off" />
              </div>
              <div>
                <label class="form-label" for="wizard_webhook_verify_token">Webhook Verify Token</label>
                <input id="wizard_webhook_verify_token" name="webhook_verify_token" type="text" class="form-input mt-1" placeholder="Choose a private random token" value="{{ old('webhook_verify_token', $displayVerifyToken) }}" required />
              </div>
              <div class="pt-1">
                <button type="submit" class="btn btn-primary w-full justify-center">
                  <i class="ph ph-whatsapp-logo text-base"></i>
                  {{ $channelsByProvider->has('whatsapp') ? 'Update WhatsApp' : 'Connect WhatsApp' }}
                </button>
              </div>
            </form>
          </div>

          @foreach ($connectableChannelProviders as $providerKey => $provider)
            @continue($providerKey === 'whatsapp')
            @php
              $connectedProviderChannel = $channelsByProvider->get($providerKey);
            @endphp
            <div x-show="selectedProvider === '{{ $providerKey }}'" x-cloak>
              <form class="mt-5 space-y-4" method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.store-generic') }}">
                @csrf
                <input type="hidden" name="provider" value="{{ $providerKey }}">
                <div class="grid gap-4 sm:grid-cols-2">
                  @foreach ($provider['fields'] as $field)
                    @php
                      $type = $field['type'] ?? 'text';
                      $fieldName = $field['name'];
                      $fieldValue = old($field['name']);
                      if ($fieldValue === null && $connectedProviderChannel) {
                          $fieldValue = match ($field['name']) {
                              'name' => $connectedProviderChannel->name,
                              'provider_account_id' => $connectedProviderChannel->provider_account_id,
                              'provider_display_id' => $connectedProviderChannel->provider_display_id,
                              default => $connectedProviderChannel->credential($field['name'], $field['default'] ?? null),
                          };
                      }
                      if ($fieldValue === null && array_key_exists('default', $field)) {
                          $fieldValue = $field['default'];
                      }
                      $showWhen = $field['show_when'] ?? null;
                      $showField = is_array($showWhen) ? array_key_first($showWhen) : null;
                      $showValue = $showField ? (string) $showWhen[$showField] : null;
                      $showCondition = $showField ? "providerSelections['{$providerKey}']['{$showField}'] === '{$showValue}'" : null;
                      $isProviderSelector = ($provider['credential_provider_field'] ?? null) === $fieldName;
                    @endphp
                    <label
                      class="block {{ in_array($field['name'], ['access_token', 'mail_password', 'mailgun_secret', 'twilio_auth_token', 'vonage_api_secret'], true) ? 'sm:col-span-2' : '' }}"
                      @if($showCondition) x-show="{{ $showCondition }}" x-cloak @endif
                    >
                      <span class="form-label">{{ $field['label'] }}</span>
                      @if ($type === 'select')
                        <select
                          class="form-input mt-1"
                          name="{{ $fieldName }}"
                          @if($isProviderSelector) x-model="providerSelections['{{ $providerKey }}']['{{ $fieldName }}']" @endif
                          @if($showCondition) :disabled="!({{ $showCondition }})" @endif
                          @if($field['required'] ?? false)
                            @if($showCondition) :required="{{ $showCondition }}" @else required @endif
                          @endif
                        >
                          @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                            <option value="{{ $optionValue }}" @selected((string) $fieldValue === (string) $optionValue)>{{ $optionLabel }}</option>
                          @endforeach
                        </select>
                      @else
                        <input
                          class="form-input mt-1"
                          name="{{ $fieldName }}"
                          type="{{ $type }}"
                          placeholder="{{ $field['placeholder'] ?? (($field['secret'] ?? false) && filled($connectedProviderChannel?->credential($fieldName)) ? 'Leave blank to keep saved value' : '') }}"
                          value="{{ $type === 'password' ? '' : $fieldValue }}"
                          @if($showCondition) :disabled="!({{ $showCondition }})" @endif
                          @if($field['required'] ?? false)
                            @if($showCondition) :required="{{ $showCondition }}" @else required @endif
                          @endif
                          autocomplete="off"
                        >
                      @endif
                    </label>
                  @endforeach
                </div>
                @if ($provider['webhook_required'] ?? false)
                  <div>
                    <label class="form-label">Webhook Verify Token</label>
                    <input name="webhook_verify_token" type="text" class="form-input mt-1" placeholder="Choose a private random token" value="{{ old('webhook_verify_token', $connectedProviderChannel?->webhook_verify_token) }}">
                    <p class="form-hint mt-1">Leave blank to generate one automatically.</p>
                  </div>
                @endif
                <div class="pt-1">
                  <button type="submit" class="btn btn-primary w-full justify-center">
                    <i class="ph {{ $provider['icon'] }} text-base"></i>
                    {{ $connectedProviderChannel ? 'Update '.$provider['label'] : 'Connect '.$provider['label'] }}
                  </button>
                </div>
              </form>
            </div>
          @endforeach
        </div>
        </div>
      </div>
    </div>
    @endpush

    @push('modals')
    @foreach ($allChannels as $connectedChannel)
      @php
        $provider = $channelProviders[$connectedChannel->provider] ?? null;
        $settings = $connectedChannel->settings ?? [];
        $webhook = $connectedChannel->webhook_url ?? ($webhookUrls[$connectedChannel->provider] ?? '');
      @endphp
      @continue(! $provider)
      <div class="modal" id="channelDetails{{ $connectedChannel->id }}" data-modal>
        <div class="modal__backdrop" data-modal-close></div>
        <div class="modal__panel max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="channelDetails{{ $connectedChannel->id }}Title">
          <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
              <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                <i class="ph {{ $provider['icon'] }} text-2xl"></i>
              </span>
              <div class="min-w-0">
                <h3 id="channelDetails{{ $connectedChannel->id }}Title" class="heading-4 truncate">{{ $connectedChannel->name }}</h3>
                <p class="mt-1 text-sm text-body">{{ $provider['title'] }}</p>
              </div>
            </div>
            <button type="button" class="row-action" data-modal-close aria-label="Close">
              <i class="ph ph-x text-base"></i>
            </button>
          </div>

          <div class="mt-5 grid gap-4 rounded-xl bg-section p-4 sm:grid-cols-2">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Provider</p>
              <p class="mt-1 text-sm font-semibold text-title">{{ $provider['label'] }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Status</p>
              <p class="mt-1 text-sm font-semibold text-title">{{ ucfirst($connectedChannel->status?->value ?? 'draft') }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $provider['identifier_label'] }}</p>
              <p class="mt-1 font-mono text-sm font-semibold text-title">{{ $connectedChannel->provider_phone_id ?? $connectedChannel->provider_account_id ?? 'Not set' }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $provider['display_label'] }}</p>
              <p class="mt-1 text-sm font-semibold text-title">{{ $connectedChannel->provider_display_id ?? $connectedChannel->name }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Connected</p>
              <p class="mt-1 text-sm font-semibold text-title">{{ $connectedChannel->connected_at?->diffForHumans() ?? 'Not connected' }}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Last synced</p>
              <p class="mt-1 text-sm font-semibold text-title">{{ $connectedChannel->last_synced_at?->diffForHumans() ?? 'Not synced' }}</p>
            </div>
          </div>

          @if ($provider['webhook_required'] ?? false)
            <div class="mt-5">
              <p class="form-label">Webhook URL</p>
              <div class="mt-2 flex flex-wrap items-center gap-2">
                <div class="code-box min-w-0 flex-1 basis-56">{{ $webhook }}</div>
                <button type="button" class="btn-sm btn-outline" data-copy="{{ $webhook }}">
                  <i class="ph ph-copy text-base"></i>
                  <span data-copy-label>Copy</span>
                </button>
              </div>
            </div>

            <div class="mt-5">
              <p class="form-label">Webhook Verify Token</p>
              <div class="mt-2 flex flex-wrap items-center gap-2">
                <div class="code-box min-w-0 flex-1 basis-56">{{ $connectedChannel->webhook_verify_token ?? 'Not set' }}</div>
                <button type="button" class="btn-sm btn-outline" data-copy="{{ $connectedChannel->webhook_verify_token ?? '' }}">
                  <i class="ph ph-copy text-base"></i>
                  <span data-copy-label>Copy</span>
                </button>
              </div>
            </div>
          @endif

          <div class="mt-5 flex flex-wrap gap-2">
            @foreach (($provider['capabilities'] ?? []) as $capability)
              <span class="badge badge-soft">{{ $capability }}</span>
            @endforeach
            @if (($settings['manual_setup'] ?? false) === true)
              <span class="badge badge-warning">Manual setup</span>
            @endif
          </div>

          @if (filled($settings['last_error'] ?? null))
            <div class="mt-5 rounded-xl border border-error/20 bg-error/5 p-4 text-sm font-medium text-error">
              {{ $settings['last_error'] }}
            </div>
          @endif

          <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-4">
            @if (($provider['connect_mode'] ?? null) !== 'internal')
              <button type="button" class="btn-sm btn-outline" data-modal-close data-drawer-trigger="channelSettingsDrawer" data-drawer-provider="{{ $connectedChannel->provider }}">
                <i class="ph ph-pencil-simple text-base"></i>
                Edit
              </button>
            @endif
            <div class="flex flex-wrap items-center gap-2">
              @if (in_array($connectedChannel->provider, $testableProviders ?? [], true))
                <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.test-channel', $connectedChannel) }}">
                  @csrf
                  <button type="submit" class="btn-sm btn-outline">
                    <i class="ph ph-plugs-connected text-base"></i>
                    Test Connection
                  </button>
                </form>
              @endif
              <form method="POST" action="{{ route('user.whatsapp-cloud.channel-setup.disconnect-channel', $connectedChannel) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-sm btn-outline text-error">
                  <i class="ph ph-plugs text-base"></i>
                  Disconnect
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    @endforeach
    @endpush

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

              if (payload.event === 'FINISH' || payload.event === 'FINISH_ONLY_WABA') {
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
              setStatus('Opening Meta Embedded Signup...');

              window.FB.login((response) => {
                if (response.authResponse?.code) {
                  pendingCode = response.authResponse.code;
                  window.setTimeout(submit, 800);

                  return;
                }

                if (response.status !== 'connected') {
                  setStatus('Meta login was not completed. No credentials were saved.', true);
                }
              }, {
                config_id: trigger.dataset.configId,
                response_type: 'code',
                override_default_response_type: true,
                extras: {
                  feature: 'whatsapp_embedded_signup',
                  sessionInfoVersion: '3',
                  setup: {},
                },
              });
            });
          });
        </script>
      @endpush
    @endif
</div>
</x-layouts.user>
