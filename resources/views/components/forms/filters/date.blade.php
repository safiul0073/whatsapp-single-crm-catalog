@props(['name', 'label', 'value' => null])

@php $value = $value ?? request($name) @endphp

<div>
    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-neutral-500">{{ __($label) }}</label>
    <input type="date" name="{{ $name }}" value="{{ $value }}" class="input-field min-h-9 w-full px-2.5 py-1.5 text-xs" />
</div>
