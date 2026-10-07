<x-layouts.user :title="__($providerLabel . ' widgets')">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('user.channels.index') }}" class="row-action" aria-label="{{ __('Back to channels') }}">
                <i class="ph ph-arrow-left text-lg"></i>
            </a>
            <div>
                <h2 class="heading-2">{{ __(':label widgets', ['label' => $providerLabel]) }}</h2>
                <p class="m-text mt-1">{{ __('Drop-in feeds for your store or any website. Pick a layout, style it, paste one snippet.') }}</p>
            </div>
        </div>
        @if ($widgets->isNotEmpty())
            <a href="{{ route('user.social-widgets.layouts', $provider) }}" class="btn-sm btn-primary">
                <i class="ph ph-plus text-base"></i>
                {{ __('New widget') }}
            </a>
        @endif
    </div>

    @if ($widgets->isEmpty())
        <section class="app-card relative mt-8 overflow-hidden px-6 py-16 text-center">
            <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.3em] text-body">
                <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary align-middle"></span>{{ __('Getting started') }}
            </p>
            <h3 class="mt-5 font-title text-4xl font-extrabold tracking-tight text-title sm:text-6xl">
                {{ __('Create your first') }} <span class="italic text-primary">{{ __('widget') }}</span>.
            </h3>
            <p class="m-text mx-auto mt-4 max-w-lg">{{ __('Show your :label on your website with a layout picked for your brand. Every detail is editable.', ['label' => $providerLabel]) }}</p>
            <a href="{{ route('user.social-widgets.layouts', $provider) }}" class="btn btn-primary mt-8 rounded-full px-7">
                <i class="ph ph-sparkle text-base"></i>
                {{ __('Create new widget') }}
                <i class="ph ph-arrow-right text-base"></i>
            </a>
            <p class="mt-4 font-mono text-[11px] uppercase tracking-[0.2em] text-body">{{ __('Takes ~60 seconds · No coding needed') }}</p>
        </section>
    @else
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($widgets as $widget)
                <article class="app-card flex flex-col p-5" data-social-widget="{{ $widget->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                            <i class="ph {{ $widget->layout->icon() }} text-2xl"></i>
                        </span>
                        <span class="badge {{ $widget->isPublished() ? 'badge-success' : 'badge-light' }}">{{ $widget->isPublished() ? __('Published') : __('Draft') }}</span>
                    </div>
                    <h3 class="heading-5 mt-4 truncate">{{ $widget->name }}</h3>
                    <p class="mt-1 text-sm text-body">{{ __(':layout layout', ['layout' => $widget->layout->label()]) }} · {{ $widget->updated_at->diffForHumans() }}</p>
                    <div class="mt-auto flex items-center justify-between gap-3 border-t border-neutral-100 pt-4">
                        <a href="{{ route('user.social-widgets.edit', $widget) }}" class="btn-sm btn-outline">
                            <i class="ph ph-pencil-simple text-base"></i>
                            {{ __('Edit') }}
                        </a>
                        <form method="POST" action="{{ route('user.social-widgets.destroy', $widget) }}">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="row-action text-danger" aria-label="{{ __('Delete widget') }}" data-confirm data-confirm-title="{{ __('Delete widget') }}" data-confirm-message="{{ __('Delete this widget? Sites using its snippet will stop showing the feed.') }}" data-confirm-button="{{ __('Delete') }}">
                                <i class="ph ph-trash text-lg"></i>
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.user>
