<x-social-widgets::editor-layout :title="$widget->name">
    <div class="flex h-screen flex-col" x-data="socialWidgetEditor" data-config="{{ json_encode($editorConfig) }}">
        <header class="flex h-16 shrink-0 items-center gap-4 border-b border-neutral-200 px-5">
            <a href="{{ $backUrl }}" class="grid h-9 w-9 place-items-center rounded-full bg-primary text-white" aria-label="{{ __('Back to widgets') }}">
                <i class="ph ph-arrow-left text-base"></i>
            </a>
            <input type="text" class="min-w-0 flex-1 border-0 border-b border-transparent bg-transparent px-0 text-xl text-title placeholder:text-neutral-400 focus:border-primary focus:ring-0" maxlength="120" x-model.lazy="name" aria-label="{{ __('Widget name') }}">
            <span class="hidden font-mono text-[11px] uppercase tracking-[0.15em] text-body sm:inline" x-text="{ saved: '{{ __('Saved') }}', saving: '{{ __('Saving…') }}', pending: '{{ __('Unsaved') }}', error: '{{ __('Not saved') }}' }[saveState]"></span>
            <div class="flex items-center gap-1">
                <button type="button" class="grid h-10 w-10 place-items-center rounded-sm border transition" x-on:click="device = 'desktop'" x-bind:class="device === 'desktop' ? 'border-primary text-primary' : 'border-transparent text-body'" aria-label="{{ __('Desktop preview') }}">
                    <i class="ph ph-desktop text-lg"></i>
                </button>
                <button type="button" class="grid h-10 w-10 place-items-center rounded-sm border transition" x-on:click="device = 'mobile'" x-bind:class="device === 'mobile' ? 'border-primary text-primary' : 'border-transparent text-body'" aria-label="{{ __('Mobile preview') }}">
                    <i class="ph ph-device-mobile text-lg"></i>
                </button>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            <aside class="flex w-full max-w-sm shrink-0 flex-col border-r border-neutral-200 bg-white">
                <nav class="grid grid-cols-4 border-b border-neutral-200" aria-label="{{ __('Editor steps') }}">
                    @foreach ([__('Source'), __('Template'), __('Posts'), __('Style')] as $index => $label)
                        <button type="button" class="relative flex items-baseline gap-1 px-2 py-3 text-left transition" x-on:click="goToStep({{ $index }})" x-bind:class="step === {{ $index }} ? 'text-title' : 'text-neutral-400'">
                            <span class="font-mono text-sm font-semibold" x-bind:class="step === {{ $index }} && 'text-primary'">0{{ $index + 1 }}</span>
                            <i class="ph-bold ph-check text-[10px] text-title" x-show="isStepDone({{ $index }})" x-cloak></i>
                            <span class="truncate font-mono text-[10px] font-bold uppercase tracking-[0.12em]">{{ $label }}</span>
                            <span class="absolute inset-x-0 bottom-0 h-0.5 bg-primary" x-show="step === {{ $index }}"></span>
                        </button>
                    @endforeach
                </nav>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                    <div x-show="step === 0">@include('social-widgets::user.steps.source')</div>
                    <div x-show="step === 1" x-cloak>@include('social-widgets::user.steps.template')</div>
                    <div x-show="step === 2" x-cloak>@include('social-widgets::user.steps.posts')</div>
                    <div x-show="step === 3" x-cloak>@include('social-widgets::user.steps.style')</div>
                </div>

                <footer class="flex items-center justify-between gap-3 border-t border-neutral-200 px-5 py-4">
                    <button type="button" class="text-sm font-medium text-body hover:text-title" x-on:click="previousStep()" x-show="step > 0" x-cloak>{{ __('Previous') }}</button>
                    <span x-show="step === 0"></span>
                    <button type="button" class="btn rounded-sm bg-title px-6 text-white hover:bg-title/90" x-on:click="nextStep()" x-show="!isLastStep">
                        {{ __('Next Step') }} <i class="ph ph-arrow-right"></i>
                    </button>
                    <button type="button" class="btn btn-primary rounded-sm px-6" x-on:click="nextStep()" x-show="isLastStep" x-cloak>
                        <span x-text="isPublished ? '{{ __('Update & get code') }}' : '{{ __('Publish Widget') }}'"></span>
                    </button>
                </footer>
            </aside>

            <main class="min-w-0 flex-1 overflow-y-auto bg-section p-6 lg:p-10">
                <div class="mx-auto transition-all" x-bind:class="device === 'mobile' ? 'max-w-sm rounded-md border-8 border-title bg-white shadow-2xl' : 'max-w-6xl'">
                    <div x-ref="preview" data-social-feed-preview></div>
                </div>
            </main>
        </div>

        <div class="fixed inset-0 z-50 grid place-items-center bg-title/60 p-4" x-show="isEmbedOpen" x-cloak x-on:keydown.escape.window="isEmbedOpen = false">
            <div class="w-full max-w-lg rounded-md bg-white p-6 shadow-2xl" x-on:click.outside="isEmbedOpen = false">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-primary">{{ __('Published') }}</p>
                        <h2 class="mt-1 font-title text-2xl font-black text-title">{{ __('Add it to your site') }}</h2>
                    </div>
                    <button type="button" class="row-action" x-on:click="isEmbedOpen = false" aria-label="{{ __('Close') }}"><i class="ph ph-x text-lg"></i></button>
                </div>
                <p class="mt-3 text-sm text-body">{{ __('Paste this snippet where the feed should appear — a store CMS page, a theme section, or any HTML page.') }}</p>
                <pre class="mt-4 overflow-x-auto rounded-md bg-title p-4 font-mono text-xs leading-relaxed text-white"><code x-text="embedCode"></code></pre>
                <button type="button" class="btn btn-primary mt-4 w-full" x-on:click="copyEmbed()">
                    <i class="ph" x-bind:class="hasCopied ? 'ph-check' : 'ph-copy'"></i>
                    <span x-text="hasCopied ? '{{ __('Copied') }}' : '{{ __('Copy snippet') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</x-social-widgets::editor-layout>
