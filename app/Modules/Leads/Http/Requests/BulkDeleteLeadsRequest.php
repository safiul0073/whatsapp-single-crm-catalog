<?php

namespace App\Modules\Leads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer'],
        ];
    }
}
