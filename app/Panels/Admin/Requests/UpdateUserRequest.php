<?php

namespace App\Panels\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$this->route('user')->id,
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'phone' => 'nullable|string|max:20',
            'phone_verified_at' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'email_verified_at' => 'nullable|boolean',
            '2fa_action' => 'nullable|string|in:disable,reset',
            'avatar' => 'nullable|string|max:255',
        ];
    }
}
