@props(['model', 'label', 'hint' => null])

<label class="flex cursor-pointer items-start justify-between gap-3">
    <span>
        <span class="block text-sm font-medium text-title">{{ $label }}</span>
        @if ($hint)
            <span class="mt-0.5 block text-xs text-body">{{ $hint }}</span>
        @endif
    </span>
    <input type="checkbox" class="peer sr-only" x-model="{{ $model }}">
    <span class="relative mt-0.5 h-5 w-9 shrink-0 rounded-full bg-neutral-300 transition after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:bg-title peer-checked:after:translate-x-4"></span>
</label>
