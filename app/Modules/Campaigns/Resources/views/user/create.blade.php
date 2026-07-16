<x-layouts.user :title="__($campaign ?? null ? 'Edit Campaign' : 'New Campaign')">
    @php
        $channels = $channels ?? collect();
        $templates = $templates ?? collect();
        $automations = $automations ?? collect();
        $contacts = $contacts ?? collect();
        $tags = $tags ?? collect();
        $groups = $groups ?? collect();
        $canUseCampaignDoctor = (bool) ($canUseCampaignDoctor ?? false);
        $campaign = $campaign ?? null;
        $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
        $isEdit = $campaign !== null;
        $initialChannel = old('channel_account_id', $campaign?->channel_account_id);
        $initialMode = old('message_type', $campaign?->message_type ?? ($campaign?->message_template_id ? 'template' : 'custom'));
        $initialAudience = old('audience_type', in_array($campaign?->audience_type, ['contacts', 'groups', 'tags'], true) ? $campaign?->audience_type : 'groups');
        $initialSchedule = old('schedule', $campaign?->status?->value === 'scheduled' ? 'later' : ($campaign?->status?->value === 'draft' ? 'draft' : 'now'));
        $channelOptions = $channels->map(fn ($channel) => [
            'id' => $channel->id,
            'provider' => $channel->provider,
            'provider_label' => config("marketing-channels.providers.{$channel->provider}.label", ucfirst($channel->provider)),
            'name' => $channel->name,
            'display' => $channel->provider_display_id,
        ])->values();
        $templateOptions = $templates->map(fn ($template) => [
            'id' => $template->id,
            'provider' => $template->provider,
            'name' => $template->name,
            'category' => ucfirst((string) $template->category),
            'language' => $template->language ?? 'en_US',
            'body' => (string) data_get(collect($template->components ?? [])->firstWhere('type', 'BODY'), 'text', ''),
        ])->values();
        $automationOptions = $automations->map(fn ($automation) => [
            'id' => $automation->id,
            'name' => $automation->name,
            'description' => $automation->description,
            'active' => (bool) $automation->is_active,
        ])->values();
        $contactOptions = $contacts->map(fn ($contact) => [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
        ])->values();
        $campaignSteps = [
            1 => ['label' => __('Setup'), 'icon' => 'ph-gear-six'],
            2 => ['label' => __('Compose'), 'icon' => 'ph-chat-circle-text'],
            3 => ['label' => __('Recipients'), 'icon' => 'ph-users-three'],
            4 => ['label' => __('Schedule'), 'icon' => 'ph-calendar-check'],
            5 => ['label' => __('Review'), 'icon' => 'ph-clipboard-text'],
        ];
    @endphp

    <div class="flex items-center gap-3">
        <a href="{{ route('user.campaigns.index') }}" class="row-action" aria-label="Back to campaigns">
            <i class="ph ph-arrow-left text-lg"></i>
        </a>
        <div>
            <h2 class="heading-2">{{ $isEdit ? __('Edit Campaign') : __('New Campaign') }}</h2>
            <p class="m-text mt-1">{{ __('Build a guided broadcast from one connected sender.') }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ $isEdit ? route('user.campaigns.update', $campaign) : route('user.campaigns.store') }}"
        class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]"
        x-ref="campaignForm"
        x-data="campaignBuilder({
            doctorUrl: @js(route('user.campaigns.doctor')),
            canUseCampaignDoctor: @js($canUseCampaignDoctor),
            channels: @js($channelOptions),
            templates: @js($templateOptions),
            automations: @js($automationOptions),
            contacts: @js($contactOptions),
            initialChannel: @js((string) $initialChannel),
            initialMode: @js($initialMode),
            initialSchedule: @js($initialSchedule),
            initialBody: @js(old('message_body', $campaign?->message_body ?? '')),
            initialSubject: @js(old('message_subject', $campaign?->message_subject ?? '')),
            initialAudience: @js($initialAudience)
        })"
        x-init="init()"
        x-effect="if (!['whatsapp', 'telegram'].includes(provider) && messageType === 'template') messageType = 'custom'; if (messageType === 'template' && selectedTemplate && selectedTemplate.provider !== provider) templateId = ''"
    >
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="space-y-6">
            <div class="app-card p-4" data-campaign-stepper>
                <div class="relative">
                    <div class="relative grid gap-3 text-sm sm:grid-cols-5">
                        @foreach ($campaignSteps as $step => $stepMeta)
                        <button
                            type="button"
                            class="group relative flex items-center gap-3 rounded-xl px-3 py-2 text-left transition sm:flex-col sm:justify-start sm:gap-2 sm:px-2"
                            :class="step === {{ $step }} ? 'text-primary' : (step > {{ $step }} ? 'text-title hover:bg-section' : 'text-neutral-400 hover:bg-section hover:text-title')"
                            :aria-current="step === {{ $step }} ? 'step' : null"
                            aria-label="{{ __('Go to :label', ['label' => $stepMeta['label']]) }}"
                            @click="goTo({{ $step }})"
                        >
                            @if (! $loop->last)
                                <span
                                    class="pointer-events-none absolute left-[calc(50%+1.25rem)] right-[calc(-50%+1.25rem)] top-7 z-0 hidden h-px transition-colors sm:block"
                                    :class="step > {{ $step }} ? 'bg-primary' : 'bg-neutral-200'"
                                    aria-hidden="true"
                                ></span>
                            @endif
                            <span
                                class="relative z-10 grid h-10 w-10 shrink-0 place-items-center rounded-full border bg-neutral-0 transition"
                                :class="step === {{ $step }} ? 'border-primary bg-primary text-white shadow-sm shadow-primary/20' : (step > {{ $step }} ? 'border-primary bg-primary/10 text-primary' : 'border-border text-neutral-400 group-hover:border-primary/40 group-hover:text-primary')"
                            >
                                <i class="ph {{ $stepMeta['icon'] }} text-lg" x-show="step <= {{ $step }}"></i>
                                <i class="ph ph-check text-lg" x-show="step > {{ $step }}" x-cloak></i>
                            </span>
                            <span class="font-semibold leading-tight">{{ $stepMeta['label'] }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <section class="app-card p-5 sm:p-6" x-show="step === 1" x-cloak>
                <div class="flex items-start justify-between gap-3">
                    <div class="f-start gap-2.5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                            <i class="ph ph-gear-six text-lg"></i>
                        </span>
                        <h3 class="heading-4">{{ __('Setup') }}</h3>
                    </div>
                    <span class="badge badge-soft">{{ __('Required') }}</span>
                </div>

                <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                    <div>
                        <label for="name" class="form-label">{{ __('Campaign name') }} <span class="text-error">*</span></label>
                        <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $campaign?->name) }}" placeholder="{{ __('e.g. July winback broadcast') }}">
                        <p class="form-hint">{{ __('Internal label for reports and queue history.') }}</p>
                    </div>

                    <div>
                        <span class="form-label">{{ __('Sender channel') }} <span class="text-error">*</span></span>
                        <div class="mt-2 grid gap-3">
                            @forelse ($channelOptions as $channel)
                                <label class="check-row cursor-pointer border border-border/80 p-3 transition hover:border-primary/40" :class="channelId == '{{ $channel['id'] }}' ? 'border-primary bg-primary/5' : ''">
                                    <input
                                        type="radio"
                                        name="channel_account_id"
                                        value="{{ $channel['id'] }}"
                                        class="app-radio"
                                        x-model="channelId"
                                        @checked((string) $initialChannel === (string) $channel['id'])
                                    >
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-section text-primary">
                                        <i class="ph {{ match ($channel['provider']) {
                                            'email' => 'ph-envelope-simple',
                                            'sms' => 'ph-chat-text',
                                            'telegram' => 'ph-paper-plane-tilt',
                                            default => 'ph-whatsapp-logo',
                                        } }} text-xl"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-title">{{ $channel['name'] }}</span>
                                            <span class="badge badge-soft">{{ $channel['provider_label'] }}</span>
                                        </span>
                                        <span class="mt-0.5 block truncate text-xs text-neutral-400">{{ $channel['display'] ?: __('No display id') }}</span>
                                    </span>
                                </label>
                            @empty
                                <div class="rounded-xl border border-dashed border-border p-4 text-sm text-neutral-500">
                                    {{ __('No connected campaign sender is available yet.') }}
                                    <a href="{{ route('user.whatsapp-cloud.channel-setup') }}" class="font-semibold text-primary">{{ __('Connect a channel') }}</a>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-card p-5 sm:p-6" x-show="step === 2" x-cloak>
                <div class="f-start gap-2.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-chat-circle-text text-lg"></i>
                    </span>
                    <h3 class="heading-4">{{ __('Compose') }}</h3>
                </div>

                <input type="hidden" name="type" value="broadcast">

                <div class="mt-5 grid items-stretch gap-3 md:grid-cols-3">
                    <label class="radio-card h-full min-h-[4ram] items-start p-2" :class="messageType === 'custom' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="message_type" value="custom" x-model="messageType">
                        <span class="grid gap-2">
                            <i class="ph ph-chat-circle-text text-2xl text-primary"></i>
                            <span class="font-semibold text-title">{{ __('Custom message') }}</span>
                            <span class="text-xs text-neutral-400">{{ __('Write a one-off broadcast body.') }}</span>
                        </span>
                    </label>

                    <label class="radio-card h-full min-h-[4ram] items-start p-2" x-show="['whatsapp', 'telegram'].includes(provider)" :class="messageType === 'template' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="message_type" value="template" x-model="messageType">
                        <span class="grid gap-2">
                            <i class="ph ph-notepad text-2xl text-primary"></i>
                            <span class="font-semibold text-title">{{ __('Template') }}</span>
                            <span class="text-xs text-neutral-400">{{ __('Use a saved sender template.') }}</span>
                        </span>
                    </label>

                    <label class="radio-card h-full min-h-[4ram] items-start p-2" :class="messageType === 'automation' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="message_type" value="automation" x-model="messageType">
                        <span class="grid gap-2">
                            <i class="ph ph-flow-arrow text-2xl text-primary"></i>
                            <span class="font-semibold text-title">{{ __('Automation flow') }}</span>
                            <span class="text-xs text-neutral-400">{{ __('Store a saved flow for this campaign.') }}</span>
                        </span>
                    </label>
                </div>

                <div class="mt-5 space-y-4" x-show="messageType === 'custom'">
                    <div x-show="provider === 'email'">
                        <label for="message_subject" class="form-label">{{ __('Subject') }} <span class="text-error">*</span></label>
                        <input id="message_subject" name="message_subject" type="text" class="form-input" x-model="subject" value="{{ old('message_subject', $campaign?->message_subject) }}" placeholder="{{ __('Short subject line') }}">
                    </div>

                    <div>
                        <label for="message_body" class="form-label">{{ __('Message body') }} <span class="text-error">*</span></label>
                        <textarea id="message_body" name="message_body" rows="7" class="form-input" x-model="body" placeholder="{{ __('Write the campaign message...') }}">{{ old('message_body', $campaign?->message_body) }}</textarea>
                        <p class="form-hint" x-show="provider === 'sms'">{{ __('Plain text is recommended for SMS. Placeholders like name and phone are supported.') }}</p>
                        <p class="form-hint" x-show="provider === 'telegram'">{{ __('Telegram campaigns send only to contacts who have started your bot or shared their Telegram contact.') }}</p>
                        <p class="form-hint" x-show="provider === 'email'">{{ __('Email content supports HTML and an unsubscribe footer by default.') }}</p>
                        <p class="form-hint" x-show="provider === 'whatsapp'">{{ __('Custom WhatsApp sends use a text message payload.') }}</p>
                    </div>

                    <div class="flex items-center gap-2.5" x-show="provider === 'email'">
                        <input id="disable_unsubscribe" name="settings[disable_unsubscribe]" type="checkbox" value="1" class="app-checkbox" @checked(old('settings.disable_unsubscribe', $campaign?->settings['disable_unsubscribe'] ?? false))>
                        <label for="disable_unsubscribe" class="text-sm text-body">{{ __('Disable unsubscribe footer') }}</label>
                    </div>
                </div>

                <div class="mt-5" x-show="messageType === 'template' && ['whatsapp', 'telegram'].includes(provider)">
                    <label for="message_template_id" class="form-label"><span x-text="provider === 'telegram' ? '{{ __('Telegram template') }}' : '{{ __('WhatsApp template') }}'"></span> <span class="text-error">*</span></label>
                    <select id="message_template_id" name="message_template_id" class="form-input" x-model="templateId">
                        <option value="">{{ __('Select template...') }}</option>
                        <template x-for="template in providerTemplates" :key="template.id">
                            <option :value="template.id" x-text="`${template.name} · ${template.category} · ${template.language}`"></option>
                        </template>
                    </select>
                </div>

                <div class="mt-5" x-show="messageType === 'automation'">
                    <label for="automation_id" class="form-label">{{ __('Automation flow') }} <span class="text-error">*</span></label>
                    <select id="automation_id" name="automation_id" class="form-input" x-model="automationId">
                        <option value="">{{ __('Select saved automation...') }}</option>
                        @foreach ($automations as $automation)
                            <option value="{{ $automation->id }}" @selected(old('automation_id', $campaign?->automation_id) == $automation->id)>{{ $automation->name }}{{ $automation->is_active ? '' : ' · '.__('Draft') }}</option>
                        @endforeach
                    </select>
                    <p class="form-hint">{{ __('The flow is stored with the campaign. Per-recipient automation execution is not triggered by this builder yet.') }}</p>
                </div>

                <div class="mt-5 rounded-xl border border-neutral-200 bg-section p-4" x-show="provider === 'whatsapp'">
                    <div class="flex items-start gap-3">
                        <input id="crm_create_lead_on_reply" name="settings[crm_create_lead_on_reply]" type="checkbox" value="1" class="app-checkbox mt-0.5" @checked(old('settings.crm_create_lead_on_reply', $campaign?->settings['crm_create_lead_on_reply'] ?? false))>
                        <div>
                            <label for="crm_create_lead_on_reply" class="font-semibold text-title">{{ __('Create a CRM lead when a recipient replies') }}</label>
                            <p class="form-hint mt-1">{{ __('Replies are always marked and tagged. Enable this to create or update the contact’s open CRM opportunity.') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-card p-5 sm:p-6" x-show="step === 3" x-cloak>
                <div class="f-start gap-2.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-users-three text-lg"></i>
                    </span>
                    <h3 class="heading-4">{{ __('Recipients') }}</h3>
                </div>

                <div class="mt-5">
                    <label for="audience_type" class="form-label">{{ __('Audience') }}</label>
                    <select id="audience_type" name="audience_type" class="form-input" x-model="audienceType">
                        <option value="contacts" @selected($initialAudience === 'contacts')>{{ __('Selected contacts') }}</option>
                        <option value="groups" @selected($initialAudience === 'groups')>{{ __('Contact groups') }}</option>
                        <option value="tags" @selected($initialAudience === 'tags')>{{ __('Tags') }}</option>
                    </select>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2" x-show="audienceType === 'groups'">
                    @forelse ($groups as $group)
                        <label class="check-row">
                            <input type="checkbox" name="audience_ids[]" value="{{ $group->id }}" class="app-checkbox" :disabled="audienceType !== 'groups'" @checked(in_array($group->id, (array) old('audience_ids', $campaign?->audience_ids ?? [])))>
                            <span class="min-w-0">
                                <span class="block font-semibold text-title">{{ $group->name }}</span>
                                <span class="block text-xs text-neutral-400">{{ $group->type === 'dynamic' ? __('Dynamic rules') : __('Static contacts') }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="form-hint">{{ __('No groups are available yet.') }}</p>
                    @endforelse
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2" x-show="audienceType === 'tags'">
                    @forelse ($tags as $tag)
                        <label class="check-row">
                            <input type="checkbox" name="audience_ids[]" value="{{ $tag->id }}" class="app-checkbox" :disabled="audienceType !== 'tags'" @checked(in_array($tag->id, (array) old('audience_ids', $campaign?->audience_ids ?? [])))>
                            <span class="min-w-0">
                                <span class="block font-semibold text-title">{{ $tag->name }}</span>
                                <span class="block text-xs text-neutral-400">{{ __('Tagged contacts') }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="form-hint">{{ __('No tags are available yet.') }}</p>
                    @endforelse
                </div>

                <div class="mt-4" x-show="audienceType === 'contacts'">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <p class="form-hint">{{ __('Choose individual contacts for a custom recipient list.') }}</p>
                        <div class="relative w-full sm:w-72">
                            <i class="ph ph-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400"></i>
                            <input type="search" class="form-input pl-9" x-model="contactSearch" placeholder="{{ __('Search contacts...') }}">
                        </div>
                    </div>

                    <div class="grid max-h-[28rem] gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        @forelse ($contacts as $contact)
                            <label class="check-row" data-search="{{ e(strtolower(trim($contact->name.' '.$contact->email.' '.$contact->phone))) }}" x-show="contactVisible($el.dataset.search)">
                                <input type="checkbox" name="audience_ids[]" value="{{ $contact->id }}" class="app-checkbox" :disabled="audienceType !== 'contacts'" @checked(in_array($contact->id, (array) old('audience_ids', $campaign?->audience_ids ?? [])))>
                                <span class="min-w-0">
                                    <span class="block truncate font-semibold text-title">{{ $contact->name ?: __('Unnamed contact') }}</span>
                                    <span class="block truncate text-xs text-neutral-400">{{ $contact->email ?: $contact->phone ?: __('No address') }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="form-hint">{{ __('No contacts are available yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="app-card p-5 sm:p-6" x-show="step === 4" x-cloak>
                <div class="f-start gap-2.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-calendar-check text-lg"></i>
                    </span>
                    <h3 class="heading-4">{{ __('Schedule') }}</h3>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <label class="radio-card p-4" :class="schedule === 'now' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="schedule" value="now" x-model="schedule">
                        <span>{{ __('Send now') }}</span>
                    </label>
                    <label class="radio-card p-4" :class="schedule === 'later' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="schedule" value="later" x-model="schedule">
                        <span>{{ __('Schedule') }}</span>
                    </label>
                    <label class="radio-card p-4" :class="schedule === 'draft' ? 'border-primary bg-primary/5' : ''">
                        <input type="radio" name="schedule" value="draft" x-model="schedule">
                        <span>{{ __('Draft') }}</span>
                    </label>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2" x-show="schedule === 'later'">
                    <div>
                        <label for="send_date" class="form-label">{{ __('Date') }}</label>
                        <input id="send_date" name="send_date" type="date" class="form-input" value="{{ old('send_date', $campaign?->scheduled_at?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label for="send_time" class="form-label">{{ __('Time') }}</label>
                        <input id="send_time" name="send_time" type="time" class="form-input" value="{{ old('send_time', $campaign?->scheduled_at?->format('H:i')) }}">
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="timezone" class="form-label">{{ __('Time zone') }}</label>
                        <select id="timezone" name="timezone" class="form-input">
                            @foreach (DateTimeZone::listIdentifiers() as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', config('app.timezone')) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="throttle" class="form-label">{{ __('Send rate') }}</label>
                        <select id="throttle" name="throttle" class="form-input">
                            <option value="0" @selected(old('throttle', $campaign?->send_rate_per_minute) == 0)>{{ __('As fast as possible') }}</option>
                            <option value="600" @selected(old('throttle', $campaign?->send_rate_per_minute) == 600)>600 / {{ __('minute') }}</option>
                            <option value="300" @selected(old('throttle', $campaign?->send_rate_per_minute) == 300)>300 / {{ __('minute') }}</option>
                            <option value="120" @selected(old('throttle', $campaign?->send_rate_per_minute) == 120)>120 / {{ __('minute') }}</option>
                            <option value="60" @selected(old('throttle', $campaign?->send_rate_per_minute) == 60)>60 / {{ __('minute') }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="app-card p-5 sm:p-6" x-show="step === 5" x-cloak>
                <div class="f-start gap-2.5">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-clipboard-text text-lg"></i>
                    </span>
                    <h3 class="heading-4">{{ __('Review') }}</h3>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-border-soft p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Sender') }}</p>
                        <p class="mt-2 font-semibold text-title" x-text="selectedChannel?.name || '{{ __('Not selected') }}'"></p>
                    </div>
                    <div class="rounded-xl border border-border-soft p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Mode') }}</p>
                        <p class="mt-2 font-semibold text-title" x-text="modeLabel"></p>
                    </div>
                    <div class="rounded-xl border border-border-soft p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Schedule') }}</p>
                        <p class="mt-2 font-semibold text-title" x-text="scheduleLabel"></p>
                    </div>
                </div>

                <div class="mt-5 rounded-xl border border-border-soft p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                    <i class="ph ph-stethoscope text-lg"></i>
                                </span>
                                <div>
                                    <h4 class="font-title text-base font-bold text-title">{{ __('AI Campaign Audit') }}</h4>
                                    <p class="text-sm text-neutral-500">{{ __('Review opt-in, cost, fatigue, wording, and send timing before launch.') }}</p>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-sm btn-primary" @click="runDoctor()" :disabled="doctor.loading || !canUseCampaignDoctor">
                            <i class="ph text-base" :class="doctor.loading ? 'ph-circle-notch animate-spin' : 'ph-sparkle'"></i>
                            <span x-text="!canUseCampaignDoctor ? '{{ __('Upgrade for doctor') }}' : (doctor.loading ? '{{ __('Checking...') }}' : '{{ __('Run Audit') }}')"></span>
                        </button>
                    </div>

                    <div class="mt-4 rounded-lg border border-warning/20 bg-warning/10 p-4" x-show="!canUseCampaignDoctor" x-cloak>`
                        <p class="text-sm font-semibold text-warning">{{ __('AI Campaign Audit is premium') }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ __('Upgrade your subscription plan to unlock opt-in, cost, fatigue, and block-risk checks.') }}</p>
                    </div>

                    <div class="mt-4 rounded-lg border border-dashed border-border bg-section p-4" x-show="canUseCampaignDoctor && !doctor.report && !doctor.error" x-cloak>
                        <p class="text-sm font-semibold text-title">{{ __('No diagnosis yet') }}</p>
                        <p class="m-text mt-1">{{ __('Run the audit from this review step. Findings are warnings only and will not block saving.') }}</p>
                    </div>

                    <div class="mt-4 rounded-lg border border-warning/20 bg-warning/10 p-4" x-show="doctor.error" x-cloak>
                        <p class="text-sm font-semibold text-warning" x-text="doctor.error"></p>
                        <p class="mt-1 text-xs text-neutral-500" x-show="doctor.upgrade">{{ __('Upgrade your subscription plan to unlock campaign risk checks.') }}</p>
                    </div>

                    <div class="mt-4 space-y-4" x-show="doctor.report" x-cloak>
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-section p-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Audit score') }}</p>
                                <p class="mt-1 font-title text-2xl font-extrabold text-title">
                                    <span x-text="doctor.report?.score ?? '-'"></span><span class="text-base text-neutral-400">/100</span>
                                </p>
                            </div>
                            <p class="max-w-xl text-sm font-medium text-body" x-text="doctor.report?.summary"></p>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <template x-for="item in doctor.report?.items || []" :key="item.key">
                                <div class="rounded-lg border p-3" :class="doctorSeverityClasses(item.severity)">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-semibold text-title" x-text="item.label"></p>
                                        <span class="badge" :class="doctorBadgeClasses(item.severity)" x-text="item.severity"></span>
                                    </div>
                                    <p class="mt-2 text-sm text-body" x-text="item.message"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" class="btn btn-outline" @click="previous()" :disabled="step === 1" :class="step === 1 ? 'opacity-50' : ''">
                    <i class="ph ph-arrow-left text-base"></i>
                    {{ __('Previous') }}
                </button>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('user.campaigns.index') }}" class="btn btn-outline">{{ __('Cancel') }}</a>
                    <button type="button" class="btn btn-primary" x-show="step < 5" @click="next()">
                        {{ __('Next') }}
                        <i class="ph ph-arrow-right text-base"></i>
                    </button>
                    <button type="submit" class="btn btn-primary" x-show="step === 5">
                        <i class="ph ph-paper-plane-tilt text-base"></i>
                        {{ $isEdit ? __('Update Campaign') : __('Save Campaign') }}
                    </button>
                </div>
            </div>
        </div>

        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
            <div class="app-card overflow-hidden">
                <div class="px-5 pt-4 pb-2">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-neutral-400">{{ __('Live summary') }}</p>
                </div>
                <div class="space-y-4 p-5">
                    <div class="rounded-2xl bg-section p-4">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="grid h-9 w-9 place-items-center rounded-full bg-primary/10 text-primary">
                                <i class="ph ph-broadcast text-lg"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-title" x-text="selectedChannel?.name || '{{ __('Choose a sender') }}'"></p>
                                <p class="text-xs text-neutral-400" x-text="selectedChannel ? `${selectedChannel.provider_label} · ${selectedChannel.display || '{{ __('No display id') }}'}` : '{{ __('Provider is inferred from sender') }}'"></p>
                            </div>
                        </div>
                        <div class="template-card__bubble">
                            <div class="template-card__body whitespace-pre-line" x-text="previewText"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="min-h-[5rem] rounded-xl border border-border-soft p-3">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Recipients') }}</p>
                            <p class="mt-1 font-title font-semibold text-title">{{ __('Counted') }}</p>
                            <p class="text-xs text-neutral-400">{{ __('on launch') }}</p>
                        </div>
                        <div class="min-h-[5rem] rounded-xl border border-border-soft p-3">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Mode') }}</p>
                            <p class="mt-1 font-semibold text-title" x-text="modeLabel"></p>
                        </div>
                        <div class="min-h-[5rem] rounded-xl border border-border-soft p-3">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Audience') }}</p>
                            <p class="mt-1 font-semibold text-title capitalize" x-text="audienceType"></p>
                        </div>
                        <div class="min-h-[5rem] rounded-xl border border-border-soft p-3">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Schedule') }}</p>
                            <p class="mt-1 font-semibold text-title" x-text="scheduleLabel"></p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </form>

    @push('scripts')
        <script>
            function campaignBuilder(config) {
                return {
                    doctorUrl: config.doctorUrl,
                    canUseCampaignDoctor: config.canUseCampaignDoctor,
                    channels: config.channels,
                    templates: config.templates,
                    automations: config.automations,
                    contacts: config.contacts,
                    doctor: {
                        loading: false,
                        report: null,
                        error: '',
                        upgrade: false,
                    },
                    step: 1,
                    channelId: config.initialChannel,
                    messageType: config.initialMode || 'custom',
                    schedule: config.initialSchedule || 'now',
                    body: config.initialBody || '',
                    subject: config.initialSubject || '',
                    audienceType: config.initialAudience || 'groups',
                    contactSearch: '',
                    templateId: @js((string) old('message_template_id', $campaign?->message_template_id ?? '')),
                    automationId: @js((string) old('automation_id', $campaign?->automation_id ?? '')),
                    init() {
                        if (! this.channelId && this.channels.length > 0) {
                            this.channelId = String(this.channels[0].id);
                        }
                    },
                    goTo(step) {
                        this.step = Math.min(5, Math.max(1, step));
                    },
                    next() {
                        this.goTo(this.step + 1);
                    },
                    previous() {
                        this.goTo(this.step - 1);
                    },
                    async runDoctor() {
                        if (! this.canUseCampaignDoctor) {
                            this.doctor.error = '{{ __('AI Campaign Doctor is available on premium plans.') }}';
                            this.doctor.upgrade = true;
                            return;
                        }

                        this.doctor.loading = true;
                        this.doctor.error = '';
                        this.doctor.upgrade = false;

                        try {
                            const formData = new FormData(this.$refs.campaignForm);
                            formData.delete('_method');

                            const response = await fetch(this.doctorUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': formData.get('_token'),
                                },
                                body: formData,
                            });
                            const payload = await response.json();

                            if (!response.ok) {
                                this.doctor.report = null;
                                this.doctor.upgrade = response.status === 403;
                                this.doctor.error = payload.summary || payload.message || '{{ __('AI Campaign Doctor is not available for this plan.') }}';
                                return;
                            }

                            this.doctor.report = payload;
                        } catch (error) {
                            this.doctor.report = null;
                            this.doctor.error = error?.message || '{{ __('Campaign doctor check failed. Please try again.') }}';
                        } finally {
                            this.doctor.loading = false;
                        }
                    },
                    doctorSeverityClasses(severity) {
                        if (severity === 'high') return 'border-error/30 bg-error/10';
                        if (severity === 'medium') return 'border-warning/30 bg-warning/10';
                        if (severity === 'success') return 'border-success/30 bg-success/10';
                        return 'border-border-soft bg-section';
                    },
                    doctorBadgeClasses(severity) {
                        if (severity === 'high') return 'badge-error';
                        if (severity === 'medium') return 'badge-warning';
                        if (severity === 'success') return 'badge-success';
                        return 'badge-soft';
                    },
                    contactVisible(haystack) {
                        return ! this.contactSearch || haystack.includes(this.contactSearch.toLowerCase());
                    },
                    get selectedChannel() {
                        return this.channels.find((channel) => String(channel.id) === String(this.channelId));
                    },
                    get provider() {
                        return this.selectedChannel?.provider || '';
                    },
                    get selectedTemplate() {
                        return this.templates.find((template) => String(template.id) === String(this.templateId));
                    },
                    get providerTemplates() {
                        return this.templates.filter((template) => template.provider === this.provider);
                    },
                    get selectedAutomation() {
                        return this.automations.find((automation) => String(automation.id) === String(this.automationId));
                    },
                    get modeLabel() {
                        if (this.messageType === 'template') return '{{ __('Template') }}';
                        if (this.messageType === 'automation') return '{{ __('Automation') }}';
                        return '{{ __('Custom') }}';
                    },
                    get scheduleLabel() {
                        if (this.schedule === 'later') return '{{ __('Later') }}';
                        if (this.schedule === 'draft') return '{{ __('Draft') }}';
                        return '{{ __('Now') }}';
                    },
                    get previewText() {
                        if (this.messageType === 'template') {
                            return this.selectedTemplate ? (this.selectedTemplate.body || `${this.selectedTemplate.name} · ${this.selectedTemplate.language}`) : '{{ __('Select a template to preview it here.') }}';
                        }

                        if (this.messageType === 'automation') {
                            return this.selectedAutomation ? `${this.selectedAutomation.name}\n{{ __('Automation flow stored with this campaign.') }}` : '{{ __('Select an automation flow to attach it here.') }}';
                        }

                        return this.body || '{{ __('Write a message body to preview it here.') }}';
                    }
                };
            }
        </script>
    @endpush
</x-layouts.user>
