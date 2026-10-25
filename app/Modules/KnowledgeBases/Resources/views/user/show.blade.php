<x-layouts.user :title="$knowledgeBase->name">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('user.knowledge-bases.index') }}" class="row-action" aria-label="{{ __('Back to knowledge bases') }}">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="heading-2">{{ $knowledgeBase->name }}</h2>
                    <span class="badge badge-{{ $knowledgeBase->status === 'ready' ? 'success' : ($knowledgeBase->status === 'error' ? 'error' : 'warning') }}">{{ ['ready' => __('Ready'), 'error' => __('Needs attention')][$knowledgeBase->status] ?? __('Learning') }}</span>
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
        <div class="mt-4 rounded-md border border-success/20 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-md border border-error/20 bg-error/10 px-4 py-3 text-sm font-semibold text-error">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $vectorSearchEnabled)
        <div class="mt-4 rounded-md border border-neutral-100 bg-section px-4 py-3 text-sm text-neutral-500">
            {{ __('Answers use simple word matching. This works well for most stores.') }}
        </div>
    @endif

    @if ($knowledgeBase->status !== 'ready' && $knowledgeBase->status !== 'error')
        <div x-data x-init="setTimeout(() => window.location.reload(), 6000)" class="mt-4 rounded-md border border-warning/20 bg-warning/5 px-4 py-3 text-sm font-semibold text-warning">
            {{ __('Your bot is learning this. This page refreshes by itself.') }}
        </div>
    @endif

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
                <h3 class="heading-4">{{ __('What your bot knows') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($sources as $source)
                        <div class="rounded-md border border-neutral-100 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-title">{{ $source->title }}</p>
                                    <p class="text-xs text-neutral-400">
                                        {{ ['text' => __('Text'), 'url' => __('Web page'), 'file' => __('File')][$source->type] ?? __('Text') }}
                                        @if ($source->last_indexed_at) · {{ __('Learned :time', ['time' => $source->last_indexed_at->diffForHumans()]) }} @endif
                                        @if (data_get($source->metadata, 'managed')) · {{ __('Updated automatically from your store') }} @endif
                                    </p>
                                    @if ($source->error)
                                        <p class="mt-1 text-xs font-semibold text-error">{{ $source->error }}</p>
                                    @endif
                                </div>
                                <span class="badge badge-{{ $source->status === 'ready' ? 'success' : ($source->status === 'error' ? 'error' : 'warning') }}">{{ ['ready' => __('Ready'), 'error' => __('Needs attention'), 'pending' => __('Learning'), 'indexing' => __('Learning')][$source->status] ?? $source->status }}</span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('user.knowledge-bases.sources.reindex', $source) }}">
                                    @csrf
                                    <button type="submit" class="btn-sm btn-outline">{{ $source->status === 'error' ? __('Try again') : __('Learn again') }}</button>
                                </form>
                                <form method="POST" action="{{ route('user.knowledge-bases.sources.destroy', $source) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-sm btn-outline text-error">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="m-text">{{ __('Nothing here yet. Add some text, questions, a web page or a file.') }}</p>
                    @endforelse
                </div>
                <div class="mt-5">{{ $sources->links() }}</div>
            </section>
        </div>

        <aside class="lg:sticky lg:top-24 lg:self-start">
            <form method="POST" action="{{ route('user.knowledge-bases.sources.store', $knowledgeBase) }}" enctype="multipart/form-data" class="app-card p-5" x-data="{ type: '{{ old('type', 'text') }}', pairs: [{}] }">
                @csrf
                <input type="hidden" name="type" x-model="type">
                <h3 class="heading-4">{{ __('Add knowledge') }}</h3>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    @foreach (['text' => __('Write text'), 'faq' => __('Q & A'), 'url' => __('Web page'), 'file' => __('File')] as $value => $label)
                        <button type="button" x-on:click="type = '{{ $value }}'" x-bind:class="type === '{{ $value }}' ? 'btn-primary' : 'btn-outline'" class="btn-sm">{{ $label }}</button>
                    @endforeach
                </div>

                <div class="mt-4" x-show="type === 'text'">
                    <label for="content" class="form-label">{{ __('Write anything your bot should know') }}</label>
                    <textarea id="content" name="content" rows="6" class="form-input" x-bind:disabled="type !== 'text'">{{ old('content') }}</textarea>
                </div>

                <div class="mt-4 space-y-3" x-show="type === 'faq'">
                    <template x-for="(pair, index) in pairs" x-bind:key="index">
                        <div class="rounded-md border border-neutral-100 p-3">
                            <input type="text" x-bind:name="'faq[' + index + '][question]'" x-bind:disabled="type !== 'faq'" placeholder="{{ __('Question') }}" class="form-input">
                            <textarea x-bind:name="'faq[' + index + '][answer]'" x-bind:disabled="type !== 'faq'" rows="2" placeholder="{{ __('Answer') }}" class="form-input mt-2"></textarea>
                        </div>
                    </template>
                    <button type="button" class="btn-sm btn-outline" x-on:click="pairs.push({})">{{ __('Add another question') }}</button>
                </div>

                <div class="mt-4" x-show="type === 'url'">
                    <label for="url" class="form-label">{{ __('Web page address') }}</label>
                    <input id="url" name="url" type="url" value="{{ old('url') }}" placeholder="https://" class="form-input" x-bind:disabled="type !== 'url'">
                    <p class="form-hint">{{ __('Only this one page is read.') }}</p>
                </div>

                <div class="mt-4" x-show="type === 'file'">
                    <label for="file" class="form-label">{{ __('Choose a file') }}</label>
                    <input id="file" name="file" type="file" class="form-input" accept=".pdf,.docx,.txt,.md,.csv,.json,.html,.htm" x-bind:disabled="type !== 'file'">
                    <p class="form-hint">{{ __('PDF, Word, text or CSV, up to 20 MB.') }}</p>
                </div>

                <div class="mt-4">
                    <label for="title" class="form-label">{{ __('Name (optional)') }}</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" class="form-input">
                </div>

                <button type="submit" class="btn btn-primary mt-5 w-full">{{ __('Add') }}</button>
            </form>
        </aside>
    </div>
</x-layouts.user>
