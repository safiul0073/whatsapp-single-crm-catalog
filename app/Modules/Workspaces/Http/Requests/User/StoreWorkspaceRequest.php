<?php

namespace App\Modules\Workspaces\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:100', 'unique:workspaces,slug'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Please provide a workspace name.'),
            'name.max' => __('The workspace name must not exceed 255 characters.'),
            'slug.required' => __('Please provide a workspace URL slug.'),
            'slug.alpha_dash' => __('The slug may only contain letters, numbers, dashes and underscores.'),
            'slug.max' => __('The slug must not exceed 100 characters.'),
            'slug.unique' => __('This workspace URL is already taken.'),
            'timezone.timezone' => __('Please select a valid timezone.'),
        ];
    }
}
