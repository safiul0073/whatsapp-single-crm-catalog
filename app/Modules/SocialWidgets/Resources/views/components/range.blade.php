@props(['model', 'label', 'min' => 0, 'max' => 100])

<div>
    <div class="flex items-center justify-between">
        <span class="text-xs font-medium text-title">{{ $label }}</span>
        <span class="rounded-md border border-neutral-200 bg-white px-2 py-0.5 font-mono text-[11px] text-title" x-text="{{ $model }} + 'px'"></span>
    </div>
    <input type="range" min="{{ $min }}" max="{{ $max }}" class="mt-2 w-full accent-title" x-model.number="{{ $model }}">
</div>
