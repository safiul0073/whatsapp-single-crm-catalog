<x-layouts.user :title="$knowledgeBase->name">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('user.knowledge-bases.index') }}" class="row-action" aria-label="{{ __('Back to knowledge bases') }}">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="heading-2">{{ $knowledgeBase->name }}</h2>
                    <span class="badge badge-{{ $knowledgeBase->status === 'ready' ? 'success' : ($knowledgeBase->status === 'error' ? 'error' : 'warning') }}">{{ ucfirst($knowledgeBase->status) }}</span>
                </div>
                <p class="m-text mt-1">{{ $knowledgeBase->description ?: __('No description') }}</p>
            </div>
        </div>
        <a href="{{ route('user.knowledge-bases.index') }}" class="btn-sm btn-outline">
            <i class="ph ph-books text-base"></i>
            {{ __('All knowledge bases') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-error/20 bg-error/10 px-4 py-3 text-sm font-semibold text-error">
            {{ __('Please fix the highlighted source fields.') }}
        </div>
    @endif

    <div class="mt-4 rounded-xl border {{ $vectorSearchEnabled ? 'border-success/20 bg-success/5 text-success' : 'border-warning/20 bg-warning/5 text-warning' }} px-4 py-3 text-sm font-semibold">
        {{ $vectorSearchEnabled ? __('Qdrant vector search is active. Indexed chunks will sync to the vector database.') : __('Database fallback is active. Configure Qdrant in Admin AI Settings for semantic vector search.') }}
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">{{ __('Knowledge Base Details') }}</h3>
                <form method="POST" action="{{ route('user.knowledge-bases.update', $knowledgeBase) }}" class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] lg:items-end">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="kbName" class="form-label">{{ __('Name') }}</label>
                        <input id="kbName" name="name" type="text" required value="{{ old('name', $knowledgeBase->name) }}" class="form-input">
                    </div>
                    <div>
                        <label for="kbDescription" class="form-label">{{ __('Description') }}</label>
                        <input id="kbDescription" name="description" type="text" value="{{ old('description', $knowledgeBase->description) }}" class="form-input">
                    </div>
                    <input type="hidden" name="visibility" value="workspace">
                    <button type="submit" class="btn btn-outline">{{ __('Save') }}</button>
                </form>
            </section>

            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">{{ __('Sources') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($sources as $source)
                        <div class="rounded-xl border border-neutral-100 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-title">{{ $source->title }}</p>
                                    <p class="text-xs text-neutral-400">{{ strtoupper($source->type) }} · {{ $source->chunks_count }} {{ __('chunks') }} · {{ $source->token_count }} {{ __('tokens') }}</p>
                                    <p class="mt-1 text-xs text-neutral-400">{{ __('Vector') }}: {{ ucfirst(str_replace('_', ' ', $source->vector_status ?? 'pending')) }} @if ($source->last_indexed_at) · {{ $source->last_indexed_at->diffForHumans() }} @endif</p>
                                    @if ($source->error)
                                        <p class="mt-1 text-xs font-semibold text-error">{{ $source->error }}</p>
                                    @endif
                                    @if ($source->vector_error)
                                        <p class="mt-1 text-xs font-semibold text-warning">{{ $source->vector_error }}</p>
                                    @endif
                                </div>
                                <span class="badge badge-{{ $source->status === 'ready' ? 'success' : ($source->status === 'error' ? 'error' : 'warning') }}">{{ ucfirst($source->status) }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('user.knowledge-bases.sources.reindex', $source) }}">
                                    @csrf
                                    <button type="submit" class="btn-sm btn-outline">{{ __('Re-index') }}</button>
                                </form>
                                <form method="POST" action="{{ route('user.knowledge-bases.sources.destroy', $source) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-sm btn-outline text-error">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="m-text">{{ __('No sources yet. Add one to create searchable chunks.') }}</p>
                    @endforelse
                </div>
                <div class="mt-5">{{ $sources->links() }}</div>
            </section>

            <section class="app-card p-5 sm:p-6">
                <h3 class="heading-4">{{ __('Recent Chunks') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($chunks as $chunk)
                        <div class="rounded-xl bg-section p-4">
                            <p class="text-xs font-semibold text-neutral-400">{{ $chunk->source?->title }}</p>
                            <p class="m-text mt-1 line-clamp-3">{{ $chunk->content }}</p>
                        </div>
                    @empty
                        <p class="m-text">{{ __('Chunks will appear after sources are indexed.') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <form method="POST" action="{{ route('user.knowledge-bases.sources.store', $knowledgeBase) }}" enctype="multipart/form-data" class="app-card p-5">
                @csrf
                <h3 class="heading-4">{{ __('Add Source') }}</h3>

                <div class="mt-4">
                    <label for="type" class="form-label">{{ __('Source type') }}</label>
                    <select id="type" name="type" class="form-input">
                        @foreach (['text' => __('Text'), 'qa' => __('Q&A'), 'url' => __('Website URL'), 'sitemap' => __('Sitemap'), 'file' => __('File')] as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'text') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4">
                    <label for="title" class="form-label">{{ __('Title') }}</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required class="form-input">
                    @error('title')<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4">
                    <label for="url" class="form-label">{{ __('URL') }}</label>
                    <input id="url" name="url" type="url" value="{{ old('url') }}" class="form-input">
                    @error('url')<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4">
                    <label for="crawl_limit" class="form-label">{{ __('Sitemap crawl limit') }}</label>
                    <input id="crawl_limit" name="crawl_limit" type="number" min="1" max="50" value="{{ old('crawl_limit', 10) }}" class="form-input">
                    @error('crawl_limit')<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4">
                    <label for="content" class="form-label">{{ __('Text content') }}</label>
                    <textarea id="content" name="content" rows="5" class="form-input">{{ old('content') }}</textarea>
                    @error('content')<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>

                <div class="mt-4 grid gap-4">
                    <div>
                        <label for="question" class="form-label">{{ __('Question') }}</label>
                        <input id="question" name="question" type="text" value="{{ old('question') }}" class="form-input">
                        @error('question')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="answer" class="form-label">{{ __('Answer') }}</label>
                        <textarea id="answer" name="answer" rows="4" class="form-input">{{ old('answer') }}</textarea>
                        @error('answer')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label for="file" class="form-label">{{ __('File') }}</label>
                    <input id="file" name="file" type="file" class="form-input" accept=".pdf,.docx,.txt,.md,.csv,.json,.html,.htm">
                    @error('file')<p class="form-hint text-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="btn btn-primary mt-5 w-full">{{ __('Add & Index') }}</button>
            </form>
        </aside>
    </div>
</x-layouts.user>
