@props(['model', 'options'])

<div @class(["grid rounded-lg bg-neutral-100 p-1",'grid-cols-2' => count($options) === 2, 'grid-cols-3' => count($options) === 3])>
    @foreach ($options as $value => $label)
        <button type="button" class="rounded-md py-1.5 text-xs font-semibold transition" x-on:click="{{ $model }} = '{{ $value }}'" x-bind:class="{{ $model }} === '{{ $value }}' ? 'bg-white text-title shadow-sm' : 'text-body'">{{ $label }}</button>
    @endforeach
</div>
