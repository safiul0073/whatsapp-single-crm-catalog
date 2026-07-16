<x-layouts.user :title="__('Template Library')">
    @php
        $provider = $provider ?? 'whatsapp';
        $isWhatsApp = $provider === 'whatsapp';
    @endphp

    <div
        x-data="{
            aiModalOpen: false,
            aiGenerating: false,
            aiPrompt: '',
            aiError: '',
            openAiModal() {
                this.aiError = '';
                this.aiModalOpen = true;
                this.$nextTick(() => this.$refs.aiPrompt?.focus());
            },
            closeAiModal() {
                if (this.aiGenerating) return;
                this.aiModalOpen = false;
                this.aiError = '';
            },
            errorMessage(data, fallback) {
                const errors = data?.errors || {};
                const first = Object.values(errors)[0];
                if (Array.isArray(first) && first.length > 0) return first[0];
                return data?.message || fallback;
            },
            async generateTemplate() {
                if (this.aiGenerating) return;
                this.aiGenerating = true;
                this.aiError = '';

                try {
                    const response = await fetch(@js(route('user.message-templates.generate')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.content || '',
                        },
                        body: JSON.stringify({
                            provider: @js($provider),
                            language: @js($isWhatsApp ? 'en_US' : 'en'),
                            category: @js($isWhatsApp ? 'marketing' : 'utility'),
                            prompt: this.aiPrompt,
                        }),
                    });
                    const data = await response.json();

                    if (! response.ok) {
                        throw new Error(this.errorMessage(data, 'AI could not generate a template.'));
                    }

                    window.location.href = data.redirect_url;
                } catch (error) {
                    this.aiError = error.message || 'AI could not generate a template.';
                } finally {
                    this.aiGenerating = false;
                }
            },
        }"
    >
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">Template Library</h2>
            <p class="m-text mt-1">{{ $isWhatsApp ? 'Build reusable WhatsApp messages and track their Meta approval status.' : 'Build reusable Telegram messages for bot campaigns.' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($isWhatsApp)
                <form method="POST" action="{{ route('user.message-templates.sync') }}">
                    @csrf
                    <button type="submit" class="btn-sm btn-outline">
                        <i class="ph ph-arrows-clockwise text-base"></i>
                        Sync from Meta
                    </button>
                </form>
            @endif
            <button type="button" class="btn-sm btn-outline" @click="openAiModal()">
                <i class="ph ph-sparkle text-base"></i>
                Generate with AI
            </button>
            <a href="{{ route('user.message-templates.create', ['provider' => $provider]) }}" class="btn-sm btn-primary">
                <i class="ph ph-plus text-base"></i>
                New Template
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-error/20 bg-error/10 px-4 py-3 text-sm font-medium text-error">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="mt-6 overflow-x-auto scrollbar-hide">
        <div class="inline-flex rounded-full border border-neutral-200 bg-neutral-0 p-1">
            <a href="{{ route('user.message-templates.index', ['provider' => 'whatsapp']) }}" class="range-btn {{ $isWhatsApp ? 'is-active' : '' }}">
                <i class="ph ph-whatsapp-logo text-base"></i>
                WhatsApp
            </a>
            <a href="{{ route('user.message-templates.index', ['provider' => 'telegram']) }}" class="range-btn {{ $provider === 'telegram' ? 'is-active' : '' }}">
                <i class="ph ph-telegram-logo text-base"></i>
                Telegram
            </a>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="stat-card">
            <div class="f-between">
                <p class="text-sm font-semibold text-body">Total templates</p>
                <span class="stat-card__icon"><i class="ph ph-cards-three text-base"></i></span>
            </div>
            <p class="display-4 mt-3 font-title font-extrabold text-title" data-stat="total">{{ $stats['total'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-body">across all categories</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-sm font-semibold text-body">Live &amp; approved</p>
                <span class="stat-card__icon"><i class="ph ph-seal-check text-base"></i></span>
            </div>
            <p class="display-4 mt-3 font-title font-extrabold text-title" data-stat="approved">{{ $stats['approved'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-body">{{ $isWhatsApp ? 'ready to send' : 'local templates' }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-sm font-semibold text-body">Awaiting review</p>
                <span class="stat-card__icon"><i class="ph ph-hourglass-medium text-base"></i></span>
            </div>
            <p class="display-4 mt-3 font-title font-extrabold text-title" data-stat="pending">{{ $stats['pending'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-body">{{ $isWhatsApp ? 'pending with Meta' : 'awaiting action' }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-sm font-semibold text-body">Needs attention</p>
                <span class="stat-card__icon"><i class="ph ph-warning-octagon text-base"></i></span>
            </div>
            <p class="display-4 mt-3 font-title font-extrabold text-title" data-stat="rejected">{{ $stats['rejected'] ?? 0 }}</p>
            <p class="mt-1 text-xs text-body">rejected, fix &amp; resubmit</p>
        </div>
    </div>

    <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
        <div class="overflow-x-auto scrollbar-hide">
            <div data-range-group data-status-filter data-range-value="all" class="inline-flex rounded-full border border-neutral-200 bg-neutral-0 p-1">
                <button type="button" class="range-btn is-active" data-range="all">All</button>
                <button type="button" class="range-btn" data-range="approved">Approved</button>
                <button type="button" class="range-btn" data-range="pending">Pending</button>
                <button type="button" class="range-btn" data-range="rejected">Rejected</button>
                <button type="button" class="range-btn" data-range="draft">Draft</button>
            </div>
        </div>

        <form class="relative w-full min-w-0 lg:max-w-xs" role="search">
            <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
            <input type="search" name="q" placeholder="Search templates..." class="form-input input-search" data-template-search />
        </form>
    </div>

    <div data-template-list>
        @forelse ($templates->groupBy('category') as $category => $categoryTemplates)
            <section class="mt-8" data-category-group>
                <div class="flex items-center gap-3">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                        <i class="ph ph-{{ $category === 'utility' ? 'gear-six' : ($category === 'authentication' ? 'shield-check' : 'megaphone') }} text-base"></i>
                    </span>
                    <h3 class="font-title text-lg font-bold text-title">{{ Str::headline($category) }}</h3>
                    <span class="badge badge-neutral" data-category-count>{{ $categoryTemplates->count() }}</span>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-category-grid>
                    @foreach ($categoryTemplates as $template)
                        @php
                            $components = collect($template->components ?? []);
                            $body = (string) data_get($components->firstWhere('type', 'BODY'), 'text', 'No body content saved.');
                            $footer = data_get($components->firstWhere('type', 'FOOTER'), 'text');
                            $status = $template->status->value;
                            $submissions = $template->submissions ?? collect();
                            $latestFailedSubmission = $submissions
                                ->filter(fn ($submission) => in_array($submission->status?->value, ['failed', 'rejected', 'disabled', 'paused', 'pending_deletion'], true))
                                ->sortByDesc('updated_at')
                                ->first();
                            $approvedCount = $submissions->filter(fn ($submission) => $submission->status?->value === 'approved')->count();
                            $pendingCount = $submissions->filter(fn ($submission) => in_array($submission->status?->value, ['submitted', 'pending', 'in_appeal'], true))->count();
                            $rejectedCount = $submissions->filter(fn ($submission) => in_array($submission->status?->value, ['rejected', 'failed', 'disabled', 'paused', 'pending_deletion'], true))->count();
                            $badgeClass = match ($status) {
                                'approved' => 'badge-success',
                                'rejected', 'failed', 'disabled' => 'badge-error',
                                'submitted', 'pending', 'in_appeal' => 'badge-warning',
                                default => 'badge-neutral',
                            };
                            $filterStatus = in_array($status, ['submitted', 'pending', 'in_appeal'], true) ? 'pending' : $status;
                        @endphp
                        <article class="template-card" data-template data-status="{{ $filterStatus }}" data-template-name="{{ $template->name }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h4 class="truncate font-title font-bold text-title">{{ $template->name }}</h4>
                                    <p class="mt-0.5 text-xs font-semibold tracking-wide text-neutral-400">
                                        {{ Str::upper($template->category) }} · {{ $template->language }}
                                    </p>
                                </div>
                                <span class="badge {{ $badgeClass }} shrink-0">{{ Str::headline($status) }}</span>
                            </div>

                            @if ($isWhatsApp)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="badge {{ $approvedCount ? 'badge-success' : 'badge-neutral' }}">{{ $approvedCount }} WABA{{ $approvedCount === 1 ? '' : 's' }} approved</span>
                                    @if ($pendingCount)
                                        <span class="badge badge-warning">{{ $pendingCount }} pending</span>
                                    @endif
                                    @if ($rejectedCount)
                                        <span class="badge badge-error">{{ $rejectedCount }} needs attention</span>
                                    @endif
                                </div>
                            @endif

                            @if ($latestFailedSubmission?->metaErrorMessage())
                                <div class="mt-3 rounded-xl border border-error/20 bg-error/10 px-3 py-2 text-xs text-error">
                                    @if ($latestFailedSubmission->metaErrorTitle())
                                        <p class="font-semibold">{{ $latestFailedSubmission->metaErrorTitle() }}</p>
                                    @endif
                                    <p class="{{ $latestFailedSubmission->metaErrorTitle() ? 'mt-1' : '' }}">{{ $latestFailedSubmission->metaErrorMessage() }}</p>
                                </div>
                            @endif

                            <div class="template-card__chat">
                                <div class="template-card__bubble">
                                    <div class="template-card__body">{{ $body }}</div>
                                    @if ($footer)
                                        <div class="template-card__footer">{{ $footer }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                                @if ($isWhatsApp)
                                    <form method="POST" action="{{ route('user.message-templates.submit', $template) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        @if (($wabas ?? collect())->count() > 1)
                                            <select name="provider_account_id" class="form-input h-9 max-w-48 py-1.5 text-xs">
                                                <option value="">Choose WABA...</option>
                                                @foreach ($wabas as $waba)
                                                    <option value="{{ $waba->provider_account_id }}">{{ $waba->name }}</option>
                                                @endforeach
                                            </select>
                                        @elseif (($wabas ?? collect())->count() === 1)
                                            <input type="hidden" name="provider_account_id" value="{{ $wabas->first()->provider_account_id }}">
                                        @endif
                                        <button type="submit" class="btn-sm btn-outline">
                                            <i class="ph ph-paper-plane-tilt text-base"></i>Submit to Meta
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('user.message-templates.edit', $template) }}" class="btn-sm btn-outline">
                                    <i class="ph ph-pencil-simple text-base"></i>Edit
                                </a>
                                <form method="POST" action="{{ route('user.message-templates.destroy', $template) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-sm btn-outline text-error hover:border-error hover:text-error" data-confirm data-confirm-title="Delete template?" data-confirm-body="This template will be permanently deleted and can no longer be used in campaigns. This cannot be undone." data-confirm-label="Delete" data-confirm-variant="error">
                                        <i class="ph ph-trash text-base"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="mt-8">
                <div class="card-soft p-8 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-file-text text-2xl"></i>
                    </span>
                    <h3 class="heading-4 mt-4">No templates yet</h3>
                    <p class="m-text mx-auto mt-2 max-w-md">{{ $isWhatsApp ? 'Create your first WhatsApp template, submit it to Meta, then use approved templates in campaigns.' : 'Create your first Telegram template, then use it in Telegram campaigns.' }}</p>
                    <a href="{{ route('user.message-templates.create', ['provider' => $provider]) }}" class="btn-sm btn-primary mt-5">
                        <i class="ph ph-plus text-base"></i>
                        New Template
                    </a>
                    <button type="button" class="btn-sm btn-outline mt-3" @click="openAiModal()">
                        <i class="ph ph-sparkle text-base"></i>
                        Generate with AI
                    </button>
                </div>
            </section>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $templates->links() }}
    </div>

    <div
        x-show="aiModalOpen"
        x-cloak
        class="fixed inset-0 z-50 grid place-items-center bg-neutral-950/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="aiTemplateTitle"
        @keydown.escape.window="closeAiModal()"
    >
        <div class="absolute inset-0" @click="closeAiModal()"></div>
        <form class="relative w-full max-w-2xl rounded-2xl bg-neutral-0 p-5 shadow-xl sm:p-6" @submit.prevent="generateTemplate()">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 id="aiTemplateTitle" class="heading-4">Generate {{ $isWhatsApp ? 'WhatsApp' : 'Telegram' }} Template</h3>
                    <p class="m-text mt-1">Describe the template you need. AI will create a draft and open it on the edit page.</p>
                </div>
                <button type="button" class="row-action shrink-0" aria-label="Close" @click="closeAiModal()">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <div class="mt-5 rounded-xl border border-primary/20 bg-primary/10 px-4 py-3 text-sm text-title">
                @if ($isWhatsApp)
                    <p class="font-semibold">WhatsApp template guidance</p>
                    <p class="mt-1 text-body">Ask for a reusable Meta template with a clear purpose, category, optional short header, helpful body, examples for placeholders, and buttons if needed. Avoid starting or ending the body with variables.</p>
                @else
                    <p class="font-semibold">Telegram template guidance</p>
                    <p class="mt-1 text-body">Ask for a bot campaign message with named shortcodes, concise body text, and URL or callback buttons when useful. Telegram drafts do not need Meta approval fields.</p>
                @endif
            </div>

            <label for="aiTemplatePrompt" class="form-label mt-5">Prompt <span class="text-error">*</span></label>
            <textarea
                id="aiTemplatePrompt"
                x-ref="aiPrompt"
                x-model="aiPrompt"
                rows="6"
                maxlength="1000"
                required
                class="form-input mt-2"
                placeholder="{{ $isWhatsApp ? 'Create a marketing template for a weekend coffee discount with one quick reply button.' : 'Create a Telegram reminder asking subscribers to confirm their appointment with a callback button.' }}"
            ></textarea>
            <div class="mt-2 flex items-center justify-between gap-3 text-xs text-neutral-400">
                <span>{{ $isWhatsApp ? 'The generated draft will be saved as a WhatsApp template draft.' : 'The generated draft will be saved as a Telegram template.' }}</span>
                <span><span x-text="aiPrompt.length"></span>/1000</span>
            </div>
            <p class="mt-2 text-sm font-semibold text-error" x-show="aiError" x-text="aiError" x-cloak></p>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
                <button type="button" class="btn-sm btn-outline" @click="closeAiModal()" :disabled="aiGenerating">Cancel</button>
                <button type="submit" class="btn-sm btn-primary" :disabled="aiGenerating || !aiPrompt.trim()">
                    <i class="ph text-base" :class="aiGenerating ? 'ph-circle-notch animate-spin' : 'ph-sparkle'"></i>
                    <span x-text="aiGenerating ? 'Generating...' : 'Generate Template'"></span>
                </button>
            </div>
        </form>
    </div>
    </div>
</x-layouts.user>
