@php
    use App\Modules\Media\Models\Media;

    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $isEditing = filled($template ?? null);
    $provider = old('provider', $template?->provider ?? ($provider ?? 'whatsapp'));
    $isWhatsApp = $provider === 'whatsapp';
    $components = collect($template?->components ?? []);
    $header = $components->firstWhere('type', 'HEADER') ?? [];
    $body = $components->firstWhere('type', 'BODY') ?? [];
    $footer = $components->firstWhere('type', 'FOOTER') ?? [];
    $buttonComponent = $components->firstWhere('type', 'BUTTONS') ?? [];
    $headerFormat = strtolower((string) data_get($header, 'format', 'none'));
    $headerType = in_array($headerFormat, ['text', 'image', 'video', 'document'], true) ? $headerFormat : 'none';
    $media = filled(data_get($header, 'media_id')) ? Media::query()->find(data_get($header, 'media_id')) : null;
    $selectedLanguage = old('language', $template?->language ?? 'en');
    $selectedCategory = old('category', $template?->category ?? ($isWhatsApp ? 'marketing' : 'utility'));
    $selectedWaba = old('provider_account_id');
    $bodyText = old('body', data_get($body, 'text', $isWhatsApp ? 'Hello {{full_name}}, your order {{custom.order_id}} is confirmed.' : 'Hi {{full_name}}, thanks for joining our Telegram updates.'));
    preg_match_all('/\{\{\s*([^}]+)\s*\}\}/', $bodyText, $bodyVariableMatches);
    $bodyVariableKeys = collect($bodyVariableMatches[1] ?? [])->map(fn ($key) => trim($key))->filter()->unique()->values();
    $savedBodyExamples = collect(data_get($body, 'example.body_text.0', []));
    $bodyExampleState = old('body_examples');
    if (! is_array($bodyExampleState)) {
        $bodyExampleState = $bodyVariableKeys->mapWithKeys(fn ($key, $index) => [$key => $savedBodyExamples->get($index, '')])->all();
    }

    $editorState = [
        'provider' => $provider,
        'header' => [
            'type' => old('header.type', $headerType),
            'text' => old('header.text', data_get($header, 'text', '')),
            'example' => old('header.example', data_get($header, 'example.header_text.0', 'Customer update')),
            'mediaId' => old('header.media_id', data_get($header, 'media_id')),
            'mediaName' => $media?->original_name ?? data_get($header, 'media_name', ''),
            'mediaUrl' => $media?->url ?? data_get($header, 'media_url', ''),
            'handle' => data_get($header, 'example.header_handle.0', ''),
        ],
        'body' => $bodyText,
        'bodyExamples' => $bodyExampleState,
        'footer' => [
            'text' => old('footer.text', data_get($footer, 'text', '')),
        ],
        'buttons' => old('buttons', collect(data_get($buttonComponent, 'buttons', []))->map(function (array $button): array {
            $type = match ($button['type'] ?? '') {
                'URL' => 'url',
                'PHONE_NUMBER' => 'phone_number',
                'CALLBACK' => 'callback',
                default => 'quick_reply',
            };

            return [
                'type' => $type,
                'text' => $button['text'] ?? '',
                'url' => $button['url'] ?? '',
                'phone_number' => $button['phone_number'] ?? '',
                'callback_data' => $button['callback_data'] ?? '',
                'example' => data_get($button, 'example.0', ''),
            ];
        })->values()->all()),
    ];
@endphp

<x-layouts.user :title="$isEditing ? __('Edit Template') : __('New Template')">
    <div class="flex items-center gap-3">
        <a href="{{ route('user.message-templates.index') }}" class="row-action" aria-label="Back to templates"><i class="ph ph-arrow-left text-lg"></i></a>
        <div>
            <h2 class="heading-2">{{ $isEditing ? 'Edit Template' : 'New Template' }}</h2>
            <p class="m-text mt-1">{{ $isWhatsApp ? 'Build a WhatsApp message template and submit it to Meta for approval.' : 'Build a local Telegram template for bot campaigns.' }}</p>
        </div>
    </div>

    <form
        x-data="messageTemplateEditor(@js($editorState))"
        data-template-editor
        class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_21rem]"
        method="POST"
        enctype="multipart/form-data"
        action="{{ $isEditing ? route('user.message-templates.update', $template) : route('user.message-templates.store') }}"
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif
        <input type="hidden" name="provider" value="{{ $provider }}">

        <div class="space-y-6">
            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">Basic Info</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="templateName" class="form-label">Template Name <span class="text-error">*</span></label>
                        <input id="templateName" name="name" type="text" required pattern="[a-z0-9_]+" placeholder="order_confirmation" class="form-input" value="{{ old('name', $template?->name) }}" />
                        <p class="form-hint">Lowercase letters, numbers and underscores only.</p>
                        @error('name')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="language" class="form-label">Language <span class="text-error">*</span></label>
                        <select id="language" name="language" required class="form-input">
                            <option value="en" @selected($selectedLanguage === 'en')>English</option>
                            <option value="en_US" @selected($selectedLanguage === 'en_US')>English (US)</option>
                            <option value="es" @selected($selectedLanguage === 'es')>Spanish</option>
                            <option value="fr" @selected($selectedLanguage === 'fr')>French</option>
                            <option value="de" @selected($selectedLanguage === 'de')>German</option>
                        </select>
                        @error('language')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4" @if (! $isWhatsApp) style="display: none;" @endif>
                    <span class="form-label">Category <span class="text-error">*</span></span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <label class="radio-card">
                            <input type="radio" name="category" value="marketing" @checked($selectedCategory === 'marketing') />
                            <span>Marketing</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="category" value="utility" @checked($selectedCategory === 'utility') />
                            <span>Utility</span>
                        </label>
                        <label class="radio-card">
                            <input type="radio" name="category" value="authentication" @checked($selectedCategory === 'authentication') />
                            <span>Authentication</span>
                        </label>
                    </div>
                    @error('category')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
                </div>
            </section>

            @if ($isWhatsApp)
            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">Header</h3>
                <p class="form-hint">Choose one optional header format for the WhatsApp template.</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <template x-for="option in headerTypes" :key="option.value">
                        <label class="radio-card">
                            <input type="radio" name="header[type]" :value="option.value" x-model="header.type" />
                            <span x-text="option.label"></span>
                        </label>
                    </template>
                </div>

                <div class="component-block mt-4" x-show="header.type === 'text'">
                    <label for="headerText" class="form-label">Header Text</label>
                    <input id="headerText" name="header[text]" type="text" maxlength="60" placeholder="Limited time offer" class="form-input" x-model="header.text" />
                    <div class="mt-3" x-show="variablesFor(header.text).length">
                        <label for="headerExample" class="form-label">Header Example</label>
                        <input id="headerExample" name="header[example]" type="text" maxlength="60" placeholder="Summer Sale" class="form-input" x-model="header.example" />
                    </div>
                    @error('header.text')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
                </div>

                <div class="component-block mt-4" x-show="isMediaHeader">
                    <input type="hidden" name="header[media_id]" :value="header.mediaId || ''" />
                    <input type="hidden" name="header[handle]" :value="header.handle || ''" />
                    <label for="headerMediaFile" class="upload-drop cursor-pointer transition-colors hover:border-primary/60 hover:bg-primary/5">
                        <input id="headerMediaFile" name="header_media_file" type="file" class="sr-only" accept="image/*,video/mp4,application/pdf" @change="chooseMedia($event)" />
                        <template x-if="!header.mediaUrl">
                            <span>
                                <i class="ph ph-upload-simple text-2xl"></i>
                                <span class="mt-1 block text-sm">Upload image, video or document header sample</span>
                            </span>
                        </template>
                        <template x-if="header.mediaUrl">
                            <span class="w-full">
                                <span class="block truncate text-sm font-semibold text-title" x-text="header.mediaName || 'Selected media'"></span>
                                <span class="mt-1 block text-xs text-neutral-400">Choose another file to replace it.</span>
                            </span>
                        </template>
                    </label>
                    <p class="form-hint">The file is used for preview and uploaded to Meta as the required header example when submitting.</p>
                    @error('header_media_file')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
                    @error('header.media_id')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
                </div>
            </section>
            @endif

            <section class="app-card p-5 sm:p-6">
                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                    <div class="min-w-0">
                        <h3 class="heading-4">Body</h3>
                        <p class="form-hint">{{ $isWhatsApp ? 'WhatsApp markdown and named shortcodes are supported.' : 'Named shortcodes are replaced for each Telegram recipient.' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap sm:justify-end">
                        <div class="flex items-center gap-1.5">
                            <button type="button" class="row-action" title="Bold" @click="wrapBody('*', '*')"><i class="ph ph-text-b text-base"></i></button>
                            <button type="button" class="row-action" title="Italic" @click="wrapBody('_', '_')"><i class="ph ph-text-italic text-base"></i></button>
                            <button type="button" class="row-action" title="Strikethrough" @click="wrapBody('~', '~')"><i class="ph ph-text-strikethrough text-base"></i></button>
                            <button type="button" class="row-action" title="Monospace" @click="wrapBody('```', '```')"><i class="ph ph-code text-base"></i></button>
                        </div>
                        <label class="w-48 shrink-0 max-sm:w-full">
                            <span class="sr-only">Insert shortcode</span>
                            <select class="form-input h-10 py-0 text-sm leading-10" x-model="selectedToken" @change="insertSelectedToken()">
                                <option value="">Insert shortcode...</option>
                                <template x-for="token in tokenOptions" :key="token.value">
                                    <option :value="token.value" x-text="token.label"></option>
                                </template>
                            </select>
                        </label>
                    </div>
                </div>

                <textarea x-ref="bodyInput" name="body" rows="8" required maxlength="1024" placeholder="Hello {{ '{' }}{{ '{' }}full_name{{ '}' }}{{ '}' }}, your order is ready." class="form-input mt-4" x-model="body"></textarea>
                <div class="mt-2 flex items-center justify-between gap-3 text-xs text-neutral-400">
                    <span>Use named shortcodes like {{ '{' }}{{ '{' }}full_name{{ '}' }}{{ '}' }}, {{ '{' }}{{ '{' }}phone{{ '}' }}{{ '}' }} or {{ '{' }}{{ '{' }}custom.order_id{{ '}' }}{{ '}' }}.</span>
                    <span><span x-text="body.length"></span>/1024</span>
                </div>
                @error('body')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror

                <div class="mt-4 grid gap-3 sm:grid-cols-2" x-show="bodyVariables.length">
                    <template x-for="variable in bodyVariables" :key="variable">
                        <label>
                            <span class="form-label">Example for <span x-text="'{' + '{' + variable + '}' + '}'"></span></span>
                            <input type="text" :name="`body_examples[${variable}]`" class="form-input" :placeholder="'Example ' + variable" x-model="bodyExamples[variable]" />
                        </label>
                    </template>
                </div>
                @error('body_examples')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
            </section>

            @if ($isWhatsApp)
            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">Footer</h3>
                <input name="footer[text]" type="text" maxlength="60" placeholder="Reply STOP to unsubscribe" class="form-input mt-4" x-model="footer.text" />
                @error('footer.text')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
            </section>
            @endif

            <section class="app-card p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="heading-4">Buttons</h3>
                        <p class="form-hint">Mix quick replies, website links and phone calls.</p>
                    </div>
                    <span class="badge badge-neutral"><span x-text="buttons.length"></span>/10 used</span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($isWhatsApp)
                        <button type="button" class="btn-sm btn-outline" @click="addButton('quick_reply')" :disabled="buttons.length >= 10">
                            <i class="ph ph-plus text-base"></i>Quick Reply
                        </button>
                    @endif
                    <button type="button" class="btn-sm btn-outline" @click="addButton('url')" :disabled="!canAddUrl">
                        <i class="ph ph-plus text-base"></i>Website
                    </button>
                    @if ($isWhatsApp)
                    <button type="button" class="btn-sm btn-outline" @click="addButton('phone_number')" :disabled="!canAddPhone">
                        <i class="ph ph-plus text-base"></i>Phone
                    </button>
                    @else
                    <button type="button" class="btn-sm btn-outline" @click="addButton('callback')" :disabled="buttons.length >= 10">
                        <i class="ph ph-plus text-base"></i>Callback
                    </button>
                    @endif
                </div>

                <div class="mt-4 space-y-3">
                    <template x-for="(button, index) in buttons" :key="button.id">
                        <div class="component-block">
                            <input type="hidden" :name="`buttons[${index}][type]`" x-model="button.type" />
                            <div class="flex items-center justify-between gap-3">
                                <span class="component-block__label" x-text="buttonLabel(button.type)"></span>
                                <button type="button" class="row-action" aria-label="Remove button" @click="removeButton(index)"><i class="ph ph-trash text-base"></i></button>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                <label>
                                    <span class="form-label">Button Text</span>
                                    <input type="text" maxlength="25" class="form-input" :name="`buttons[${index}][text]`" x-model="button.text" placeholder="Learn more" />
                                </label>
                                <label x-show="button.type === 'url'">
                                    <span class="form-label">Website URL</span>
                                    <input type="url" class="form-input" :name="`buttons[${index}][url]`" x-model="button.url" placeholder="https://example.com/{{ '{' }}{{ '{' }}1{{ '}' }}{{ '}' }}" />
                                </label>
                                <label x-show="button.type === 'url' && variablesFor(button.url).length">
                                    <span class="form-label">URL Example</span>
                                    <input type="text" class="form-input" :name="`buttons[${index}][example]`" x-model="button.example" placeholder="summer-sale" />
                                </label>
                                <label x-show="button.type === 'phone_number'">
                                    <span class="form-label">Phone Number</span>
                                    <input type="tel" class="form-input" :name="`buttons[${index}][phone_number]`" x-model="button.phone_number" placeholder="+15551234567" />
                                </label>
                                <label x-show="button.type === 'callback'">
                                    <span class="form-label">Callback Data</span>
                                    <input type="text" maxlength="64" class="form-input" :name="`buttons[${index}][callback_data]`" x-model="button.callback_data" placeholder="start_flow" />
                                </label>
                            </div>
                        </div>
                    </template>
                </div>
                @error('buttons')<p class="mt-1.5 text-xs font-semibold text-error">{{ $message }}</p>@enderror
            </section>

            <div class="flex flex-wrap items-center gap-3">
                @if ($isWhatsApp && ($wabas ?? collect())->count() > 1)
                    <label class="min-w-56">
                        <span class="sr-only">WhatsApp Business Account</span>
                        <select name="provider_account_id" class="form-input">
                            <option value="">Choose WABA for Meta submit...</option>
                            @foreach ($wabas as $waba)
                                <option value="{{ $waba->provider_account_id }}" @selected((string) $selectedWaba === (string) $waba->provider_account_id)>
                                    {{ $waba->name }} ({{ $waba->provider_account_id }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                @elseif ($isWhatsApp && ($wabas ?? collect())->count() === 1)
                    <input type="hidden" name="provider_account_id" value="{{ $wabas->first()->provider_account_id }}">
                @endif
                @if ($isWhatsApp)
                    <button type="submit" name="submit_to_meta" value="1" class="btn btn-primary">{{ $isEditing ? 'Update & Submit' : 'Submit Template' }}</button>
                    <button type="submit" name="submit_to_meta" value="0" class="btn btn-outline">{{ $isEditing ? 'Update Draft' : 'Save Draft' }}</button>
                @else
                    <button type="submit" name="submit_to_meta" value="0" class="btn btn-primary">{{ $isEditing ? 'Update Template' : 'Save Template' }}</button>
                @endif
                <a href="{{ route('user.message-templates.index', ['provider' => $provider]) }}" class="btn btn-outline">Cancel</a>
            </div>
        </div>

        <aside class="xl:sticky xl:top-24 xl:self-start">
            <div class="mx-auto flex w-full max-w-[20rem] flex-col items-center">
                <p class="w-full text-center text-xs font-bold tracking-wider text-neutral-400 uppercase">Live {{ $isWhatsApp ? 'WhatsApp' : 'Telegram' }} Preview</p>
                <div class="wa-phone wa-phone--compact wa-phone--centered {{ $isWhatsApp ? 'wa-phone--whatsapp' : 'wa-phone--telegram' }} mt-3 w-full">
                <div class="wa-phone__bar">
                    <span class="wa-phone__avatar"><i class="ph {{ $isWhatsApp ? 'ph-whatsapp-logo' : 'ph-telegram-logo' }}"></i></span>
                    <div>
                        <p class="wa-phone__name">{{ $isWhatsApp ? 'WaPro Business' : 'WaPro Bot' }}</p>
                        <p class="wa-phone__status">online</p>
                    </div>
                </div>
                <div class="wa-phone__screen">
                    <div class="wa-phone__bubble">
                        <template x-if="header.type === 'text' && header.text">
                            <div class="wa-phone__header" x-text="renderPlain(header.text, headerExampleMap)"></div>
                        </template>
                        <template x-if="isMediaHeader">
                            <div class="wa-phone__media">
                                <template x-if="header.type === 'image' && header.mediaUrl">
                                    <img :src="header.mediaUrl" alt="" />
                                </template>
                                <template x-if="header.type !== 'image' || !header.mediaUrl">
                                    <div class="wa-phone__media-placeholder">
                                        <i :class="mediaIcon"></i>
                                        <span x-text="header.mediaName || mediaLabel"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <div class="wa-phone__body" :class="{ 'text-neutral-400': !body.trim() }" x-html="renderedBody"></div>
                        <template x-if="footer.text">
                            <div class="wa-phone__footer" x-text="footer.text"></div>
                        </template>
                        <div class="wa-phone__time" x-text="previewTime"></div>
                        <template x-for="button in buttons" :key="button.id">
                            <div class="wa-phone__button">
                                <i :class="previewButtonIcon(button.type)"></i>
                                <span x-text="button.text || buttonLabel(button.type)"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            </div>
        </aside>
    </form>
</x-layouts.user>
