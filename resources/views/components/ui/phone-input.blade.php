@props(['id' => null, 'name' => null, 'default' => 'US', 'required' => false])

@php
    $countries = collect(app(\App\Modules\Contacts\Services\ContactService::class)->countryOptions())
        ->map(fn (array $option, string $iso): array => [$iso, $option['name'], $option['dial']])
        ->values();
@endphp
<div {{ $attributes->merge(['class' => 'phone-input']) }} x-data="phoneInput" x-modelable="e164" data-countries="{{ json_encode($countries) }}" data-default="{{ $default }}" @keydown.escape.stop="open && (open = false)">
    <button type="button" class="phone-input-code" @click="toggle()" :aria-expanded="open" aria-haspopup="listbox" aria-label="{{ __('Country code') }}">
        <span x-text="flag(current().iso)"></span>
        <span x-text="`+${current().dial}`"></span>
        <i class="ph ph-caret-down text-xs"></i>
    </button>
    <input type="tel" x-ref="number" @if($id) id="{{ $id }}" @endif x-model="national" class="form-input phone-input-number" inputmode="tel" autocomplete="off" @if($required) required @endif>
    @if($name)<input type="hidden" name="{{ $name }}" :value="e164">@endif
    <div x-show="open" x-cloak class="phone-input-menu" @click.outside="open = false">
        <input x-ref="search" type="search" class="form-input" x-model="query" @input="active = 0" placeholder="{{ __('Search country or code') }}" aria-label="{{ __('Search country or code') }}"
            @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="pick()">
        <ul role="listbox" class="phone-input-list">
            <template x-for="(country, index) in filtered()" :key="country.iso">
                <li role="option" :aria-selected="country.iso === iso">
                    <button type="button" class="phone-input-option" :class="index === active && 'phone-input-option-active'" @click="choose(country)" @mouseenter="active = index">
                        <span x-text="flag(country.iso)"></span><span class="min-w-0 flex-1 truncate" x-text="country.name"></span><span class="text-body" x-text="`+${country.dial}`"></span>
                    </button>
                </li>
            </template>
        </ul>
    </div>
</div>
