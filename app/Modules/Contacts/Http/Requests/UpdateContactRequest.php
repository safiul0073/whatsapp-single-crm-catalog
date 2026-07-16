<?php

namespace App\Modules\Contacts\Http\Requests;

use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::enum(ContactSource::class)],
            'opt_in_status' => ['nullable', Rule::enum(ContactOptInStatus::class)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:contact_tags,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'exists:contact_groups,id'],
        ];
    }
}
