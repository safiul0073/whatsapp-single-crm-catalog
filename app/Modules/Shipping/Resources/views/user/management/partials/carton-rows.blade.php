@php
    $rows = collect(old('cartons', $cartons ?? []))->values();
    $rows = $rows->concat(array_fill(0, max(3, 5 - $rows->count()), []))->values();
@endphp
<div class="table-responsive">
    <table class="table w-full text-sm whitespace-nowrap">
        <thead>
            <tr>
                <th>{{ __('Cartons') }}</th>
                <th>{{ __('Weight each (kg)') }}</th>
                <th>{{ __('Length (cm)') }}</th>
                <th>{{ __('Width (cm)') }}</th>
                <th>{{ __('Height (cm)') }}</th>
                <th>{{ __('Packaging') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => $carton)
                <tr>
                    <td><input class="form-input form-input-sm w-20" type="number" min="1" max="999" name="cartons[{{ $index }}][quantity]" value="{{ $carton['quantity'] ?? '' }}" placeholder="1" aria-label="{{ __('Number of cartons') }}"></td>
                    <td><input class="form-input form-input-sm w-24" type="number" step="0.001" min="0" name="cartons[{{ $index }}][actual_weight_kg]" value="{{ $carton['actual_weight_kg'] ?? '' }}" aria-label="{{ __('Actual weight per carton in kg') }}"></td>
                    <td><input class="form-input form-input-sm w-24" type="number" step="0.01" min="0" name="cartons[{{ $index }}][length_cm]" value="{{ $carton['length_cm'] ?? '' }}" aria-label="{{ __('Length in cm') }}"></td>
                    <td><input class="form-input form-input-sm w-24" type="number" step="0.01" min="0" name="cartons[{{ $index }}][width_cm]" value="{{ $carton['width_cm'] ?? '' }}" aria-label="{{ __('Width in cm') }}"></td>
                    <td><input class="form-input form-input-sm w-24" type="number" step="0.01" min="0" name="cartons[{{ $index }}][height_cm]" value="{{ $carton['height_cm'] ?? '' }}" aria-label="{{ __('Height in cm') }}"></td>
                    <td>
                        <select class="form-input form-input-sm" name="cartons[{{ $index }}][packaging_type]" aria-label="{{ __('Packaging type') }}">
                            @foreach(\App\Modules\Shipping\Models\ShipmentCarton::PACKAGING_TYPES as $value => $label)
                                <option value="{{ $value }}" @selected(($carton['packaging_type'] ?? 'carton') === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="form-hint">{{ __('Leave a row blank to ignore it. Save to get more blank rows.') }}</p>
@error('cartons')<p class="form-hint text-error">{{ $message }}</p>@enderror
@foreach($errors->get('cartons.*') as $messages)<p class="form-hint text-error">{{ $messages[0] }}</p>@endforeach
