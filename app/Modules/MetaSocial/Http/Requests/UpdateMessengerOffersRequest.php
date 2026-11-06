<?php

namespace App\Modules\MetaSocial\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMessengerOffersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'auto_request' => ['nullable', 'boolean'],
            'title' => ['required', 'string', 'max:65'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => __('Write the title customers see on the Get messages button.'),
            'title.max' => __('Meta allows at most 65 characters for this title.'),
        ];
    }
}
