<?php

namespace App\Modules\TikTok\Http\Requests;

use App\Modules\TikTok\Models\TikTokPost;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTikTokPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tiktok.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $direct = fn (): bool => $this->input('mode') === TikTokPost::MODE_DIRECT;

        return [
            'channel_account_id' => ['required', 'integer'],
            'mode' => ['required', Rule::in([TikTokPost::MODE_DIRECT, TikTokPost::MODE_DRAFT])],
            'video' => ['required', 'file', 'mimetypes:video/mp4,application/mp4,video/quicktime,video/webm', 'max:1048576'],
            'title' => ['nullable', 'string', 'max:2200'],
            'privacy_level' => [Rule::requiredIf($direct), 'nullable', Rule::in(TikTokPost::PRIVACY_LEVELS)],
            'allow_comment' => ['nullable', 'boolean'],
            'allow_duet' => ['nullable', 'boolean'],
            'allow_stitch' => ['nullable', 'boolean'],
            'disclose_commercial' => ['nullable', 'boolean'],
            'brand_organic' => ['nullable', 'boolean'],
            'brand_content' => ['nullable', 'boolean'],
            'is_aigc' => ['nullable', 'boolean'],
            'product_id' => ['nullable', 'integer'],
            'consent' => [Rule::requiredIf($direct), 'accepted_if:mode,'.TikTokPost::MODE_DIRECT],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'video.required' => __('Choose the video to post.'),
            'video.mimetypes' => __('TikTok accepts MP4, MOV and WebM videos.'),
            'video.max' => __('The video is too large (1 GB at most).'),
            'privacy_level.required' => __('Choose who can see this video.'),
            'consent.required' => __('Agree to TikTok\'s Music Usage Confirmation to post.'),
            'consent.accepted_if' => __('Agree to TikTok\'s Music Usage Confirmation to post.'),
        ];
    }
}
