@props(['model', 'label'])

<label class="flex cursor-pointer items-center gap-2 text-sm text-title">
    <input type="checkbox" class="h-4 w-4 rounded border-neutral-300 accent-title" x-model="{{ $model }}">
    {{ $label }}
</label>
