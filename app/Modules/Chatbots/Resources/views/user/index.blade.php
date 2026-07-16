<x-layouts.user :title="__('AI Chatbots')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('AI Chatbots') }}</h2>
            <p class="m-text mt-1">{{ __('Chatbots that answer customers with platform AI and your knowledge bases.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('user.knowledge-bases.index') }}" class="btn-sm btn-outline">
                <i class="ph ph-books text-base"></i>
                {{ __('Knowledge bases') }}
            </a>
            <a href="{{ route('user.chatbots.widgets.index') }}" class="btn-sm btn-outline">
                <i class="ph ph-browser text-base"></i>
                {{ __('Website widgets') }}
            </a>
            <a href="{{ route('user.chatbots.create') }}" class="btn-sm btn-primary">
                <i class="ph ph-plus text-base"></i>
                {{ __('New Chatbot') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Total bots') }}</p>
                <span class="stat-card__icon"><i class="ph ph-robot text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $stats['total'] }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('in this workspace') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Active') }}</p>
                <span class="stat-card__icon"><i class="ph ph-check-circle text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $stats['active'] }}</p>
            <p class="mt-1 text-xs text-success">{{ __('ready to respond') }}</p>
        </div>
        <div class="stat-card">
            <div class="f-between">
                <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Paused') }}</p>
                <span class="stat-card__icon"><i class="ph ph-pause-circle text-lg"></i></span>
            </div>
            <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $stats['paused'] }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('disabled bots') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('user.chatbots.index') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
        <div class="inline-flex rounded-full border border-neutral-200 bg-neutral-0 p-1">
            @foreach (['all' => __('All'), 'active' => __('Active'), 'paused' => __('Paused')] as $value => $label)
                <button type="submit" name="status" value="{{ $value }}" class="range-btn {{ ($filters['status'] ?? 'all') === $value ? 'is-active' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="relative w-full min-w-0 sm:ml-auto sm:w-72">
            <i class="ph ph-magnifying-glass pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-base text-neutral-400"></i>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search chatbots...') }}" class="form-input input-search">
        </div>
        <button type="submit" class="btn-sm btn-outline">{{ __('Filter') }}</button>
    </form>

    @if ($chatbots->isEmpty())
        <div class="mt-6 flex flex-col items-center justify-center rounded-xl border border-dashed border-neutral-300 px-6 py-16 text-center">
            <span class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary">
                <i class="ph ph-robot text-2xl"></i>
            </span>
            <h3 class="heading-4 mt-4">{{ __('No chatbots yet') }}</h3>
            <p class="m-text mt-1 max-w-sm">{{ __('Create a chatbot and attach knowledge bases to start testing responses.') }}</p>
            <a href="{{ route('user.chatbots.create') }}" class="btn btn-primary mt-5">
                <i class="ph ph-plus text-base"></i>
                {{ __('Create chatbot') }}
            </a>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($chatbots as $chatbot)
                <article class="app-card flex flex-col p-5">
                    <div class="flex items-start gap-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                            <i class="ph ph-robot text-2xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-title text-lg font-bold text-title">{{ $chatbot->name }}</h3>
                            <p class="flex items-center gap-1.5 text-xs font-semibold {{ $chatbot->is_active ? 'text-success' : 'text-warning' }}">
                                <span class="status-dot {{ $chatbot->is_active ? 'bg-success' : 'bg-warning' }}"></span>
                                {{ $chatbot->is_active ? __('Active') : __('Paused') }}
                            </p>
                        </div>
                    </div>
                    <p class="m-text mt-3 line-clamp-2">{{ $chatbot->persona }}</p>
                    <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-neutral-100 pt-4 text-sm">
                        <div>
                            <dt class="text-xs text-neutral-400">{{ __('Provider') }}</dt>
                            <dd class="truncate font-semibold text-title">{{ __('Platform AI') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-neutral-400">{{ __('Model') }}</dt>
                            <dd class="truncate font-semibold text-title">{{ ai_setting('ai_default_text_model') ?: __('Admin default') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-neutral-400">{{ __('KB') }}</dt>
                            <dd class="font-semibold text-title">{{ $chatbot->knowledge_bases_count }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex items-center gap-2">
                        <a href="{{ route('user.chatbots.config', $chatbot) }}" class="btn-sm btn-primary flex-1">{{ __('Configure') }}</a>
                        <form method="POST" action="{{ route('user.chatbots.toggle', $chatbot) }}" class="flex-1">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn-sm btn-outline w-full">{{ $chatbot->is_active ? __('Pause') : __('Activate') }}</button>
                        </form>
                        <form method="POST" action="{{ route('user.chatbots.destroy', $chatbot) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-action text-error" aria-label="{{ __('Delete chatbot') }}">
                                <i class="ph ph-trash text-base"></i>
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $chatbots->links() }}
        </div>
    @endif
</x-layouts.user>
