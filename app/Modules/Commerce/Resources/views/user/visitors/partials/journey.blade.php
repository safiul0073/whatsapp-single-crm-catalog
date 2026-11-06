<div x-show="timeline !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4" x-on:keydown.escape.window="timeline = null">
    <div class="card max-h-[80vh] w-full max-w-lg overflow-y-auto p-5" x-on:click.outside="timeline = null" role="dialog" aria-modal="true">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-title" x-text="timelineVisitor"></h3>
            <button type="button" class="text-neutral-400 hover:text-neutral-700" x-on:click="timeline = null" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button>
        </div>
        <ol class="space-y-2 text-sm">
            <template x-for="(view, i) in timeline || []" :key="i">
                <li class="flex justify-between gap-3 border-b border-border-soft pb-2">
                    <span class="min-w-0">
                        <span class="block truncate" x-text="view.path"></span>
                        <span class="block text-xs text-body" x-show="view.city" x-text="view.city"></span>
                    </span>
                    <span class="shrink-0 text-xs text-body" x-text="view.at"></span>
                </li>
            </template>
            <li x-show="timeline && timeline.length === 0" class="py-4 text-center text-xs text-body">{{ __('Loading…') }}</li>
        </ol>
    </div>
</div>
