<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Services\ShippingReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'period' => ['nullable', Rule::in(array_keys(ShippingReportService::PERIODS))],
        ];
    }
}
