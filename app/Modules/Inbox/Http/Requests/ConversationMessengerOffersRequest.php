<?php

namespace App\Modules\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks the Messenger customer in a conversation to subscribe to the Page's offers.
 */
class ConversationMessengerOffersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inbox.reply') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
