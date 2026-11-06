<?php

namespace App\Modules\MetaSocial\Http\Requests;

use App\Modules\MetaSocial\Models\MetaPagePost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMetaPagePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meta-social.publish') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'page_id' => ['required', 'string', 'max:64'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['required', Rule::in(MetaPagePost::TARGETS)],
            'instagram_account_id' => [Rule::requiredIf(fn (): bool => in_array('instagram', (array) $this->input('targets'), true)), 'nullable', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:5000'],
            'link' => ['nullable', 'url:http,https', 'max:2048'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'product_id' => ['nullable', 'integer'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'targets.required' => __('Choose Facebook, Instagram or both.'),
            'instagram_account_id.required' => __('Choose the Instagram account to post to.'),
            'scheduled_at.after' => __('Pick a time in the future, or leave it empty to post now.'),
        ];
    }
}
