@props([
    'group' => '',
    'deleteAction' => '',
    'toggleAction' => '',
    'exportAction' => '',
])

<div
    x-data="bulkActions({
        group: '{{ $group }}',
        deleteAction: '{{ $deleteAction }}',
        toggleAction: '{{ $toggleAction }}',
        exportAction: '{{ $exportAction }}'
    })"
    x-show="count > 0"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-2"
    class="flex flex-wrap items-center gap-3 border-b border-neutral-100 px-5 py-3"
>
    <span class="text-sm font-medium text-neutral-700">
        <span x-text="count"></span> {{ __('selected') }}
    </span>

    <div class="flex items-center gap-2">
        @if($toggleAction)
        <button
            type="button"
            class="btn btn-outline btn-sm"
            x-on:click="bulkToggleStatus()"
            x-bind:disabled="processing"
        >
            <i class="ph ph-arrows-clockwise"></i>
            {{ __('Toggle Status') }}
        </button>
        @endif

        @if($deleteAction)
        <button
            type="button"
            class="btn btn-danger btn-sm"
            x-on:click="bulkDelete()"
            x-bind:disabled="processing"
        >
            <i class="ph ph-trash"></i>
            {{ __('Delete Selected') }}
        </button>
        @endif

        @if($exportAction)
        <button
            type="button"
            class="btn btn-outline btn-sm"
            x-on:click="exportSelected()"
            x-bind:disabled="processing"
        >
            <i class="ph ph-file-csv"></i>
            {{ __('Export Selected') }}
        </button>
        @endif

        {{ $slot }}
    </div>

    <div x-show="processing" class="ml-auto">
        <i class="ph ph-spinner-gap animate-spin text-neutral-400"></i>
    </div>
</div>
