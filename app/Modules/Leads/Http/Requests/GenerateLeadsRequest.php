<?php

namespace App\Modules\Leads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'size:2'],
            'place' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:1200'],
            'channel' => ['nullable', 'string', 'in:whatsapp,email,sms,telegram,any'],
            'count' => ['required', 'integer', 'min:1', 'max:25'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ];
    }
}
