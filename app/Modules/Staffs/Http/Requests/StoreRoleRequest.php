<?php

namespace App\Modules\Staffs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'not_in:super-admin,user',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'admin')),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => [
                Rule::exists('permissions', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'admin')),
            ],
        ];
    }
}
