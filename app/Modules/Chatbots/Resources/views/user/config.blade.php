@php
    $isEditing = $chatbot !== null;
    $selectedKnowledgeBases = collect(old('knowledge_bases', $chatbot?->knowledgeBases?->pluck('id')->all() ?? []))->map(fn ($id) => (int) $id)->all();
    $handoffRules = $chatbot?->handoff_rules ?? [];
@endphp

<x-layouts.user :title="$isEditing ? __('Configure Chatbot') : __('New Chatbot')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('user.chatbots.index') }}" class="row-action" aria-label="{{ __('Back to chatbots') }}">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <h2 class="heading-2">{{ $isEditing ? $chatbot->name : __('New Chatbot') }}</h2>
                <p class="m-text mt-1">{{ __('Configure how this AI chatbot answers customers.') }}</p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-error/20 bg-error/10 px-4 py-3 text-sm font-semibold text-error">
            {{ __('Please fix the highlighted chatbot settings.') }}
        </div>
    @endif

    <form id="chatbot-config-form" method="POST" action="{{ $isEditing ? route('user.chatbots.update', $chatbot) : route('user.chatbots.store') }}" class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        @csrf
        @if ($isEditing)
            @method('PUT')
        @endif

        <div class="space-y-6">
            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">{{ __('Identity') }}</h3>

                <div class="mt-4">
                    <div>
                        <label for="name" class="form-label">{{ __('Bot name') }} <span class="text-error">*</span></label>
                        <input id="name" name="name" type="text" required value="{{ old('name', $chatbot?->name) }}" class="form-input">
                        @error('name')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between gap-3">
                        <label for="persona" class="form-label mb-0">{{ __('Persona & instructions') }} <span class="text-error">*</span></label>
                        @unless ($isEditing)
                            <button type="button" id="generate_persona_button" class="row-action" aria-label="{{ __('Generate persona with AI') }}" title="{{ __('Generate persona with AI') }}">
                                <i id="generate_persona_icon" class="ph ph-sparkle text-base"></i>
                            </button>
                        @endunless
                    </div>
                    <textarea id="persona" name="persona" rows="6" required class="form-input">{{ old('persona', $chatbot?->persona) }}</textarea>
                    @unless ($isEditing)
                        <p id="persona_ai_error" class="form-hint text-error hidden"></p>
                    @endunless
                    @error('persona')<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4">
                    <label for="greeting" class="form-label">{{ __('First message') }}</label>
                    <input id="greeting" name="greeting" type="text" value="{{ old('greeting', $chatbot?->greeting) }}" class="form-input">
                    @error('greeting')<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">{{ __('Model & Behavior') }}</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="max_tokens" class="form-label">{{ __('Max reply tokens') }}</label>
                        <input id="max_tokens" name="max_tokens" type="number" min="64" max="8192" value="{{ old('max_tokens', $chatbot?->max_tokens ?? 512) }}" class="form-input">
                        @error('max_tokens')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="temperature" class="form-label">{{ __('Creativity') }}</label>
                        <input id="temperature" name="temperature" type="number" min="0" max="2" step="0.1" value="{{ old('temperature', $chatbot?->temperature ?? 0.4) }}" class="form-input">
                        @error('temperature')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="confidence_threshold" class="form-label">{{ __('Handoff threshold') }}</label>
                        <input id="confidence_threshold" name="confidence_threshold" type="number" min="0" max="1" step="0.05" value="{{ old('confidence_threshold', $chatbot?->confidence_threshold ?? 0.7) }}" class="form-input">
                        @error('confidence_threshold')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="fallback_only_knowledge_base" value="1" class="app-checkbox" @checked(old('fallback_only_knowledge_base', $chatbot?->fallback_only_knowledge_base ?? true))>
                        <span class="text-sm text-body">{{ __('Only answer from selected knowledge bases') }}</span>
                    </label>
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="is_active" value="1" class="app-checkbox" @checked(old('is_active', $chatbot?->is_active ?? false))>
                        <span class="text-sm text-body">{{ __('Active') }}</span>
                    </label>
                </div>
            </section>

            <section class="app-card p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="heading-4">{{ __('Knowledge Bases') }}</h3>
                    <a href="{{ route('user.knowledge-bases.index') }}" class="btn-sm btn-outline">
                        <i class="ph ph-books text-base"></i>
                        {{ __('Manage') }}
                    </a>
                </div>

                <div class="mt-4 space-y-2.5">
                    @forelse ($knowledgeBases as $knowledgeBase)
                        <label class="check-row">
                            <input type="checkbox" name="knowledge_bases[]" value="{{ $knowledgeBase->id }}" class="app-checkbox" @checked(in_array($knowledgeBase->id, $selectedKnowledgeBases, true))>
                            <span class="min-w-0">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-title">{{ $knowledgeBase->name }}</span>
                                    <span class="badge badge-{{ $knowledgeBase->status === 'ready' ? 'success' : ($knowledgeBase->status === 'error' ? 'error' : 'warning') }}">{{ ucfirst($knowledgeBase->status) }}</span>
                                </span>
                                <span class="block text-xs text-neutral-400">{{ $knowledgeBase->description ?: __('No description') }}</span>
                                <span class="block text-xs text-neutral-400">{{ trans_choice(':count chunk|:count chunks', $knowledgeBase->chunks_count, ['count' => $knowledgeBase->chunks_count]) }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="m-text">{{ __('No knowledge bases have been created yet.') }}</p>
                    @endforelse
                </div>
                @error('knowledge_bases')<p class="form-hint text-error">{{ $message }}</p>@enderror
            </section>

            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">{{ __('Human Handoff') }}</h3>
                <div class="mt-4 space-y-3">
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="handoff_on_request" value="1" class="app-checkbox" @checked(old('handoff_on_request', data_get($handoffRules, 'on_request', true)))>
                        <span class="text-sm text-body">{{ __('When the customer asks for a human') }}</span>
                    </label>
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="handoff_on_unsure" value="1" class="app-checkbox" @checked(old('handoff_on_unsure', data_get($handoffRules, 'on_unsure', true)))>
                        <span class="text-sm text-body">{{ __('When confidence is below the threshold') }}</span>
                    </label>
                    <label class="flex items-center gap-2.5">
                        <input type="checkbox" name="handoff_off_hours" value="1" class="app-checkbox" @checked(old('handoff_off_hours', data_get($handoffRules, 'off_hours', false)))>
                        <span class="text-sm text-body">{{ __('Outside business hours') }}</span>
                    </label>
                </div>
                <div class="mt-4">
                    <label for="handoff_message" class="form-label">{{ __('Handoff message') }}</label>
                    <input id="handoff_message" name="handoff_message" type="text" value="{{ old('handoff_message', data_get($handoffRules, 'message')) }}" class="form-input">
                </div>
            </section>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary">{{ $isEditing ? __('Save Configuration') : __('Create Chatbot') }}</button>
                <a href="{{ route('user.chatbots.index') }}" class="btn btn-outline">{{ __('Cancel') }}</a>
            </div>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="app-card flex flex-col p-5">
                <div class="f-between">
                    <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Test panel') }}</p>
                    <span class="f-start gap-1.5 text-xs font-semibold {{ $isEditing && $chatbot->is_active ? 'text-success' : 'text-neutral-400' }}">
                        <span class="status-dot {{ $isEditing && $chatbot->is_active ? 'bg-success' : 'bg-neutral-300' }}"></span>
                        {{ $isEditing && $chatbot->is_active ? __('Active') : __('Draft') }}
                    </span>
                </div>

                @if ($isEditing)
                    <div class="mt-4 space-y-3 rounded-2xl bg-section p-4">
                        @if ($chatbot->greeting)
                            <div class="flex justify-start">
                                <p class="max-w-[85%] rounded-2xl rounded-tl-sm bg-neutral-0 px-3 py-2 text-sm text-title shadow-sm">{{ $chatbot->greeting }}</p>
                            </div>
                        @endif
                        <div class="flex justify-end">
                            <p class="max-w-[85%] rounded-2xl rounded-tr-sm bg-primary px-3 py-2 text-sm text-neutral-0">{{ __('Can you help me with pricing?') }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <input type="text" id="test_message" placeholder="{{ __('Type a test message...') }}" class="form-input flex-1">
                        <button type="button" class="btn btn-primary" aria-label="{{ __('Send') }}" onclick="testChatbot()">
                            <i class="ph ph-paper-plane-tilt text-base"></i>
                        </button>
                    </div>
                    <p id="test_reply" class="form-hint mt-2">{{ __("Test messages use your saved settings and don't reach real contacts.") }}</p>
                @else
                    <p class="m-text mt-4">{{ __('Save this chatbot before running a test message.') }}</p>
                @endif
            </div>
        </aside>
    </form>

    @unless ($isEditing)
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    'use strict';

                    const form = document.getElementById('chatbot-config-form');
                    const button = document.getElementById('generate_persona_button');
                    const icon = document.getElementById('generate_persona_icon');
                    const error = document.getElementById('persona_ai_error');
                    const persona = document.getElementById('persona');

                    if (!form || !button || !icon || !error || !persona) {
                        return;
                    }

                    const field = (name) => form.querySelector(`[name="${name}"]`);
                    const selectedKnowledgeBases = () => Array.from(form.querySelectorAll('input[name="knowledge_bases[]"]:checked'))
                        .map((input) => input.value);

                    button.addEventListener('click', async () => {
                        error.textContent = '';
                        error.classList.add('hidden');
                        button.disabled = true;
                        icon.className = 'ph ph-circle-notch animate-spin text-base';

                        try {
                            const response = await fetch('{{ route('user.chatbots.persona.generate') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                },
                                body: JSON.stringify({
                                    name: field('name')?.value || null,
                                    knowledge_bases: selectedKnowledgeBases(),
                                    greeting: field('greeting')?.value || null,
                                    instruction: persona.value || null,
                                }),
                            });
                            const data = await response.json();

                            if (!response.ok) {
                                const errors = data.errors || {};
                                const first = Object.values(errors)[0];
                                throw new Error(Array.isArray(first) ? first[0] : (data.message || '{{ __('AI could not generate persona instructions.') }}'));
                            }

                            persona.value = data.persona || '';
                            persona.focus();
                        } catch (exception) {
                            error.textContent = exception.message || '{{ __('AI could not generate persona instructions.') }}';
                            error.classList.remove('hidden');
                        } finally {
                            button.disabled = false;
                            icon.className = 'ph ph-sparkle text-base';
                        }
                    });
                });
            </script>
        @endpush
    @endunless

    @if ($isEditing)
        @push('scripts')
            <script>
                async function testChatbot() {
                    const input = document.getElementById('test_message');
                    const output = document.getElementById('test_reply');
                    output.textContent = '{{ __('Testing...') }}';

                    const response = await fetch('{{ route('user.chatbots.test', $chatbot) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ message: input.value }),
                    });

                    const data = await response.json();
                    output.textContent = data.reply || data.message || '{{ __('Unable to test this chatbot.') }}';
                }
            </script>
        @endpush
    @endif
</x-layouts.user>
