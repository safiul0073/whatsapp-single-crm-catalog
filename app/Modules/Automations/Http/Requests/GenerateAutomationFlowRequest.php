<?php

namespace App\Modules\Automations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAutomationFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:10', 'max:1200'],
        ];
    }
}
