<x-layouts.admin :title="__('Send Newsletter')">
    @php
        $templateOptions = $templates->map(fn ($template) => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'variables' => $template->variables ?? [],
            'email_subject' => $template->email_subject,
            'email_body' => $template->email_body,
        ])->values();
        $nameTag = '{{name}}';
        $emailTag = '{{email}}';
    @endphp

    <div
        x-data="sendNewsletterPage({
            templates: {{ Js::from($templateOptions) }},
            oldTemplateId: {{ Js::from(old('template_id')) }},
            oldRecipientType: {{ Js::from(old('recipient_type', 'active')) }},
        })"
        class="space-y-6"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Send Newsletter') }}</h1>
                <p class="mt-1 text-sm text-neutral-400">
                    {{ __('Send an email newsletter to active subscribers, every subscriber, or one selected subscriber.') }}
                </p>
            </div>

            <x-ui.button variant="outline" href="{{ route('admin.subscribers.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back to Subscribers') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ route('admin.subscribers.send.store') }}" class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            @csrf

            <div class="space-y-6 xl:col-span-2">
                <div class="section-card space-y-5">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <x-forms.select
                            :label="__('Recipients')"
                            name="recipient_type"
                            :selected="old('recipient_type', 'active')"
                            x-model="recipientType"
                            required
                        >
                            <option value="active">{{ __('Active Subscribers (:count)', ['count' => $activeSubscriberCount]) }}</option>
                            <option value="all">{{ __('All Subscribers (:count)', ['count' => $subscriberCount]) }}</option>
                            <option value="single">{{ __('Single Subscriber') }}</option>
                        </x-forms.select>

                        <div x-show="recipientType === 'single'" x-cloak>
                            <x-forms.select
                                :label="__('Subscriber')"
                                name="subscriber_id"
                                :options="$subscriberOptions"
                                :selected="old('subscriber_id')"
                                :placeholder="__('Select a subscriber')"
                            />
                        </div>
                    </div>
                </div>

                <div class="section-card space-y-6">
                    <div class="space-y-2">
                        <x-forms.select
                            :label="__('Notification Template')"
                            name="template_id"
                            :selected="old('template_id')"
                            :placeholder="__('Custom newsletter (no template)')"
                            x-model="selectedTemplateId"
                        >
                            <template x-for="template in templates" :key="template.id">
                                <option :value="template.id" x-text="template.name"></option>
                            </template>
                        </x-forms.select>

                        <p class="text-xs text-neutral-400">
                            {{ __('Select an email notification template, or leave this empty to write a custom subject and message.') }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-dashed border-neutral-200 bg-neutral-50/70 p-4" x-show="currentTemplate" x-cloak>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-neutral-900" x-text="currentTemplate?.name"></p>
                                <p class="mt-1 text-sm text-neutral-500" x-text="currentTemplate?.description || @js(__('No description available.'))"></p>
                            </div>
                            <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">{{ __('Email Template') }}</span>
                        </div>

                        <div class="mt-4 space-y-4">
                            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ __('Subject') }}</p>
                                <p class="mt-2 text-sm font-medium text-neutral-900" x-text="currentTemplate?.email_subject || @js(__('No email subject configured.'))"></p>
                            </div>
                            <div class="rounded-xl border border-neutral-200 bg-white p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ __('Body') }}</p>
                                <div class="prose prose-sm mt-2 max-w-none text-neutral-600" x-html="currentTemplate?.email_body || @js(__('No email body configured.'))"></div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2" x-show="currentTemplate && templateVariableEntries.length" x-cloak>
                        <template x-for="field in templateVariableEntries" :key="field.key">
                            <div>
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
                            :label="__('Subject')"
                            name="title"
                            :value="old('title')"
                            :placeholder="__('Newsletter subject')"
                            data-shortcode-target
                            required
                        />

                        <x-forms.editor
                            :label="__('Message')"
                            name="message"
                            :value="old('message')"
                            :placeholder="__('Write your newsletter and use shortcodes like :name or :email.', ['name' => $nameTag, 'email' => $emailTag])"
                            required
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <x-ui.button variant="outline" href="{{ route('admin.subscribers.index') }}">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" type="submit">
                        <i class="ph ph-paper-plane-tilt"></i> {{ __('Queue Newsletter') }}
                    </x-ui.button>
                </div>
            </div>

            <div class="space-y-6">
                <div class="section-card space-y-5">
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">{{ __('Newsletter Shortcodes') }}</h2>
                        <p class="mt-1 text-sm text-neutral-400">{{ __('Click a shortcode to insert it into the active field and copy it.') }}</p>
                    </div>

                    <div class="space-y-3">
                        @foreach([
                            'name' => __('Subscriber name inferred from email'),
                            'email' => __('Subscriber email address'),
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
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            function sendNewsletterPage(config) {
                return {
                    templates: config.templates || [],
                    selectedTemplateId: config.oldTemplateId ? String(config.oldTemplateId) : '',
                    recipientType: config.oldRecipientType || 'active',
                    activeField: null,
                    init() {
                        this.$nextTick(() => {
                            this.bindShortcodeTargets();
                        });
                    },
                    get currentTemplate() {
                        return this.templates.find((template) => String(template.id) === String(this.selectedTemplateId)) || null;
                    },
                    get templateVariableEntries() {
                        if (!this.currentTemplate || !this.currentTemplate.variables) {
                            return [];
                        }

                        return Object.entries(this.currentTemplate.variables).map(([key, description]) => ({
                            key,
                            description,
                        }));
                    },
                    bindShortcodeTargets() {
                        this.$root.querySelectorAll('[data-shortcode-target]').forEach((field) => {
                            field.addEventListener('focus', () => {
                                this.activeField = field;
                            });
                        });
                    },
                    oldTemplateVariable(key) {
                        const oldVariables = {{ Js::from(old('template_variables', [])) }};

                        return oldVariables[key] || '';
                    },
                    useShortcode(shortcode) {
                        if (!this.activeField) {
                            this.activeField = this.$root.querySelector('[data-shortcode-target]');
                        }

                        if (this.activeField) {
                            const start = this.activeField.selectionStart || this.activeField.value.length;
                            const end = this.activeField.selectionEnd || this.activeField.value.length;
                            this.activeField.value = `${this.activeField.value.slice(0, start)}${shortcode}${this.activeField.value.slice(end)}`;
                            this.activeField.dispatchEvent(new Event('input', { bubbles: true }));
                            this.activeField.focus();
                            this.activeField.setSelectionRange(start + shortcode.length, start + shortcode.length);
                        }

                        navigator.clipboard?.writeText(shortcode);
                    },
                };
            }
        </script>
    @endpush
</x-layouts.admin>
