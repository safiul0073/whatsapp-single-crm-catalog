<x-layouts.admin :title="__('Contact Message')">
    @php
        $templateOptions = $templates->map(fn ($template) => [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'variables' => $template->variables ?? [],
            'email_subject' => $template->email_subject,
            'email_body' => $template->email_body,
        ])->values();
        $contactVariableLabels = [
            'name' => __('Full name'),
            'first_name' => __('First name'),
            'last_name' => __('Last name'),
            'email' => __('Email address'),
            'company' => __('Company'),
            'interest' => __('Interest'),
            'message' => __('Original message'),
        ];
    @endphp

    <div class="space-y-6">
        <div class="section-card p-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <x-ui.button variant="outline" href="{{ route('admin.contact-messages.index') }}" class="mb-3">
                    <i class="ph ph-arrow-left"></i> {{ __('All Messages') }}
                </x-ui.button>
                <h1 class="heading-4 text-neutral-950 font-bold">{{ $contactMessage->full_name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <a href="mailto:{{ $contactMessage->email }}" class="text-sm font-semibold text-primary">
                        {{ $contactMessage->email }}
                    </a>
                    <x-ui.badge :variant="$contactMessage->status_badge_variant">{{ $contactMessage->status_label }}</x-ui.badge>
                    <span class="s-body text-neutral-400">{{ $contactMessage->created_at->diffForHumans() }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.contact-messages.update-status', $contactMessage) }}"
                class="flex items-center gap-2 rounded-xl border border-neutral-100 bg-neutral-50 p-2.5">
                @csrf
                <select name="status" class="select-field bg-white">
                    @foreach (['new' => __('New'), 'read' => __('Read'), 'archived' => __('Archived')] as $value => $label)
                        <option value="{{ $value }}" {{ $contactMessage->status === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <x-ui.button type="submit" variant="primary">{{ __('Update') }}</x-ui.button>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_320px]">
            <div class="space-y-6">
                <div class="section-card p-6">
                    <div class="mb-4 flex items-center gap-2 border-b border-neutral-100 pb-3">
                        <i class="ph ph-chat-centered-text text-lg text-primary"></i>
                        <h2 class="text-sm font-bold text-neutral-900">{{ __('Message') }}</h2>
                    </div>
                    <div class="prose prose-sm max-w-none whitespace-pre-line text-neutral-700">
                        {{ $contactMessage->message }}
                    </div>
                </div>

                <div class="section-card p-6">
                    <div class="mb-5 flex items-center gap-2 border-b border-neutral-100 pb-3">
                        <i class="ph ph-paper-plane-tilt text-lg text-primary"></i>
                        <h2 class="text-sm font-bold text-neutral-900">{{ __('Send Email Reply') }}</h2>
                    </div>

                    <form method="POST" action="{{ route('admin.contact-messages.reply', $contactMessage) }}"
                        class="space-y-5"
                        x-data="contactMessageReplyForm({
                            templates: {{ Js::from($templateOptions) }},
                            oldTemplateId: {{ Js::from(old('template_id')) }},
                            oldReplyType: {{ Js::from(old('reply_type', 'custom')) }},
                            oldVariables: {{ Js::from(old('template_variables', [])) }},
                        })">
                        @csrf

                        <div>
                            <label class="form-label">{{ __('Reply Type') }}</label>
                            <div class="flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm font-semibold text-neutral-700">
                                    <input type="radio" name="reply_type" value="custom" x-model="replyType" class="custom-radio">
                                    {{ __('Custom message') }}
                                </label>
                                <label class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm font-semibold text-neutral-700">
                                    <input type="radio" name="reply_type" value="template" x-model="replyType" class="custom-radio">
                                    {{ __('Use template') }}
                                </label>
                            </div>
                            @error('reply_type')<p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="replyType === 'template'" x-cloak>
                            <label for="template_id" class="form-label">{{ __('Email Template') }}</label>
                            <select id="template_id" name="template_id" class="select-field w-full" x-model="selectedTemplateId">
                                <option value="">{{ __('Select a template') }}</option>
                                <template x-for="template in templates" :key="template.id">
                                    <option :value="template.id" x-text="template.name"></option>
                                </template>
                            </select>
                            @error('template_id')<p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4" x-show="replyType === 'template' && currentTemplate" x-cloak>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-400">{{ __('Template Preview') }}</p>
                            <p class="mt-2 text-sm font-semibold text-neutral-900" x-text="currentTemplate?.email_subject"></p>
                            <div class="prose prose-sm mt-3 max-w-none text-neutral-600" x-html="currentTemplate?.email_body"></div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2" x-show="replyType === 'template' && templateVariableEntries.length" x-cloak>
                            <template x-for="field in templateVariableEntries" :key="field.key">
                                <div>
                                    <label class="form-label" :for="`contact-template-variable-${field.key}`" x-text="field.key"></label>
                                    <input
                                        :id="`contact-template-variable-${field.key}`"
                                        :name="`template_variables[${field.key}]`"
                                        :placeholder="field.description"
                                        type="text"
                                        class="input-field"
                                        :value="oldTemplateVariable(field.key)"
                                    >
                                    <p class="form-hint" x-text="field.description"></p>
                                </div>
                            </template>
                        </div>

                        <div x-show="replyType === 'custom'" x-cloak>
                            <label for="reply_subject" class="form-label">{{ __('Subject') }}</label>
                            <input id="reply_subject" name="subject" type="text" value="{{ old('subject', 'Re: Your message to ' . setting('site_name', config('app.name'))) }}"
                                class="input-field w-full" />
                            @error('subject')<p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="replyType === 'custom'" x-cloak>
                            <x-forms.editor :label="__('Message')" name="body" :value="old('body')" :placeholder="__('Write your email reply...')" />
                        </div>

                        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                            <p class="text-sm font-semibold text-neutral-900">{{ __('Built-in Contact Variables') }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($contactVariableLabels as $code => $label)
                                    @php($shortcode = '{' . '{' . $code . '}' . '}')
                                    <button
                                        type="button"
                                        class="rounded-lg border border-neutral-200 bg-white px-2.5 py-1.5 text-left font-mono text-xs font-semibold text-primary transition hover:border-primary/40 hover:bg-primary/5"
                                        title="{{ $label }}"
                                        @click="copyVariable({{ Js::from($shortcode) }})"
                                    >
                                        {{ $shortcode }}
                                    </button>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-neutral-500">{{ __('Click a variable to copy it, then paste it into a custom subject, custom message, or template variable field.') }}</p>
                            @error('template_variables')<p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>@enderror
                        </div>

                        <x-ui.button type="submit" variant="primary">
                            <i class="ph ph-paper-plane-tilt"></i> {{ __('Queue Email Reply') }}
                        </x-ui.button>
                    </form>
                </div>

                <div class="section-card p-6">
                    <div class="mb-5 flex items-center gap-2 border-b border-neutral-100 pb-3">
                        <i class="ph ph-clock-counter-clockwise text-lg text-primary"></i>
                        <h2 class="text-sm font-bold text-neutral-900">{{ __('Reply History') }}</h2>
                    </div>

                    @forelse ($replies as $reply)
                        <div class="border-b border-neutral-100 py-4 first:pt-0 last:border-b-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-neutral-900">{{ $reply->subject }}</p>
                                    <p class="mt-1 text-xs text-neutral-500">
                                        {{ __('To') }} {{ $reply->recipient_email }}
                                        @if ($reply->admin)
                                            {{ __('by') }} {{ $reply->admin->name }}
                                        @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-ui.badge :variant="$reply->source === 'template' ? 'info' : 'neutral'">
                                        {{ $reply->source === 'template' ? __('Template') : __('Custom') }}
                                    </x-ui.badge>
                                    <span class="text-xs text-neutral-400">{{ $reply->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                            <div class="prose prose-sm mt-3 max-w-none text-neutral-700">
                                @if ($reply->source === 'template')
                                    {!! $reply->body !!}
                                @else
                                    {!! $reply->body !!}
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-neutral-500">{{ __('No replies have been sent yet.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-4">
                <div class="section-card p-6">
                    <h3 class="mb-4 flex items-center gap-2 border-b border-neutral-100 pb-3 text-sm font-bold text-neutral-900">
                        <i class="ph ph-info text-neutral-500"></i>
                        {{ __('Submission Details') }}
                    </h3>
                    <dl class="space-y-4">
                        <div>
                            <dt class="s-body mb-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-400">{{ __('Company') }}</dt>
                            <dd class="text-sm font-semibold text-neutral-900">{{ $contactMessage->company }}</dd>
                        </div>
                        <div>
                            <dt class="s-body mb-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-400">{{ __('Interest') }}</dt>
                            <dd class="text-sm text-neutral-700">{{ $contactMessage->interest }}</dd>
                        </div>
                        <div>
                            <dt class="s-body mb-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-400">{{ __('Submitted') }}</dt>
                            <dd class="text-sm text-neutral-700">{{ $contactMessage->created_at->format('M j, Y g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="s-body mb-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-400">{{ __('Source') }}</dt>
                            <dd class="break-words text-sm text-neutral-700">{{ $contactMessage->source_url ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="s-body mb-1 text-[10px] font-semibold uppercase tracking-widest text-neutral-400">{{ __('IP Address') }}</dt>
                            <dd class="text-sm text-neutral-700">{{ $contactMessage->ip_address ?: '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="section-card p-6 space-y-4">
                    <h3 class="flex items-center gap-2 text-sm font-bold text-neutral-900">
                        <i class="ph ph-envelope text-primary"></i>
                        {{ __('Admin Actions') }}
                    </h3>
                    <form method="POST" action="{{ route('admin.contact-messages.subscribe-newsletter', $contactMessage) }}">
                        @csrf
                        <x-ui.button type="submit" variant="outline" class="w-full justify-center">
                            <i class="ph ph-newspaper"></i> {{ __('Add to Newsletter') }}
                        </x-ui.button>
                    </form>
                    <x-ui.button type="button" variant="danger" class="w-full justify-center"
                        data-modal-trigger="globalConfirmModal"
                        data-confirm-title="{{ __('Delete Contact Message?') }}"
                        data-confirm-message="{{ __('Delete this contact message? This cannot be undone.') }}"
                        data-confirm-action="{{ route('admin.contact-messages.destroy', $contactMessage) }}"
                        data-confirm-method="DELETE"
                        data-confirm-button="{{ __('Yes, Delete') }}">
                        <i class="ph ph-trash"></i> {{ __('Delete Message') }}
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function contactMessageReplyForm(config) {
                return {
                    templates: config.templates || [],
                    replyType: config.oldReplyType || 'custom',
                    selectedTemplateId: config.oldTemplateId ? String(config.oldTemplateId) : '',
                    oldVariables: config.oldVariables || {},
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
                    oldTemplateVariable(key) {
                        return this.oldVariables[key] || '';
                    },
                    copyVariable(shortcode) {
                        navigator.clipboard?.writeText(shortcode);
                    },
                };
            }
        </script>
    @endpush
</x-layouts.admin>
