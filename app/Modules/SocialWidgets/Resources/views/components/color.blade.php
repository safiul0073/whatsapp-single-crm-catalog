@props(['model', 'label'])

<label class="flex items-center justify-between gap-3">
    <span class="text-xs font-medium text-title">{{ $label }}</span>
    <span class="flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white p-1">
        <input type="color" class="h-6 w-7 cursor-pointer rounded border-0 bg-transparent p-0" x-bind:value="{{ $model }}.slice(0, 7)" x-on:input="{{ $model }} = $event.target.value">
        <input type="text" class="w-20 border-0 bg-transparent p-0 font-mono text-[11px] uppercase text-title focus:ring-0" maxlength="9" x-model.lazy="{{ $model }}" aria-label="{{ $label }}">
    </span>
</label>
