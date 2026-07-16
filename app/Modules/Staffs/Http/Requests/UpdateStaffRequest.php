<?php

namespace App\Modules\Staffs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'password' => 'nullable|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }
}
