<?php

namespace App\Modules\Shipping\Http\Requests;

use App\Modules\Shipping\Models\AgentRateChart;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChartStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(AgentRateChart::STATUSES)]];
    }
}
