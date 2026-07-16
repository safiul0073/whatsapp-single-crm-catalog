<?php

namespace App\Modules\SupportTickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('support-tickets.attachments.max_size');
        $allowedMimes = implode(',', config('support-tickets.attachments.allowed_mimes', []));

        return [
            'message' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip', 'mimetypes:'.$allowedMimes, 'max:'.$maxSize],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => __('Please enter a reply message.'),
            'message.max' => __('Message may not exceed 10,000 characters.'),
            'attachments.*.max' => __('Each attachment may not exceed :size MB.', ['size' => config('support-tickets.attachments.max_size') / 1024]),
            'attachments.*.mimes' => __('Unsupported file type.'),
            'attachments.*.mimetypes' => __('Unsupported file type.'),
        ];
    }
}
