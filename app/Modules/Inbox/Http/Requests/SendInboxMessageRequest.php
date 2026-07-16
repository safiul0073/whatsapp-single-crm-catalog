<?php

namespace App\Modules\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendInboxMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:attachment', 'string', 'max:4096'],
            'attachment' => ['nullable', 'file', 'max:16384', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,audio/mpeg,audio/mp4,audio/ogg,audio/wav,application/pdf,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => __('Write a message or attach a file before sending.'),
            'body.max' => __('Messages can be at most 4096 characters.'),
            'attachment.max' => __('Attachments can be at most 16 MB.'),
            'attachment.mimetypes' => __('This file type is not supported for inbox messages.'),
        ];
    }
}
