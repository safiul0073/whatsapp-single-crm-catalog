<?php

namespace App\Modules\Workspaces\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Please provide a workspace name.'),
            'name.max' => __('The workspace name must not exceed 255 characters.'),
            'timezone.timezone' => __('Please select a valid timezone.'),
        ];
    }
}
