<?php

namespace App\Modules\Crm\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManageCrmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('crm.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
