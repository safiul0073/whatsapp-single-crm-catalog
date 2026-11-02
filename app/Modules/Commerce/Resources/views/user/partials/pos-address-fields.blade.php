@php
    $usStates = ['AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming'];
    $countryNames = collect(app(\App\Modules\Contacts\Services\ContactService::class)->countryOptions())->map(fn (array $option): string => $option['name']);
@endphp
<div class="pos-form-grid">
    <label class="pos-field"><span>{{ __('Recipient name') }}</span><input class="form-input" x-model="addressForm.name" maxlength="150" autocomplete="off"></label>
    <div class="pos-field"><label for="pos-address-phone">{{ __('Delivery phone') }}</label><x-ui.phone-input id="pos-address-phone" x-model="addressForm.phone" /></div>
    <div class="pos-field pos-field-wide relative" @click.outside="suggestions = []">
        <label for="pos-address-line1">{{ __('Address line 1') }}</label>
        <input id="pos-address-line1" class="form-input" x-model="addressForm.line1" maxlength="255" autocomplete="off" role="combobox" aria-autocomplete="list" :aria-expanded="suggestions.length > 0" placeholder="{{ __('Start typing a street address') }}"
            @input="queueSuggestions()"
            @keydown.arrow-down.prevent="moveSuggestion(1)"
            @keydown.arrow-up.prevent="moveSuggestion(-1)"
            @keydown.enter="suggestIndex >= 0 && ($event.preventDefault(), chooseSuggestion())"
            @keydown.escape="suggestions.length && ($event.preventDefault(), $event.stopPropagation(), suggestions = [])">
        <ul x-show="suggestions.length" x-cloak class="pos-dropdown" role="listbox">
            <template x-for="(suggestion, index) in suggestions" :key="suggestion.label">
                <li role="option" :aria-selected="index === suggestIndex">
                    <button type="button" class="pos-suggestion" :class="index === suggestIndex && 'pos-suggestion-active'" @click="chooseSuggestion(suggestion)" @mouseenter="suggestIndex = index">
                        <i class="ph ph-map-pin shrink-0 text-body"></i>
                        <span><template x-for="part in [highlight(suggestion.label)]" :key="suggestion.label"><span><span x-text="part.before"></span><strong class="text-title" x-text="part.match"></strong><span x-text="part.after"></span></span></template></span>
                    </button>
                </li>
            </template>
        </ul>
    </div>
    <label class="pos-field pos-field-wide"><span>{{ __('Address line 2') }}</span><input class="form-input" x-model="addressForm.line2" maxlength="255" autocomplete="off" placeholder="{{ __('Apartment, suite, unit (optional)') }}"></label>
    <label class="pos-field"><span>{{ __('City') }}</span><input class="form-input" x-model="addressForm.city" maxlength="120" autocomplete="off"></label>
    <label class="pos-field"><span x-text="addressForm.country === 'US' ? '{{ __('State') }}' : '{{ __('State / division') }}'"></span>
        <select x-show="addressForm.country === 'US'" class="form-input" x-model="addressForm.state"><option value="">{{ __('Select state') }}</option>@foreach($usStates as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select>
        <input x-show="addressForm.country !== 'US'" class="form-input" x-model="addressForm.state" maxlength="120" autocomplete="off">
    </label>
    <label class="pos-field"><span>{{ __('Country') }}</span>
        <select class="form-input" x-model="addressForm.country" @change="countryChanged()">@foreach($countryNames as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select>
    </label>
    <div class="pos-field">
        <label for="pos-address-zip">{{ __('ZIP / postal code') }}</label>
        <input id="pos-address-zip" class="form-input" x-model="addressForm.postal_code" maxlength="30" autocomplete="off" inputmode="numeric" placeholder="{{ __('Type the ZIP to find the area') }}" @input.debounce.400ms="lookupPostal()">
        <span x-show="postalStatus === 'loading'" x-cloak class="font-normal text-body">{{ __('Looking up…') }}</span>
        <span x-show="postalStatus === 'missing'" x-cloak class="font-normal text-warning">{{ __('ZIP not found, enter the city and state manually.') }}</span>
        <span x-show="postalStatus === 'found'" x-cloak class="font-normal text-primary">{{ __('Area found and filled in above.') }}</span>
    </div>
</div>
