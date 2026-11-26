<?php

namespace App\Modules\MetaSocial\Http\Requests;

use App\Modules\MetaSocial\Services\MetaPostAiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMetaPostCaptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meta-social.publish') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer'],
            'tone' => ['required', Rule::in(MetaPostAiService::TONES)],
            'language' => ['required', Rule::in(MetaPostAiService::LANGUAGES)],
            'hashtags' => ['boolean'],
            'emojis' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
