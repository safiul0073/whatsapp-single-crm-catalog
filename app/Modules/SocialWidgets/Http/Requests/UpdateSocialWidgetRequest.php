<?php

namespace App\Modules\SocialWidgets\Http\Requests;

use App\Modules\SocialWidgets\Enums\SocialWidgetLayout;
use App\Modules\SocialWidgets\Enums\SocialWidgetTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSocialWidgetRequest extends FormRequest
{
    public const COLOR_KEYS = [
        'canvasBackground', 'headerBackground', 'headerText', 'headerButton',
        'postBackground', 'postText', 'postBorder', 'popupBackground', 'popupText',
        'loadMoreBackground', 'loadMoreText', 'loadMoreBorder',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $color = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/'];

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'layout' => ['required', Rule::enum(SocialWidgetLayout::class)],
            'channel_account_id' => ['nullable', 'integer'],
            'settings' => ['required', 'array'],
            'settings.source.username' => ['nullable', 'string', 'max:60', 'regex:/^@?[A-Za-z0-9._]+$/'],
            'settings.title.text' => ['nullable', 'string', 'max:120'],
            'settings.title.show' => ['required', 'boolean'],
            'settings.header.show' => ['required', 'boolean'],
            'settings.header.gap' => ['required', 'integer', 'between:0,64'],
            'settings.columns.mode' => ['required', Rule::in(['auto', 'manual'])],
            'settings.columns.count' => ['required', 'integer', 'between:1,8'],
            'settings.columns.gap' => ['required', 'integer', 'between:0,48'],
            'settings.columns.width' => ['required', 'integer', 'between:320,1200'],
            'settings.post.limit' => ['required', 'integer', 'between:1,48'],
            'settings.click.action' => ['required', Rule::in(['popup', 'link'])],
            'settings.click.swipe' => ['required', 'boolean'],
            'settings.style.theme' => ['required', Rule::enum(SocialWidgetTheme::class)],
            'settings.style.radius' => ['required', 'integer', 'between:0,40'],
            'settings.style.borderWidth' => ['required', 'integer', 'between:0,8'],
            'settings.style.font' => ['required', Rule::in(['sans', 'serif', 'mono'])],
            'settings.style.loadMoreBorderWidth' => ['required', 'integer', 'between:0,8'],
            'settings.style.loadMoreRadius' => ['required', 'integer', 'between:0,40'],
        ];

        foreach (['profilePicture', 'fullName', 'username', 'verifiedBadge', 'stats', 'followButton'] as $element) {
            $rules["settings.header.elements.$element"] = ['required', 'boolean'];
        }

        foreach (['caption', 'likes', 'comments'] as $element) {
            $rules["settings.post.$element"] = ['required', 'boolean'];
        }

        foreach (['header', 'caption', 'comments', 'followButton', 'counts', 'shareButton'] as $element) {
            $rules["settings.click.popup.$element"] = ['required', 'boolean'];
        }

        foreach (self::COLOR_KEYS as $key) {
            $rules["settings.style.colors.$key"] = $color;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.style.colors.*.regex' => __('Colours must be hex values like #1faa53.'),
            'settings.source.username.regex' => __('Enter an Instagram username like @yourbrand.'),
            'layout.enum' => __('That layout is not available.'),
            'settings.style.theme.enum' => __('That theme preset is not available.'),
        ];
    }

    /**
     * Only whitelisted keys are kept so unknown settings never reach storage or the public embed.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $validated = $this->validated('settings');
        $settings = [];

        foreach (array_keys($this->rules()) as $key) {
            if (str_starts_with($key, 'settings.') && $key !== 'settings') {
                data_set($settings, substr($key, 9), data_get($validated, substr($key, 9)));
            }
        }

        return $settings;
    }
}
