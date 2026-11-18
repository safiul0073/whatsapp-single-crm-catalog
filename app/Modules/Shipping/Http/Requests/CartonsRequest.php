<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\ShipmentCarton;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cartons' => ['required', 'array', 'max:50'],
            'cartons.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'cartons.*.actual_weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:9999', 'required_with:cartons.*.length_cm,cartons.*.width_cm,cartons.*.height_cm'],
            'cartons.*.length_cm' => ['nullable', 'numeric', 'gt:0', 'max:9999', 'required_with:cartons.*.actual_weight_kg'],
            'cartons.*.width_cm' => ['nullable', 'numeric', 'gt:0', 'max:9999', 'required_with:cartons.*.actual_weight_kg'],
            'cartons.*.height_cm' => ['nullable', 'numeric', 'gt:0', 'max:9999', 'required_with:cartons.*.actual_weight_kg'],
            'cartons.*.packaging_type' => ['nullable', Rule::in(array_keys(ShipmentCarton::PACKAGING_TYPES))],
        ];
    }

    public function messages(): array
    {
        return [
            'cartons.*.actual_weight_kg.required_with' => __('Enter the weight for every carton that has dimensions.'),
            'cartons.*.length_cm.required_with' => __('Enter length, width and height for every carton.'),
            'cartons.*.width_cm.required_with' => __('Enter length, width and height for every carton.'),
            'cartons.*.height_cm.required_with' => __('Enter length, width and height for every carton.'),
        ];
    }

    /**
     * Carton rows that were actually filled in; blank spare rows are dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function cartons(): array
    {
        return array_values(array_filter(
            array_map(fn (array $carton): array => $carton + ['quantity' => 1, 'packaging_type' => 'carton'], array_map(fn (array $carton): array => array_filter($carton, fn ($value): bool => $value !== null && $value !== ''), $this->validated('cartons', []))),
            fn (array $carton): bool => isset($carton['actual_weight_kg'], $carton['length_cm'], $carton['width_cm'], $carton['height_cm']),
        ));
    }
}
