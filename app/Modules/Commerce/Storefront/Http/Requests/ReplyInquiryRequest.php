<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Please enter a subject for your reply.',
            'message.required' => 'Please write a reply message.',
        ];
    }
}
