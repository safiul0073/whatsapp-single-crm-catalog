<?php

namespace App\Modules\SupportTickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip', 'mimetypes:'.$allowedMimes, 'max:'.$maxSize],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => __('Please provide a subject for your ticket.'),
            'subject.max' => __('Subject may not exceed 255 characters.'),
            'message.required' => __('Please describe your issue.'),
            'message.max' => __('Message may not exceed 10,000 characters.'),
            'priority.required' => __('Please select a priority level.'),
            'priority.in' => __('Invalid priority selected.'),
            'attachments.*.max' => __('Each attachment may not exceed :size MB.', ['size' => config('support-tickets.attachments.max_size') / 1024]),
            'attachments.*.mimes' => __('Unsupported file type.'),
            'attachments.*.mimetypes' => __('Unsupported file type.'),
        ];
    }
}
