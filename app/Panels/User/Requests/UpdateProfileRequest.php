<?php

namespace App\Panels\User\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $section = $this->input('section', 'details');

        $rules = [
            'section' => ['required', Rule::in(['details', 'security', 'avatar', 'preferences'])],
        ];

        return match ($section) {
            'security' => $rules + [
                'current_password' => ['required', 'current_password:web'],
                'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            ],
            'avatar' => $rules + [
                'avatar' => ['nullable', 'integer', Rule::exists('media', 'id')->where('uploaded_by', auth()->id())],
                'avatar_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'remove_avatar' => ['nullable', 'boolean'],
            ],
            'preferences' => $rules + [
                'locale' => ['required', Rule::in($this->availableLocales())],
                'timezone' => ['required', 'timezone:all'],
            ],
            default => $rules + [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore(auth()->id())],
                'phone' => ['nullable', 'string', 'max:20'],
                'bio' => ['nullable', 'string', 'max:500'],
            ],
        };
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => __('The current password is incorrect.'),
            'avatar_upload.image' => __('Please upload a valid image file.'),
            'avatar_upload.max' => __('Profile photos must be 5 MB or smaller.'),
            'timezone.timezone' => __('Please select a valid timezone.'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function availableLocales(): array
    {
        $locales = collect(glob(resource_path('lang/*.json')) ?: [])
            ->map(fn (string $path): string => basename($path, '.json'))
            ->values()
            ->all();

        return $locales === [] ? [config('app.locale', 'en')] : $locales;
    }
}
