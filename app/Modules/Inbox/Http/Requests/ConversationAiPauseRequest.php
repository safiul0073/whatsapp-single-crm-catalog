<?php

namespace App\Modules\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConversationAiPauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inbox.reply') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'paused' => ['required', 'boolean'],
        ];
    }
}
