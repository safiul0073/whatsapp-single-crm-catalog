<?php

namespace App\Modules\Commerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackPageViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visitor_key' => ['required', 'uuid'],
            'path' => ['required', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:500'],
        ];
    }
}
