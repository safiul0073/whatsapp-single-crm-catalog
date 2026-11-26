<?php

namespace App\Modules\MetaSocial\Http\Requests;

use App\Modules\MetaSocial\Services\MetaPostAiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMetaPostImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meta-social.publish') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:3', 'max:1000'],
            'style' => ['required', Rule::in(MetaPostAiService::IMAGE_STYLES)],
            'product_id' => ['nullable', 'integer'],
        ];
    }
}
