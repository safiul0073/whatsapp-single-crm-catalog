<?php

namespace App\Modules\SocialWidgets\Http\Requests;

use App\Modules\SocialWidgets\Enums\SocialWidgetLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialWidgetRequest extends FormRequest
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
            'layout' => ['required', Rule::enum(SocialWidgetLayout::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'layout.required' => __('Choose a layout to start with.'),
            'layout.enum' => __('That layout is not available.'),
        ];
    }
}
