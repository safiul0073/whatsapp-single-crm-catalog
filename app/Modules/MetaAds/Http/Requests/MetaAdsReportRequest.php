<?php

namespace App\Modules\MetaAds\Http\Requests;

use App\Modules\MetaAds\Services\MetaAdsReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaAdsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(array_keys(MetaAdsReportService::PERIODS))],
        ];
    }

    public function period(): string
    {
        return (string) ($this->validated('period') ?? '30d');
    }
}
