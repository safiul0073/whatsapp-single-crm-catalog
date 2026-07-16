<?php

namespace App\Panels\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,'.$this->route('staff')->id,
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                Rule::exists('roles', 'name')->where(function ($query) {
                    $query->where('guard_name', 'admin')
                        ->where('name', '!=', 'super-admin');
                }),
            ],
        ];
    }
}
