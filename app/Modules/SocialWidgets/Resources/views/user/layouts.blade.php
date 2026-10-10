<x-layouts.user :title="__('Choose a layout')">
    <div class="mx-auto max-w-5xl" x-data="socialWidgetLayoutPicker" x-on:keydown.window="focusSearchOnShortcut($event)">
        <div class="flex items-center justify-between gap-4 font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-body">
            <a href="{{ route('user.social-widgets.index', $provider) }}" class="inline-flex items-center gap-1.5 hover:text-title">
                <i class="ph ph-arrow-left"></i>{{ __('All widgets') }}
            </a>
            <nav class="hidden items-center gap-2 sm:flex" aria-label="{{ __('Breadcrumb') }}">
                <a href="{{ route('user.channels.index') }}" class="hover:text-title">{{ __('Widgets') }}</a>
                <span class="text-neutral-300">/</span>
                <a href="{{ route('user.social-widgets.index', $provider) }}" class="hover:text-title">{{ __($providerLabel) }}</a>
                <span class="text-neutral-300">/</span>
                <span class="text-title">{{ __('Layouts') }}</span>
            </nav>
        </div>

        <div class="mt-8 h-0.5 w-9 bg-primary"></div>
        <p class="mt-6 inline-flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-[0.2em] text-title">
            <i class="ph ph-sparkle text-base"></i>{{ __(':label widget', ['label' => $providerLabel]) }}
        </p>
        <h2 class="mt-2 font-title text-4xl font-black tracking-tight text-title sm:text-5xl">{{ __('Choose a layout.') }}</h2>
        <p class="mt-3 max-w-xl text-body">
            {{ __(':count hand-crafted layouts —', ['count' => $layoutCount]) }}
            <strong class="font-semibold text-title">{{ __('one picked for your brand') }}</strong>.
            {{ __('Every layout is fully customizable once you open the editor.') }}
        </p>

        <p class="mt-10 font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-title">
            <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary align-middle"></span>{{ __('Recommended for :label', ['label' => $providerLabel]) }}
        </p>
        <form method="POST" action="{{ route('user.social-widgets.store', $provider) }}" class="mt-3">
            @csrf
            <input type="hidden" name="layout" value="{{ $recommended->value }}">
            <button type="submit" class="group grid w-full gap-6 rounded-sm border border-title bg-linear-to-r from-section via-white to-primary/10 p-7 text-left shadow-lg transition hover:shadow-xl sm:grid-cols-[1fr_auto]" data-layout-featured="{{ $recommended->value }}">
                <div>
                    <p class="flex items-center gap-2 font-mono text-[10px] font-bold uppercase tracking-[0.2em]">
                        <span class="rounded-sm bg-primary px-2 py-0.5 text-white"><i class="ph ph-sparkle"></i> {{ __('Featured') }}</span>
                        <span class="text-body">{{ __('Picked for you') }}</span>
                    </p>
                    <h3 class="mt-4 font-title text-3xl font-black tracking-tight text-title">{{ __($recommended->label()) }}</h3>
                    <p class="mt-2 text-sm text-body">{{ __($recommended->description()) }}</p>
                    <span class="mt-7 inline-flex items-center gap-3 text-sm font-semibold text-primary">
                        {{ __('Start with this layout') }}
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-primary text-white transition group-hover:rotate-45"><i class="ph ph-arrow-up-right"></i></span>
                    </span>
                </div>
                <dl class="grid content-start gap-4 border-neutral-200 sm:border-l sm:pl-7">
                    <div>
                        <dt class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-body">{{ __('Pick') }}</dt>
                        <dd class="font-title text-3xl font-black text-title">{{ $recommended->number() }}<span class="text-neutral-200">/{{ $layoutCount }}</span></dd>
                    </div>
                    <div>
                        <dt class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-body">{{ __('Tailored for') }}</dt>
                        <dd class="text-sm font-semibold text-title">{{ __($providerLabel) }}</dd>
                    </div>
                    <div>
                        <dt class="font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-body">{{ __('Editable') }}</dt>
                        <dd class="text-sm font-semibold text-title">{{ __('Every detail') }}</dd>
                    </div>
                </dl>
            </button>
        </form>

        <div class="mt-12 flex flex-wrap items-center justify-between gap-4">
            <p class="font-mono text-xs font-bold uppercase tracking-[0.2em] text-title">
                {{ __('All layouts') }}
                <span class="ml-2 normal-case tracking-normal text-body" x-text="visibleCountLabel">{{ trans_choice(':count layout|:count layouts', count($otherLayouts)) }}</span>
            </p>
            <label class="relative flex w-full items-center sm:w-64">
                <i class="ph ph-magnifying-glass pointer-events-none absolute left-3 text-body"></i>
                <input type="search" x-ref="search" x-model="query" class="form-input w-full pl-9 pr-12" placeholder="{{ __('Search layouts...') }}" aria-label="{{ __('Search layouts') }}">
                <kbd class="pointer-events-none absolute right-2 rounded border border-neutral-200 bg-section px-1.5 font-mono text-[10px] text-body">⌘K</kbd>
            </label>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($otherLayouts as $layout)
                <form method="POST" action="{{ route('user.social-widgets.store', $provider) }}" data-layout-card data-search="{{ strtolower($layout->label().' '.$layout->description()) }}" x-show="matches($el)">
                    @csrf
                    <input type="hidden" name="layout" value="{{ $layout->value }}">
                    <button type="submit" class="group flex h-44 w-full flex-col rounded-sm border border-neutral-200 bg-white p-5 text-left transition hover:-translate-y-0.5 hover:border-title hover:shadow-md">
                        <span class="flex w-full items-start justify-between">
                            <span class="font-mono text-[11px] font-semibold text-neutral-300">{{ $layout->number() }}</span>
                            <span class="grid h-7 w-7 place-items-center rounded-full border border-neutral-200 text-body transition group-hover:border-primary group-hover:bg-primary group-hover:text-white"><i class="ph ph-arrow-up-right text-xs"></i></span>
                        </span>
                        <span class="mt-auto font-title text-2xl font-black tracking-tight text-title">{{ __($layout->label()) }}</span>
                        <span class="mt-1 text-sm text-body">{{ __($layout->description()) }}</span>
                    </button>
                </form>
            @endforeach
        </div>
        <p class="mt-8 text-center text-sm text-body" x-show="visibleCount === 0" x-cloak>{{ __('No layouts match your search.') }}</p>
    </div>
</x-layouts.user>
