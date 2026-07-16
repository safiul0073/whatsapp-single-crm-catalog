@props([
    'field',
    'name',
    'value' => null,
    'errorKey' => null,
    'showLabel' => true,
    'showHint' => true,
])

@php
    $normalizeText = function ($text) use (&$normalizeText) {
        if ($text === null || $text === '') {
            return '';
        }

        if (is_array($text)) {
            if (isset($text['key'])) {
                return __($text['key'], is_array($text['replace'] ?? null) ? $text['replace'] : []);
            }

            return collect($text)
                ->map(fn ($item) => $normalizeText($item))
                ->filter(fn ($item) => $item !== '')
                ->implode(', ');
        }

        return __((string) $text);
    };

    $rules = $field['rules'] ?? '';
    $type = $field['type'] ?? 'text';
    $label = $normalizeText($field['label'] ?? '');
    $hint = !empty($field['hint']) ? $normalizeText($field['hint']) : '';
    $mediaNotes = collect([
        $field['recommended_size'] ?? null,
        $field['accepted_formats'] ?? null,
    ])->filter()->map(fn ($note) => $normalizeText($note))->implode(' ');
    $resolvedMediaHint = trim(collect([$hint, $mediaNotes])->filter()->implode(' '));
    $displayLabel = $showLabel ? $label : '';
    $displayHint = $showHint ? $hint : '';
    $displayMediaHint = $showHint ? $resolvedMediaHint : '';
    $required = is_array($rules)
        ? collect($rules)->contains(fn ($rule) => is_string($rule) && str_contains($rule, 'required'))
        : str_contains((string) $rules, 'required');
    $errorName = $errorKey ?? $name;
    $resolvedValue = old($name, $value ?? ($field['default'] ?? null));
    $selectOptions = collect($field['options'] ?? [])
        ->map(fn ($optionLabel) => $normalizeText($optionLabel))
        ->all();

    if (($field['options_resolver'] ?? null) === 'timezones') {
        $selectOptions = collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz])->toArray();
    }
@endphp

@if(in_array($type, ['boolean', 'feature'], true))
    <x-forms.toggle :label="$displayLabel" :name="$name" :checked="(bool) $resolvedValue" />
@elseif($type === 'textarea')
    <x-forms.textarea :label="$displayLabel" :name="$name" :value="$resolvedValue" :required="$required" :hint="$displayHint" rows="4" />
@elseif($type === 'select')
    @if(count($selectOptions) > 10 || !empty($field['options_resolver']))
        <x-forms.tom-select :label="$displayLabel" :name="$name" :selected="$resolvedValue">
            @foreach($selectOptions as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($resolvedValue == $optionValue)>{{ __($optionLabel) }}</option>
            @endforeach
        </x-forms.tom-select>
    @else
        <x-forms.select :label="$displayLabel" :name="$name" :selected="$resolvedValue" :options="$selectOptions" />
    @endif
@elseif($type === 'media')
    <x-media.picker :label="$displayLabel" :name="$name" :value="$resolvedValue" :accept="$field['accept'] ?? 'image'" :hint="$displayMediaHint" />
@elseif($type === 'color')
    <div>
        @if($displayLabel)
            <label class="form-label">{{ $displayLabel }}</label>
        @endif
        <div class="setting-color-field">
            <input type="color" value="{{ $resolvedValue ?? '#000000' }}" class="setting-color-swatch" oninput="this.nextElementSibling.value = this.value">
            <input
                type="text"
                name="{{ $name }}"
                value="{{ $resolvedValue ?? '#000000' }}"
                class="setting-color-hex"
                maxlength="7"
                pattern="^#[0-9A-Fa-f]{6}$"
                oninput="this.previousElementSibling.value = this.value"
            >
        </div>
        @if($displayHint)
            <p class="form-hint">{{ $displayHint }}</p>
        @endif
        @error($errorName)
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>
@elseif($type === 'checkbox')
    <x-forms.checkbox-group :label="$displayLabel" :name="$name" :selected="$resolvedValue ?? []" :options="$selectOptions" :columns="$field['columns'] ?? 2" :hint="$displayHint" />
@elseif($type === 'tags')
    <x-forms.tom-select :label="$displayLabel" :name="$name . '[]'" :selected="$resolvedValue ?? []" multiple>
        @foreach($selectOptions as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, (array) $resolvedValue, true))>{{ __($optionLabel) }}</option>
        @endforeach
    </x-forms.tom-select>
@elseif(in_array($type, ['date', 'date_range', 'datetime', 'time'], true))
    @php
        $pickerMode = match ($type) {
            'date_range' => 'range',
            'datetime' => 'datetime',
            'time' => 'time',
            default => 'date',
        };
    @endphp
    <x-forms.datepicker :label="$displayLabel" :name="$name" :value="$resolvedValue" :mode="$pickerMode" :hint="$displayHint" />
@elseif($type === 'editor')
    <x-forms.editor :label="$displayLabel" :name="$name" :value="$resolvedValue" :placeholder="$hint ?: __('Type your content here...')" />
@elseif($type === 'repeater')
    <x-forms.repeater :label="$displayLabel" :name="$name" :items="$resolvedValue ?? []" :schema="$field['schema'] ?? []" :hint="$displayHint" :error-key="$errorName" />
@else
    <x-forms.input :label="$displayLabel" :name="$name" :type="$type" :value="$resolvedValue" :required="$required" :hint="$displayHint" />
@endif
