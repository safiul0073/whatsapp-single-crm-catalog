<x-layouts.user :title="__('Website Widgets')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('user.chatbots.index') }}" class="row-action" aria-label="{{ __('Back to chatbots') }}">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <h2 class="heading-2">{{ __('Website Widgets') }}</h2>
                <p class="m-text mt-1">{{ __('Embeddable chat widgets connected to your workspace chatbots and Inbox.') }}</p>
            </div>
        </div>
        <a href="{{ route('user.chatbots.widgets.create') }}" class="btn-sm btn-primary">
            <i class="ph ph-plus text-base"></i>
            {{ __('New widget') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-success/20 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($widgets->isEmpty())
        <div class="app-card mt-6 p-8 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-primary/10 text-primary">
                <i class="ph ph-browser text-xl"></i>
            </span>
            <h3 class="heading-4 mt-4">{{ __('No website widgets yet') }}</h3>
            <p class="m-text mx-auto mt-1 max-w-md">{{ __('Create a widget, attach a chatbot, and paste one script tag into your website.') }}</p>
            <a href="{{ route('user.chatbots.widgets.create') }}" class="btn btn-primary mt-5">{{ __('Create widget') }}</a>
        </div>
    @else
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            @foreach ($widgets as $widget)
                @php($embedCode = '<script src="'.route('widgets.chatbot.loader', $widget->public_token).'" async></script>')
                <div class="app-card p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="truncate font-title text-lg font-bold text-title">{{ $widget->name }}</h3>
                            <p class="m-text mt-1">{{ $widget->chatbot?->name ?? __('Missing chatbot') }}</p>
                        </div>
                        <span class="badge {{ $widget->is_active ? 'badge-success' : 'badge-warning' }}">
                            {{ $widget->is_active ? __('Active') : __('Paused') }}
                        </span>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-neutral-400">{{ __('Sessions') }}</dt>
                            <dd class="font-semibold text-title">{{ $widget->sessions_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('Domains') }}</dt>
                            <dd class="font-semibold text-title">{{ count($widget->allowed_domains ?? []) }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('Position') }}</dt>
                            <dd class="font-semibold text-title">{{ ucfirst($widget->setting('position', 'right')) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 rounded-lg border border-neutral-100 bg-section p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ __('Embed code') }}</p>
                            <button type="button" class="btn-sm btn-outline shrink-0" data-copy="{{ $embedCode }}" aria-label="{{ __('Copy embed code for :name', ['name' => $widget->name]) }}">
                                <i class="ph ph-copy text-base"></i>
                                <span data-copy-label>{{ __('Copy') }}</span>
                            </button>
                        </div>
                        <code class="mt-2 block overflow-x-auto text-xs text-title">{{ $embedCode }}</code>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('user.chatbots.widgets.edit', $widget) }}" class="btn-sm btn-primary flex-1">{{ __('Configure') }}</a>
                        <form method="POST" action="{{ route('user.chatbots.widgets.destroy', $widget) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-action text-error" aria-label="{{ __('Delete widget') }}">
                                <i class="ph ph-trash text-base"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $widgets->links() }}
        </div>
    @endif
</x-layouts.user>
