@php
    $isEditing = (bool) $rule;
    $payload = $rule?->reply_payload ?? [];
    $triggerType = old('trigger_type', $rule->trigger_type ?? 'keyword');
    $matchType = old('match_type', $rule->match_type ?? 'contains');
    $replyType = old('reply_type', $rule->reply_type ?? 'text');
    $replyText = old('reply_text', $rule->reply_text ?? '');
    $selectedTemplateId = old('message_template_id', $payload['message_template_id'] ?? '');
    $selectedMediaId = old('media_id', $payload['media_id'] ?? '');
    $mediaUrl = old('media_url', $payload['media_url'] ?? '');
    $mediaType = old('media_type', $payload['type'] ?? 'document');
    $mediaCaption = old('media_caption', $payload['caption'] ?? '');
@endphp

<x-layouts.user :title="$isEditing ? __('Edit Auto-Reply Rule') : __('New Auto-Reply Rule')">
    <div class="flex items-center gap-3">
        <a href="{{ route('user.auto-replies.index') }}" class="row-action" aria-label="Back to auto-replies">
            <i class="ph ph-arrow-left text-lg"></i>
        </a>
        <div>
            <h2 class="heading-2">{{ $isEditing ? 'Edit Auto-Reply Rule' : 'New Auto-Reply Rule' }}</h2>
            <p class="m-text mt-1">Define when to reply and what message to send.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="app-card mt-5 border-error/30 bg-error/5 p-4 text-sm text-error">
            <p class="font-semibold">Please fix the highlighted fields.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ $isEditing ? route('user.auto-replies.update', $rule) : route('user.auto-replies.store') }}"
        data-auto-reply-editor
        class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <div class="space-y-5">
            <section class="app-card p-4 sm:p-6">
                <h3 class="heading-4">Rule Details</h3>

                <div class="mt-4">
                    <label for="name" class="form-label">Rule Name <span class="text-error">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name', $rule->name ?? '') }}" class="form-input" placeholder="e.g. Menu request" required />
                </div>
            </section>

            <section class="app-card p-4 sm:p-6">
                <h3 class="heading-4">Trigger</h3>
                <p class="form-hint mt-0.5">When should this rule fire?</p>

                <div class="mt-4">
                    <span class="form-label">Trigger Type <span class="text-error">*</span></span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ([
                            'keyword' => 'Keyword',
                            'welcome' => 'Welcome',
                            'out_of_hours' => 'Out of hours',
                            'fallback' => 'Fallback',
                        ] as $value => $label)
                            <label class="radio-card">
                                <input type="radio" name="trigger_type" value="{{ $value }}" @checked($triggerType === $value) />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 space-y-4" data-keyword-fields>
                    <div>
                        <label for="trigger_value" class="form-label">Keywords <span class="text-error">*</span></label>
                        <input id="trigger_value" name="trigger_value" type="text" value="{{ old('trigger_value', $rule->trigger_value ?? '') }}" class="form-input" placeholder="e.g. ORDER, MENU, TRACK" />
                        <p class="form-hint">Separate multiple keywords with commas.</p>
                    </div>
                    <div>
                        <span class="form-label">Match Type</span>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (['contains' => 'Contains', 'exact' => 'Exact', 'regex' => 'Regex'] as $value => $label)
                                <label class="radio-card">
                                    <input type="radio" name="match_type" value="{{ $value }}" @checked($matchType === $value) />
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section class="app-card p-4 sm:p-6">
                <h3 class="heading-4">Response</h3>

                <div class="mt-4">
                    <span class="form-label">Response Type <span class="text-error">*</span></span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (['text' => 'Text', 'template' => 'Template', 'media' => 'Media'] as $value => $label)
                            <label class="radio-card">
                                <input type="radio" name="reply_type" value="{{ $value }}" @checked($replyType === $value) />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 hidden" data-template-fields>
                    <label for="message_template_id" class="form-label">Approved WhatsApp Template <span class="text-error">*</span></label>
                    <select id="message_template_id" name="message_template_id" class="form-input">
                        <option value="">Select approved template...</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}" @selected((string) $selectedTemplateId === (string) $template->id)>
                                {{ $template->name }} · {{ $template->language ?? 'en_US' }} · {{ ucfirst((string) $template->category) }}
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">Template replies are sent only on WhatsApp conversations.</p>
                </div>

                <div class="mt-4 hidden space-y-4" data-media-fields>
                    <div>
                        <label for="media_id" class="form-label">Media Library File</label>
                        <select id="media_id" name="media_id" class="form-input">
                            <option value="">Use external URL...</option>
                            @foreach ($mediaItems as $media)
                                <option value="{{ $media->id }}" @selected((string) $selectedMediaId === (string) $media->id)>
                                    {{ $media->original_name ?? $media->name }} · {{ ucfirst((string) $media->type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                        <div>
                            <label for="media_url" class="form-label">Media URL</label>
                            <input id="media_url" name="media_url" type="url" value="{{ $mediaUrl }}" class="form-input" placeholder="https://example.com/catalog.pdf" />
                        </div>
                        <div>
                            <label for="media_type" class="form-label">URL Type</label>
                            <select id="media_type" name="media_type" class="form-input">
                                @foreach (['image' => 'Image', 'video' => 'Video', 'audio' => 'Audio', 'document' => 'Document'] as $value => $label)
                                    <option value="{{ $value }}" @selected($mediaType === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="media_caption" class="form-label">Caption</label>
                        <input id="media_caption" name="media_caption" type="text" value="{{ $mediaCaption }}" maxlength="1024" class="form-input" placeholder="Optional caption for the media message" data-preview-caption />
                    </div>
                    <p class="form-hint">Choose a library file or add a direct URL. URL type is used only when no library file is selected.</p>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between gap-2">
                        <label for="reply_text" class="form-label">Reply Text</label>
                        <span class="text-xs text-neutral-400"><span data-char-count>0</span> / 4096</span>
                    </div>
                    <textarea
                        id="reply_text"
                        name="reply_text"
                        rows="6"
                        maxlength="4096"
                        class="form-input resize-none"
                        placeholder="Hi! Thanks for reaching out. How can we help you today?"
                        data-preview-text
                    >{{ $replyText }}</textarea>
                    <p class="form-hint">Required for text replies. Optional preview text for template/media replies.</p>
                </div>
            </section>

            <section class="app-card p-4 sm:p-6">
                <h3 class="heading-4">Settings</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="priority" class="form-label">Priority</label>
                        <input id="priority" name="priority" type="number" min="0" max="999" value="{{ old('priority', $rule->priority ?? 10) }}" class="form-input" data-summary-priority-input />
                        <p class="form-hint">Lower numbers run first when multiple rules match.</p>
                    </div>
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-section p-4">
                        <div>
                            <p class="text-sm font-semibold text-title">Enable rule</p>
                            <p class="text-xs text-body">Disabled rules are saved but never fire.</p>
                        </div>
                        <x-forms.switch
                            name="is_active"
                            value="1"
                            unchecked-value="0"
                            :checked="old('is_active', $rule->is_active ?? true)"
                            :title="__('Enable rule')"
                            data-summary-status-input
                        />
                    </div>
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-check text-base"></i>
                    {{ $isEditing ? 'Update rule' : 'Save rule' }}
                </button>
                <a href="{{ route('user.auto-replies.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </div>

        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <div class="app-card p-4 sm:p-5">
                <h3 class="heading-5 mb-3">Preview</h3>
                <div class="space-y-2">
                    <div class="flex justify-start">
                        <div class="max-w-[80%] rounded-2xl rounded-tl-sm bg-neutral-100 px-3 py-2 text-sm text-title dark:bg-neutral-800">Hello! I need help.</div>
                    </div>
                    <div class="flex justify-end">
                        <div class="chat-bubble chat-bubble--out" data-preview-bubble>
                            <p class="whitespace-pre-wrap" data-preview-content>{{ $replyText ?: 'Hi! Thanks for reaching out. How can we help you today?' }}</p>
                            <span class="mt-1 block text-right text-[10px] text-neutral-0/60">Just now ✓✓</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-card p-4 sm:p-5">
                <h3 class="heading-5 mb-3">Rule Summary</h3>
                <dl class="space-y-2 text-sm">
                    <div class="f-between gap-2">
                        <dt class="text-body">Trigger</dt>
                        <dd class="font-semibold text-title" data-summary-trigger>{{ str($triggerType)->replace('_', ' ')->headline() }}</dd>
                    </div>
                    <div class="f-between gap-2">
                        <dt class="text-body">Response</dt>
                        <dd class="font-semibold text-title" data-summary-reply>{{ str($replyType)->headline() }}</dd>
                    </div>
                    <div class="f-between gap-2">
                        <dt class="text-body">Priority</dt>
                        <dd class="font-semibold text-title" data-summary-priority>{{ old('priority', $rule->priority ?? 10) }}</dd>
                    </div>
                    <div class="f-between gap-2">
                        <dt class="text-body">Status</dt>
                        <dd><span class="badge badge-success" data-summary-status>{{ old('is_active', $rule->is_active ?? true) ? 'Enabled' : 'Disabled' }}</span></dd>
                    </div>
                </dl>
            </div>
        </aside>
    </form>
</x-layouts.user>
