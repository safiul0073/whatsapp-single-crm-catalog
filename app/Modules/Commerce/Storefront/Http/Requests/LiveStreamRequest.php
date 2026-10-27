<?php

namespace App\Modules\Commerce\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LiveStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('store_workspace');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'signal' => [
                'type' => 'required|string|in:viewer-join,offer,answer,ice-candidate,viewer-leave',
                'sender_peer_id' => 'required|string|max:120',
                'target_peer_id' => 'nullable|string|max:120',
                'payload' => 'nullable|array',
            ],
            'comment' => [
                'author_name' => 'nullable|string|max:120',
                'body' => 'required|string|min:1|max:500',
            ],
            'react' => [
                'action' => 'required|string|in:like,join,leave,view',
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Please enter a comment message.',
            'body.max' => 'Comments may not exceed 500 characters.',
            'type.in' => 'Invalid WebRTC signaling packet type.',
            'action.in' => 'Invalid engagement action.',
        ];
    }
}
