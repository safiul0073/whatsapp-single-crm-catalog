<?php

namespace App\Modules\Shipping\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RateChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'effective_from' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt,pdf', 'extensions:xlsx,xls,csv,pdf'],
        ];
    }

    public function messages(): array
    {
        return ['file.mimes' => __('Upload an Excel, CSV or PDF rate chart.'), 'file.extensions' => __('Upload an Excel, CSV or PDF rate chart.')];
    }
}
