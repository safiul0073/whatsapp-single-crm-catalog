@php
    $isEditing = $widget !== null;
    $settings = $widget?->settings ?? [];
    $leadFields = collect(old('lead_fields', $widget?->lead_fields ?? ['name', 'email']))->all();
    $domains = implode("\n", old('allowed_domains', $widget?->allowed_domains ?? []));
    $selectedChatbotId = (string) old('chatbot_id', $widget?->chatbot_id ?? '');
    $selectedChatbotName = optional($chatbots->firstWhere('id', (int) $selectedChatbotId))->name ?? __('No chatbot selected');
    $embedCode = $isEditing ? '<script src="'.route('widgets.chatbot.loader', $widget->public_token).'" async></script>' : '';
    $automatedReplyEnabled = (bool) old('automated_reply_enabled', $widget?->automatedReplyEnabled() ?? true);
@endphp

<x-layouts.user :title="$isEditing ? __('Edit Website Widget') : __('New Website Widget')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('user.chatbots.widgets.index') }}" class="row-action" aria-label="{{ __('Back to widgets') }}">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <h2 class="heading-2">{{ $isEditing ? $widget->name : __('New Website Widget') }}</h2>
                <p class="m-text mt-1">{{ __('Attach a chatbot, tune the launcher, restrict domains, and publish the embed script.') }}</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-error/20 bg-error/10 px-4 py-3 text-sm text-error">
            {{ __('Please fix the highlighted widget settings.') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ $isEditing ? route('user.chatbots.widgets.update', $widget) : route('user.chatbots.widgets.store') }}"
        class="mt-6"
        x-data="{
            step: 1,
            maxStep: 4,
            name: @js(old('name', $widget?->name ?? '')),
            chatbotId: @js($selectedChatbotId),
            chatbotName: @js($selectedChatbotName),
            greeting: @js(old('greeting', $widget?->greeting ?? '')),
            primaryColor: @js(old('primary_color', data_get($settings, 'primary_color', '#16a34a'))),
            position: @js(old('position', data_get($settings, 'position', 'right'))),
            launcherLabel: @js(old('launcher_label', data_get($settings, 'launcher_label', 'Chat'))),
            domains: @js($domains),
            leadFields: @js($leadFields),
            isActive: @js((bool) old('is_active', $widget?->is_active ?? true)),
            automatedReplyEnabled: @js($automatedReplyEnabled),
            embedCode: @js($embedCode),
            fieldLabels: { name: @js(__('Name')), email: @js(__('Email')), phone: @js(__('Phone')) },
            steps: [
                { number: 1, label: @js(__('Basic info')), icon: 'ph-identification-card' },
                { number: 2, label: @js(__('Appearance')), icon: 'ph-palette' },
                { number: 3, label: @js(__('Allowed domains')), icon: 'ph-globe-hemisphere-west' },
                { number: 4, label: @js(__('Embed Codes')), icon: 'ph-code' },
            ],
            goTo(target) { this.step = Math.min(this.maxStep, Math.max(1, target)); },
            next() { this.goTo(this.step + 1); },
            previous() { this.goTo(this.step - 1); },
            domainList() {
                return this.domains.split(/\r?\n/).map((domain) => domain.trim()).filter(Boolean);
            },
            leadFieldSummary() {
                if (! this.leadFields.length) {
                    return @js(__('None'));
                }

                return this.leadFields.map((field) => this.fieldLabels[field] || field).join(', ');
            },
            statusLabel() {
                return this.isActive ? @js(__('Active')) : @js(__('Paused'));
            },
        }"
    >
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="space-y-6">
                <div class="app-card p-4 sm:p-5">
                    <div class="grid gap-2 sm:grid-cols-4">
                        <template x-for="item in steps" :key="item.number">
                            <button type="button" class="flex min-h-16 items-center gap-3 rounded-lg border px-3 py-2 text-left transition"
                                :class="step === item.number ? 'border-primary bg-primary/10 text-primary' : (step > item.number ? 'border-success/20 bg-success/10 text-success' : 'border-neutral-100 bg-section text-body hover:border-primary/30 hover:text-title')"
                                @click="goTo(item.number)">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-neutral-0">
                                    <i class="ph text-lg" :class="step > item.number ? 'ph-check-circle' : item.icon"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-neutral-400" x-text="`{{ __('Step') }} ${item.number}`"></span>
                                    <span class="block truncate text-sm font-bold" x-text="item.label"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                <section class="app-card p-5 sm:p-6" x-show="step === 1" x-cloak>
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-identification-card text-xl"></i>
                        </span>
                        <div>
                            <h3 class="heading-4">{{ __('Basic info') }}</h3>
                            <p class="m-text mt-1">{{ __('Name the widget and connect it to the chatbot visitors will talk to.') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="name" class="form-label">{{ __('Widget name') }}</label>
                            <input id="name" name="name" type="text" required x-model="name" value="{{ old('name', $widget?->name) }}" class="form-input">
                            @error('name')<p class="form-hint text-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="chatbot_id" class="form-label">{{ __('Chatbot') }}</label>
                            <select id="chatbot_id" name="chatbot_id" required x-model="chatbotId" class="form-input"
                                @change="chatbotName = $event.target.selectedOptions[0]?.text || @js(__('No chatbot selected'))">
                                <option value="">{{ __('Select chatbot') }}</option>
                                @foreach ($chatbots as $chatbot)
                                    <option value="{{ $chatbot->id }}" @selected((int) old('chatbot_id', $widget?->chatbot_id) === $chatbot->id)>{{ $chatbot->name }}</option>
                                @endforeach
                            </select>
                            @error('chatbot_id')<p class="form-hint text-error">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="greeting" class="form-label">{{ __('Greeting') }}</label>
                        <input id="greeting" name="greeting" type="text" x-model="greeting" value="{{ old('greeting', $widget?->greeting) }}" class="form-input" placeholder="{{ __('Hi, how can I help?') }}">
                        @error('greeting')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-5 rounded-lg border border-neutral-100 bg-section p-4">
                        <label class="flex items-center justify-between gap-3">
                            <span>
                                <span class="block text-sm font-bold text-title">{{ __('Active') }}</span>
                                <span class="m-text text-xs">{{ __('Allow this script to answer visitors after it is embedded.') }}</span>
                            </span>
                            <input type="checkbox" name="is_active" value="1" x-model="isActive" class="app-checkbox" @checked(old('is_active', $widget?->is_active ?? true))>
                        </label>
                    </div>

                    <div class="mt-4 rounded-lg border border-neutral-100 bg-section p-4">
                        <label class="flex items-center justify-between gap-3">
                            <span>
                                <span class="block text-sm font-bold text-title">{{ __('Automated chatbot replies') }}</span>
                                <span class="m-text text-xs">{{ __('When enabled, the chatbot answers widget visitors and Inbox replies are locked for this widget.') }}</span>
                            </span>
                            <input type="checkbox" name="automated_reply_enabled" value="1" x-model="automatedReplyEnabled" class="app-checkbox" @checked($automatedReplyEnabled)>
                        </label>
                    </div>
                </section>

                <section class="app-card p-5 sm:p-6" x-show="step === 2" x-cloak>
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-palette text-xl"></i>
                        </span>
                        <div>
                            <h3 class="heading-4">{{ __('Appearance') }}</h3>
                            <p class="m-text mt-1">{{ __('Tune the launcher color, position, button copy, and visitor fields.') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="primary_color" class="form-label">{{ __('Color') }}</label>
                            <input id="primary_color" name="primary_color" type="color" x-model="primaryColor" value="{{ old('primary_color', data_get($settings, 'primary_color', '#16a34a')) }}" class="h-11 w-full rounded-lg border border-neutral-200 bg-neutral-0 p-1">
                            @error('primary_color')<p class="form-hint text-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="position" class="form-label">{{ __('Position') }}</label>
                            <select id="position" name="position" x-model="position" class="form-input">
                                <option value="right">{{ __('Right') }}</option>
                                <option value="left">{{ __('Left') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="launcher_label" class="form-label">{{ __('Button label') }}</label>
                            <input id="launcher_label" name="launcher_label" type="text" required x-model="launcherLabel" value="{{ old('launcher_label', data_get($settings, 'launcher_label', 'Chat')) }}" class="form-input">
                        </div>
                    </div>

                    <div class="mt-5">
                        <p class="form-label">{{ __('Visitor form') }}</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach (['name' => __('Name'), 'email' => __('Email'), 'phone' => __('Phone')] as $field => $label)
                                <label class="flex min-h-11 items-center gap-2 rounded-lg border border-neutral-100 bg-section px-3 py-2 text-sm font-semibold text-title">
                                    <input type="checkbox" name="lead_fields[]" value="{{ $field }}" x-model="leadFields" class="app-checkbox" @checked(in_array($field, $leadFields, true))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="app-card p-5 sm:p-6" x-show="step === 3" x-cloak>
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-globe-hemisphere-west text-xl"></i>
                        </span>
                        <div>
                            <h3 class="heading-4">{{ __('Allowed domains') }}</h3>
                            <p class="m-text mt-1">{{ __('Limit where this widget script can load. Leave empty only when every domain should be accepted.') }}</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label for="allowed_domains" class="form-label">{{ __('Domains') }}</label>
                        <textarea id="allowed_domains" name="allowed_domains" rows="8" x-model="domains" class="form-input" placeholder="example.com&#10;shop.example.com">{{ $domains }}</textarea>
                        <p class="form-hint mt-1">{{ __('One domain per line. Localhost is allowed automatically for development.') }}</p>
                        @error('allowed_domains')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-5 rounded-lg border border-neutral-100 bg-section p-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Domain summary') }}</p>
                        <template x-if="domainList().length">
                            <div class="mt-3 flex flex-wrap gap-2">
                                <template x-for="domain in domainList()" :key="domain">
                                    <span class="badge badge-soft" x-text="domain"></span>
                                </template>
                            </div>
                        </template>
                        <p class="m-text mt-2 text-sm" x-show="!domainList().length">{{ __('No production domains restricted yet.') }}</p>
                    </div>
                </section>

                <section class="app-card p-5 sm:p-6" x-show="step === 4" x-cloak>
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                            <i class="ph ph-code text-xl"></i>
                        </span>
                        <div>
                            <h3 class="heading-4">{{ __('Embed Codes') }}</h3>
                            <p class="m-text mt-1">{{ __('Review the setup and use the generated script on your website.') }}</p>
                        </div>
                    </div>

                    @if ($isEditing)
                        <div class="mt-5">
                            <label for="embed_code" class="form-label">{{ __('Script tag') }}</label>
                            <textarea id="embed_code" readonly rows="3" class="form-input font-mono text-xs">{{ $embedCode }}</textarea>
                        </div>
                    @else
                        <div class="mt-5 rounded-lg border border-warning/20 bg-warning/10 p-4 text-sm font-semibold text-warning">
                            {{ __('Save the widget to generate its public embed code.') }}
                        </div>
                    @endif

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-neutral-100 bg-section p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Publish status') }}</p>
                            <p class="mt-2 text-lg font-bold text-title" x-text="statusLabel()"></p>
                        </div>
                        <div class="rounded-lg border border-neutral-100 bg-section p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Launcher') }}</p>
                            <p class="mt-2 text-lg font-bold text-title" x-text="launcherLabel || @js(__('Chat'))"></p>
                        </div>
                        <div class="rounded-lg border border-neutral-100 bg-section p-4 sm:col-span-2">
                            <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Reply mode') }}</p>
                            <p class="mt-2 text-lg font-bold text-title" x-text="automatedReplyEnabled ? @js(__('Automated chatbot')) : @js(__('Inbox team reply'))"></p>
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button type="button" class="btn btn-outline" @click="previous()" :disabled="step === 1" :class="step === 1 ? 'opacity-50' : ''">
                        <i class="ph ph-arrow-left text-base"></i>
                        {{ __('Back') }}
                    </button>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('user.chatbots.widgets.index') }}" class="btn btn-outline">{{ __('Cancel') }}</a>
                        <button type="button" class="btn btn-primary" x-show="step < maxStep" @click="next()">
                            {{ __('Next') }}
                            <i class="ph ph-arrow-right text-base"></i>
                        </button>
                        <button type="submit" class="btn btn-primary" x-show="step === maxStep">
                            {{ $isEditing ? __('Update widget') : __('Create widget') }}
                        </button>
                    </div>
                </div>
            </div>

            <aside class="space-y-6 xl:sticky xl:top-6 xl:self-start">
                <div class="app-card overflow-hidden">
                    <div class="border-b border-neutral-100 p-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Preview') }}</p>
                        <h3 class="heading-4 mt-1" x-text="name || @js(__('Website widget'))"></h3>
                    </div>
                    <div class="bg-section p-5">
                        <div class="relative mx-auto min-h-96 max-w-sm rounded-2xl border border-neutral-200 bg-neutral-0 p-4 shadow-sm">
                            <div class="rounded-xl border border-neutral-100">
                                <div class="flex items-center gap-2 rounded-t-xl px-4 py-3 text-neutral-0" :style="`background-color: ${primaryColor}`">
                                    <span class="grid h-8 w-8 place-items-center rounded-full bg-neutral-0/20">
                                        <i class="ph ph-robot text-lg"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold" x-text="name || @js(__('Website widget'))"></p>
                                        <p class="text-xs opacity-80" x-text="chatbotName"></p>
                                    </div>
                                </div>
                                <div class="space-y-3 p-4">
                                    <div class="max-w-[82%] rounded-xl rounded-tl-sm bg-section px-3 py-2 text-sm text-title" x-text="greeting || @js(__('Hi, how can I help?'))"></div>
                                    <div class="ml-auto max-w-[82%] rounded-xl rounded-tr-sm px-3 py-2 text-sm text-neutral-0" :style="`background-color: ${primaryColor}`">{{ __('I need help with my order.') }}</div>
                                    <div class="max-w-[82%] rounded-xl rounded-tl-sm bg-section px-3 py-2 text-sm text-title">{{ __('Sure, I can help with that.') }}</div>
                                </div>
                            </div>

                            <div class="absolute bottom-4 flex items-center gap-2" :class="position === 'left' ? 'left-4' : 'right-4'">
                                <button type="button" class="inline-flex h-11 items-center gap-2 rounded-full px-4 text-sm font-bold text-neutral-0 shadow-lg" :style="`background-color: ${primaryColor}`">
                                    <i class="ph ph-chat-circle-dots text-base"></i>
                                    <span x-text="launcherLabel || @js(__('Chat'))"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="app-card p-5">
                    <p class="text-xs font-bold uppercase tracking-wider text-neutral-400">{{ __('Summary') }}</p>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-body">{{ __('Chatbot') }}</dt>
                            <dd class="max-w-48 truncate font-semibold text-title" x-text="chatbotName"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-body">{{ __('Status') }}</dt>
                            <dd class="font-semibold text-title" x-text="statusLabel()"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-body">{{ __('Reply mode') }}</dt>
                            <dd class="text-right font-semibold text-title" x-text="automatedReplyEnabled ? @js(__('Automated')) : @js(__('Inbox'))"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-body">{{ __('Position') }}</dt>
                            <dd class="font-semibold text-title capitalize" x-text="position"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-body">{{ __('Lead fields') }}</dt>
                            <dd class="max-w-48 text-right font-semibold text-title" x-text="leadFieldSummary()"></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-body">{{ __('Domains') }}</dt>
                            <dd class="font-semibold text-title" x-text="domainList().length"></dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </div>
    </form>
</x-layouts.user>
