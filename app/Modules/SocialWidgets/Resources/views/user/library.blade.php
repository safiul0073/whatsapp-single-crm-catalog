<x-layouts.user :title="__('Widget library')">
    <div class="mx-auto max-w-5xl" x-data="socialWidgetLibrary" x-on:keydown.window="focusSearchOnShortcut($event)">
        <div class="flex items-center justify-between border-b border-neutral-200 pb-3 text-sm">
            <a href="{{ route('user.channels.index') }}" class="inline-flex items-center gap-1.5 text-body hover:text-title">
                <i class="ph ph-arrow-left"></i>{{ __('Back to channels') }}
            </a>
            <span class="text-xs text-body">{{ trans_choice(':count widget|:count widgets', $widgetCount) }}</span>
        </div>

        <h2 class="heading-2 mt-6">{{ __('Widget library') }}</h2>
        <p class="m-text mt-1">{{ trans_choice(':count drop-in widget — pick one and paste its snippet anywhere.|:count drop-in widgets — pick one and paste its snippet anywhere.', $widgetCount) }}</p>

        <label class="relative mt-5 flex max-w-md items-center">
            <i class="ph ph-magnifying-glass pointer-events-none absolute left-3 text-body"></i>
            <input type="search" x-ref="search" x-model="query" class="form-input w-full pl-9 pr-12" placeholder="{{ __('Search widgets — try “instagram”') }}" aria-label="{{ __('Search widgets') }}">
            <kbd class="pointer-events-none absolute right-2 rounded border border-neutral-200 bg-section px-1.5 font-mono text-[10px] text-body">⌘K</kbd>
        </label>

        <section class="mt-7" x-show="query === ''">
            <p class="text-sm font-semibold text-title"><span class="text-primary">★</span> {{ __('Recommended for you') }} <span class="font-normal text-body">· {{ __('what most sites pick first') }}</span></p>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($recommended as $widget)
                    <a href="{{ $widget['url'] }}" class="group rounded-md border border-neutral-200 bg-white p-4 transition hover:border-primary hover:shadow-sm" data-widget-card="{{ $widget['key'] }}" x-on:mouseenter="showPreview($event, '{{ $widget['key'] }}')" x-on:mousemove="movePreview($event)" x-on:mouseleave="hidePreview()">
                        <span class="grid h-9 w-9 place-items-center rounded-md bg-pink-50 text-pink-600"><i class="ph {{ $widget['icon'] }} text-xl"></i></span>
                        <span class="mt-4 block font-semibold text-title">{{ __($widget['name']) }}</span>
                        <span class="mt-2 inline-flex items-center gap-1 text-sm text-title group-hover:text-primary">{{ __('Install') }} <i class="ph ph-arrow-right text-xs"></i></span>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="mt-7 flex flex-wrap gap-2 border-b border-neutral-200 pb-4" x-show="query === ''">
            @foreach ($sections as $sectionKey => $section)
                <a href="#widgets-{{ $sectionKey }}" class="rounded-md border border-neutral-200 bg-white px-2.5 py-1 text-xs font-medium text-title hover:border-neutral-400">
                    {{ __($section['label']) }} <span class="ml-0.5 text-body">{{ count($section['widgets']) }}</span>
                </a>
            @endforeach
        </div>

        @foreach ($sections as $sectionKey => $section)
            <section id="widgets-{{ $sectionKey }}" class="mt-6 scroll-mt-20" x-show="sectionHasMatches($el)">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-semibold text-title">{{ __($section['label']) }} <span class="font-normal text-body">· {{ __($section['blurb']) }}</span></p>
                    <span class="text-xs text-body">{{ count($section['widgets']) }}</span>
                </div>
                <div class="mt-3 grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($section['widgets'] as $widget)
                        <a href="{{ $widget['url'] }}" class="flex items-center gap-3 rounded-md border border-neutral-200 bg-white px-3.5 py-3 text-sm font-medium text-title transition hover:border-primary" data-widget-item data-search="{{ strtolower($widget['name'].' '.$widget['keywords']) }}" x-show="matches($el)" x-on:mouseenter="showPreview($event, '{{ $widget['key'] }}')" x-on:mousemove="movePreview($event)" x-on:mouseleave="hidePreview()">
                            <i class="ph {{ $widget['icon'] }} text-lg text-body"></i>
                            {{ __($widget['name']) }}
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        <p class="mt-10 text-center text-sm text-body" x-show="visibleCount === 0" x-cloak>{{ __('No widgets match your search.') }}</p>

        <div x-ref="preview" class="pointer-events-none fixed left-0 top-0 z-50 w-80 opacity-0 transition-opacity duration-150" x-bind:class="previewKey && 'opacity-100'" aria-hidden="true">
            <div class="overflow-hidden rounded-md border border-neutral-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-neutral-100 bg-white px-3 py-2">
                    <span class="h-1.5 w-14 rounded-full bg-neutral-200"></span>
                    <span class="flex gap-1.5"><span class="h-1 w-5 rounded-full bg-neutral-200"></span><span class="h-1 w-5 rounded-full bg-neutral-200"></span><span class="h-1 w-5 rounded-full bg-neutral-200"></span></span>
                </div>
                <div class="bg-section p-3">
                    <div class="grid grid-cols-[1fr_auto] gap-3">
                        <div class="space-y-1.5 pt-1">
                            <span class="block h-2.5 w-4/5 rounded bg-slate-300"></span>
                            <span class="block h-1.5 w-3/5 rounded bg-slate-200"></span>
                            <span class="block h-1.5 w-1/2 rounded bg-slate-200"></span>
                            <span class="mt-2 block h-3.5 w-14 rounded bg-slate-400"></span>
                        </div>
                        <span class="h-16 w-24 rounded-md bg-linear-to-br from-slate-200 to-slate-300"></span>
                    </div>
                    <div class="mt-3 rounded-md border border-neutral-200 bg-white p-2">
                        @foreach ($sections as $section)
                            @foreach ($section['widgets'] as $widget)
                                <div x-show="previewKey === '{{ $widget['key'] }}'">
                                    @include('social-widgets::user.previews.'.$widget['preview'])
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
