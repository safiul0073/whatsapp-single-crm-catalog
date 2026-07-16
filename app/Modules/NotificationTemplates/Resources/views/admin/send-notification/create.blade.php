<x-layouts.admin :title="__('Send Notification')">
    @php
        $templateOptions = $templates->map(fn ($template) => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'channels' => $template->channels ?? [],
            'variables' => $template->variables ?? [],
            'email_subject' => $template->email_subject,
            'email_body' => $template->email_body,
            'sms_body' => $template->sms_body,
        ])->values();
        $nameTag = '{{name}}';
        $emailTag = '{{email}}';
        $userTag = '{{user}}';
    @endphp

    <div
        x-data="sendNotificationPage({
            templates: {{ Js::from($templateOptions) }},
            oldChannel: {{ Js::from(old('channel', 'email')) }},
            oldTemplateId: {{ Js::from(old('template_id')) }},
            oldRecipientType: {{ Js::from(old('recipient_type', 'all_admins')) }},
            channelAvailability: {{ Js::from($channelAvailability) }},
        })"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Send Notification') }}</h1>
                <p class="mt-1 text-sm text-neutral-400">
                    {{ __('Send email or SMS notifications using an existing template or a custom message.') }}
                </p>
            </div>

            <x-ui.button variant="outline" href="{{ route('admin.system-notifications.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back to System Notifications') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ route('admin.notification-send.store') }}" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            @csrf

            <div class="space-y-6 xl:col-span-2">
                <div class="section-card space-y-6">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <x-forms.select
                            :label="__('Recipients')"
                            name="recipient_type"
                            :selected="old('recipient_type', 'all_admins')"
                            x-model="recipientType"
                            required
                        >
                            <option value="all_admins">{{ __('All Admins') }}</option>
                            <option value="all_users">{{ __('All Users') }}</option>
                            <option value="role">{{ __('Specific Role') }}</option>
                        </x-forms.select>

                        <div x-show="recipientType === 'role'" x-cloak>
                            <x-forms.select
                                :label="__('Role')"
                                name="role_id"
                                :options="$roleOptions"
                                :selected="old('role_id')"
                                :placeholder="__('Select a role')"
                            />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="form-label">{{ __('Channel') }}</p>
                            <p class="mt-1 text-xs text-neutral-400">
                                {{ __('Choose the delivery channel first, then pick a template or write a custom message.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium transition"
                                :class="activeChannel === 'email' ? 'border-primary bg-primary/10 text-primary' : 'border-neutral-200 text-neutral-600 hover:border-neutral-300 hover:text-neutral-900'"
                                @click="setChannel('email')"
                            >
                                <i class="ph ph-envelope-simple"></i>
                                <span>{{ __('Email') }}</span>
                                @unless($channelAvailability['email'])
                                    <span class="rounded-full bg-warning/15 px-2 py-0.5 text-[11px] font-semibold text-warning">{{ __('Disabled') }}</span>
                                @endunless
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium transition"
                                :class="activeChannel === 'sms' ? 'border-primary bg-primary/10 text-primary' : 'border-neutral-200 text-neutral-600 hover:border-neutral-300 hover:text-neutral-900'"
                                @click="setChannel('sms')"
                            >
                                <i class="ph ph-chat-text"></i>
                                <span>{{ __('SMS') }}</span>
                                @unless($channelAvailability['sms'])
                                    <span class="rounded-full bg-warning/15 px-2 py-0.5 text-[11px] font-semibold text-warning">{{ __('Disabled') }}</span>
                                @endunless
                            </button>
                        </div>

                        <input type="hidden" name="channel" x-model="activeChannel">

                        <div class="rounded-2xl border border-neutral-200 bg-neutral-50/70 p-4 text-sm text-neutral-600" x-show="!isChannelEnabled(activeChannel)" x-cloak>
                            {{ __('This channel is currently disabled in Settings → Notifications. You can still prepare the form, but delivery will be blocked until the channel is enabled.') }}
                        </div>
                    </div>
                </div>

                <div class="section-card space-y-6">
                    <div class="space-y-2">
                        <x-forms.select
                            :label="__('Template')"
                            name="template_id"
                            :selected="old('template_id')"
                            :placeholder="__('Custom message (no template)')"
                            x-model="selectedTemplateId"
                        >
                            <template x-for="template in availableTemplates" :key="template.id">
                                <option :value="template.id" x-text="template.name"></option>
                            </template>
                        </x-forms.select>

                        <p class="text-xs text-neutral-400">
                            {{ __('If you leave the template empty, a custom title and message will be sent using the shortcodes listed on the right.') }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-dashed border-neutral-200 bg-neutral-50/70 p-4" x-show="currentTemplate" x-cloak>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-neutral-900" x-text="currentTemplate?.name"></p>
                                <p class="mt-1 text-sm text-neutral-500" x-text="currentTemplate?.description || @js(__('No description available.'))"></p>
                            </div>
                            <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary" x-text="activeChannel === 'email' ? @js(__('Template Email')) : @js(__('Template SMS'))"></span>
                        </div>

                        <div class="mt-4 space-y-4" x-show="currentTemplate && activeChannel === 'email'">
                            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ __('Subject') }}</p>
                                <p class="mt-2 text-sm font-medium text-neutral-900" x-text="currentTemplate?.email_subject || @js(__('No email subject configured.'))"></p>
                            </div>
                            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ __('Body') }}</p>
                                <div class="prose prose-sm mt-2 max-w-none text-neutral-600" x-html="currentTemplate?.email_body || @js(__('No email body configured.'))"></div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl border border-neutral-200 bg-white p-4" x-show="currentTemplate && activeChannel === 'sms'" x-cloak>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ __('SMS Message') }}</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-neutral-700" x-text="currentTemplate?.sms_body || @js(__('No SMS body configured.'))"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2" x-show="currentTemplate && templateVariableEntries.length" x-cloak>
                        <template x-for="field in templateVariableEntries" :key="field.key">
                            <div class="md:col-span-1">
                                <label class="form-label" :for="`template-variable-${field.key}`" x-text="field.key"></label>
                                <input
                                    :id="`template-variable-${field.key}`"
                                    :name="`template_variables[${field.key}]`"
                                    :placeholder="field.description"
                                    type="text"
                                    class="input-field"
                                    data-shortcode-target
                                    :value="oldTemplateVariable(field.key)"
                                >
                                <p class="form-hint" x-text="field.description"></p>
                            </div>
                        </template>
                    </div>

                    <div class="space-y-4" x-show="!currentTemplate" x-cloak>
                        <x-forms.input
                            :label="__('Title')"
                            name="title"
                            :value="old('title')"
                            :placeholder="__('Message title or email subject')"
                            data-shortcode-target
                            required
                        />

                        <x-forms.textarea
                            :label="__('Message Content')"
                            name="message"
                            :value="old('message')"
                            :placeholder="__('Write your message and use shortcodes like :name, :email, :user.', ['name' => $nameTag, 'email' => $emailTag, 'user' => $userTag])"
                            rows="8"
                            data-shortcode-target
                            required
                        />

                        <div class="rounded-2xl border border-neutral-200 bg-neutral-50/70 p-4 text-sm text-neutral-600" x-show="activeChannel === 'sms'" x-cloak>
                            {{ __('For custom SMS, the title is prepended above the message body.') }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <x-ui.button variant="outline" href="{{ route('admin.system-notifications.index') }}">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" type="submit">
                        <i class="ph ph-paper-plane-tilt"></i> {{ __('Queue Notification') }}
                    </x-ui.button>
                </div>
            </div>

            <div class="space-y-6 xl:col-span-1">
                <div class="section-card space-y-5">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">{{ __('Available Shortcodes') }}</h2>
                        <p class="mt-1 text-sm text-neutral-400">{{ __('Click a shortcode to insert it into the active field and copy it.') }}</p>
                    </div>

                    <div class="space-y-3">
                        @foreach([
                            'name' => __('Recipient name'),
                            'email' => __('Recipient email address'),
                            'user' => __('Recipient display name'),
                            'phone' => __('Recipient phone number'),
                            'site_name' => __('Application name'),
                            'site_url' => __('Application URL'),
                            'current_year' => __('Current year'),
                        ] as $code => $description)
                            @php($shortcode = '{' . '{' . $code . '}' . '}')
                            <button
                                type="button"
                                class="block w-full rounded-xl border border-neutral-200 p-3 text-left transition hover:border-primary/40 hover:bg-primary/5"
                                @click="useShortcode({{ Js::from($shortcode) }})"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <span class="inline-flex items-center gap-2 rounded-lg bg-primary/10 px-2.5 py-1 font-mono text-xs font-semibold text-primary">
                                        {{ $shortcode }}
                                    </span>
                                    <span class="text-[11px] font-medium text-neutral-400">{{ __('Insert + Copy') }}</span>
                                </div>
                                <p class="mt-2 text-xs text-neutral-500">{{ $description }}</p>
                            </button>
                        @endforeach
                    </div>

                    <x-ui.alert type="info">
                        <p class="text-sm font-medium">{{ __('Template behavior') }}</p>
                        <p class="mt-1 text-sm">
                            {{ __('Selecting a template uses the saved channel content for that template. Leaving the template empty uses the custom title and message from this page.') }}
                        </p>
                    </x-ui.alert>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function sendNotificationPage(config) {
                return {
                    templates: config.templates || [],
                    activeChannel: config.oldChannel || 'email',
                    selectedTemplateId: config.oldTemplateId || '',
                    recipientType: config.oldRecipientType || 'all_admins',
                    channelAvailability: config.channelAvailability || {},
                    oldTemplateVariables: @json(old('template_variables', [])),
                    activeShortcodeTarget: null,

                    get availableTemplates() {
                        return this.templates.filter((template) => (template.channels || []).includes(this.activeChannel));
                    },

                    get currentTemplate() {
                        if (!this.selectedTemplateId) {
                            return null;
                        }

                        return this.availableTemplates.find((template) => String(template.id) === String(this.selectedTemplateId)) || null;
                    },

                    get templateVariableEntries() {
                        const variables = this.currentTemplate?.variables || {};

                        return Object.entries(variables).map(([key, description]) => ({
                            key,
                            description,
                        }));
                    },

                    init() {
                        if (!this.isChannelEnabled(this.activeChannel)) {
                            const fallbackChannel = ['email', 'sms'].find((channel) => this.isChannelEnabled(channel));

                            if (fallbackChannel) {
                                this.activeChannel = fallbackChannel;
                            }
                        }

                        if (!this.currentTemplate && this.selectedTemplateId) {
                            this.selectedTemplateId = '';
                        }

                        this.$root.addEventListener('focusin', (event) => {
                            if (event.target.matches('[data-shortcode-target]')) {
                                this.activeShortcodeTarget = event.target;
                            }
                        });
                    },

                    setChannel(channel) {
                        this.activeChannel = channel;

                        if (!this.currentTemplate) {
                            return;
                        }

                        const templateStillAvailable = this.availableTemplates.some((template) => String(template.id) === String(this.selectedTemplateId));

                        if (!templateStillAvailable) {
                            this.selectedTemplateId = '';
                        }
                    },

                    isChannelEnabled(channel) {
                        return Boolean(this.channelAvailability[channel]);
                    },

                    oldTemplateVariable(key) {
                        return this.oldTemplateVariables[key] || '';
                    },

                    useShortcode(shortcode) {
                        this.insertIntoActiveField(shortcode);

                        if (navigator.clipboard?.writeText) {
                            navigator.clipboard.writeText(shortcode);
                        }
                    },

                    insertIntoActiveField(shortcode) {
                        const target = this.resolveShortcodeTarget();

                        if (!target) {
                            return;
                        }

                        const start = target.selectionStart ?? target.value.length;
                        const end = target.selectionEnd ?? target.value.length;
                        const value = target.value || '';

                        target.value = `${value.slice(0, start)}${shortcode}${value.slice(end)}`;
                        target.dispatchEvent(new Event('input', { bubbles: true }));
                        target.focus();

                        const cursorPosition = start + shortcode.length;

                        if (typeof target.setSelectionRange === 'function') {
                            target.setSelectionRange(cursorPosition, cursorPosition);
                        }
                    },

                    resolveShortcodeTarget() {
                        if (this.activeShortcodeTarget && this.activeShortcodeTarget.offsetParent !== null) {
                            return this.activeShortcodeTarget;
                        }

                        return this.$root.querySelector('[data-shortcode-target]:not([type="hidden"])');
                    },
                };
            }
        </script>
    @endpush
</x-layouts.admin>
