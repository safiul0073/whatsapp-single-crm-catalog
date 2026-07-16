@props(['name', 'label', 'value' => null, 'placeholder' => null])

@php $value = $value ?? request($name) @endphp

<div>
    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-neutral-500">{{ __($label) }}</label>
    <input type="text" name="{{ $name }}" value="{{ $value }}" placeholder="{{ $placeholder ? __($placeholder) : null }}" class="input-field min-h-9 w-full px-2.5 py-1.5 text-xs" />
</div>
