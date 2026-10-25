<x-layouts.user :title="__('Knowledge Bases')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('user.chatbots.index') }}" class="row-action" aria-label="{{ __('Back to chatbots') }}">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <h2 class="heading-2">{{ __('Knowledge Bases') }}</h2>
                <p class="m-text mt-1">{{ __('What your chatbot knows. Add text, questions and answers, web pages or files.') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('user.knowledge-bases.refresh-defaults') }}">
                @csrf
                <button type="submit" class="btn-sm btn-outline">
                    <i class="ph ph-arrows-clockwise text-base"></i>
                    {{ __('Refresh from store data') }}
                </button>
            </form>
        <button type="button" class="btn-sm btn-primary" data-modal-open="createKnowledgeBase">
            <i class="ph ph-plus text-base"></i>
            {{ __('New Knowledge Base') }}
        </button>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-md border border-success/20 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('status') }}
        </div>
    @endif

    <form method="GET" action="{{ route('user.knowledge-bases.index') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <div class="inline-flex rounded-sm border border-neutral-200 bg-neutral-0 p-1">
            @foreach (['all' => __('All'), 'ready' => __('Ready'), 'indexing' => __('Learning'), 'error' => __('Needs attention')] as $value => $label)
                <button type="submit" name="status" value="{{ $value }}" class="range-btn {{ ($filters['status'] ?? 'all') === $value ? 'is-active' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="relative w-full min-w-0 sm:ml-auto sm:w-72">
            <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search knowledge bases...') }}" class="form-input input-search">
        </div>
        <button type="submit" class="btn-sm btn-outline">{{ __('Filter') }}</button>
    </form>

    @if ($knowledgeBases->isEmpty())
        <div class="mt-6 flex flex-col items-center justify-center rounded-md border border-dashed border-neutral-300 px-6 py-16 text-center">
            <span class="grid h-12 w-12 place-items-center rounded-md bg-primary/10 text-primary">
                <i class="ph ph-books text-2xl"></i>
            </span>
            <h3 class="heading-4 mt-4">{{ __('No knowledge bases yet') }}</h3>
            <p class="m-text mt-1 max-w-sm">{{ __('Create one, add what your bot should know, then pick it in your chatbot.') }}</p>
            <button type="button" class="btn btn-primary mt-5" data-modal-open="createKnowledgeBase">
                <i class="ph ph-plus text-base"></i>
                {{ __('Create knowledge base') }}
            </button>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($knowledgeBases as $knowledgeBase)
                <article class="app-card flex flex-col p-5">
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
                            <i class="ph ph-books text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-title text-lg font-bold text-title">{{ $knowledgeBase->name }}</h3>
                            <p class="flex items-center gap-1.5 text-xs font-semibold {{ match ($knowledgeBase->status) { 'ready' => 'text-success', 'error' => 'text-error', default => 'text-warning' } }}">
                                <span class="status-dot {{ match ($knowledgeBase->status) { 'ready' => 'bg-success', 'error' => 'bg-error', default => 'bg-warning' } }}"></span>
                                {{ ['ready' => __('Ready'), 'error' => __('Needs attention')][$knowledgeBase->status] ?? __('Learning') }}
                                @if (data_get($knowledgeBase->settings, 'managed')) · <span class="badge badge-success">{{ __('Default') }}</span> @endif
                            </p>
                        </div>
                    </div>
                    <p class="m-text mt-3 line-clamp-2">{{ $knowledgeBase->description ?: __('No description') }}</p>
                    <p class="mt-4 border-t border-neutral-100 pt-4 text-sm text-neutral-500">
                        {{ trans_choice(':count item|:count items', $knowledgeBase->sources_count) }} · {{ trans_choice('used by :count bot|used by :count bots', $knowledgeBase->chatbots_count) }}
                    </p>
                    <div class="mt-4 flex items-center gap-2">
                        <a href="{{ route('user.knowledge-bases.show', $knowledgeBase) }}" class="btn-sm btn-primary flex-1">{{ __('Manage') }}</a>
                        <button type="button" class="btn-sm btn-outline flex-1" data-modal-open="editKnowledgeBase{{ $knowledgeBase->id }}">{{ __('Edit') }}</button>
                        <form method="POST" action="{{ route('user.knowledge-bases.destroy', $knowledgeBase) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-action text-error" aria-label="{{ __('Delete knowledge base') }}">
                                <i class="ph ph-trash text-base"></i>
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $knowledgeBases->links() }}
        </div>
    @endif

    @push('modals')
        <div class="modal" id="createKnowledgeBase" data-modal>
            <div class="modal__backdrop" data-modal-close></div>
            <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="createKnowledgeBaseTitle">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 id="createKnowledgeBaseTitle" class="heading-4">{{ __('New Knowledge Base') }}</h3>
                        <p class="m-text mt-1">{{ __('Create it, then add text, questions and answers, a web page or a file.') }}</p>
                    </div>
                    <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                        <i class="ph ph-x text-base"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('user.knowledge-bases.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="kbCreateName" class="form-label">{{ __('Name') }} <span class="text-error">*</span></label>
                        <input id="kbCreateName" name="name" type="text" required value="{{ old('name') }}" class="form-input" placeholder="{{ __('e.g. Shipping and Returns') }}">
                        @error('name')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="kbCreateDescription" class="form-label">{{ __('Description') }}</label>
                        <textarea id="kbCreateDescription" name="description" rows="3" class="form-input" placeholder="{{ __('Short note about what this knowledge base contains') }}">{{ old('description') }}</textarea>
                        @error('description')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                    <input type="hidden" name="visibility" value="workspace">
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="btn btn-primary flex-1">{{ __('Create') }}</button>
                        <button type="button" class="btn btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @foreach ($knowledgeBases as $knowledgeBase)
            <div class="modal" id="editKnowledgeBase{{ $knowledgeBase->id }}" data-modal>
                <div class="modal__backdrop" data-modal-close></div>
                <div class="modal__panel" role="dialog" aria-modal="true" aria-labelledby="editKnowledgeBase{{ $knowledgeBase->id }}Title">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 id="editKnowledgeBase{{ $knowledgeBase->id }}Title" class="heading-4">{{ __('Edit Knowledge Base') }}</h3>
                            <p class="m-text mt-1">{{ __('Rename the container or update its internal note.') }}</p>
                        </div>
                        <button type="button" class="row-action" data-modal-close aria-label="{{ __('Close') }}">
                            <i class="ph ph-x text-base"></i>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('user.knowledge-bases.update', $knowledgeBase) }}" class="mt-5 space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="kbEditName{{ $knowledgeBase->id }}" class="form-label">{{ __('Name') }} <span class="text-error">*</span></label>
                            <input id="kbEditName{{ $knowledgeBase->id }}" name="name" type="text" required value="{{ old('name', $knowledgeBase->name) }}" class="form-input">
                        </div>
                        <div>
                            <label for="kbEditDescription{{ $knowledgeBase->id }}" class="form-label">{{ __('Description') }}</label>
                            <textarea id="kbEditDescription{{ $knowledgeBase->id }}" name="description" rows="3" class="form-input">{{ old('description', $knowledgeBase->description) }}</textarea>
                        </div>
                        <input type="hidden" name="visibility" value="workspace">
                        <div class="flex items-center gap-3 pt-1">
                            <button type="submit" class="btn btn-primary flex-1">{{ __('Save changes') }}</button>
                            <button type="button" class="btn btn-outline" data-modal-close>{{ __('Cancel') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endpush
</x-layouts.user>
