<?php

namespace App\Modules\Leads\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendLeadMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'in:whatsapp,sms,email,telegram'],
            'telegram_delivery_channel' => ['nullable', 'string', 'in:copy,whatsapp,sms,email'],
            'subject' => ['nullable', 'required_if:channel,email', 'string', 'max:255'],
            'body' => ['nullable', 'required_unless:channel,telegram', 'string', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required_if' => 'Add a subject before sending an email.',
            'body.required_unless' => 'Write a message before sending.',
        ];
    }
}
