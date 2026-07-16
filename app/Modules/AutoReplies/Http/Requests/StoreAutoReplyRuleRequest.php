<?php

namespace App\Modules\AutoReplies\Http\Requests;

use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutoReplyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());
        $replyType = (string) $this->input('reply_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'trigger_type' => ['required', Rule::in(['keyword', 'welcome', 'out_of_hours', 'fallback'])],
            'trigger_value' => ['nullable', 'required_if:trigger_type,keyword', 'string', 'max:1000'],
            'match_type' => ['nullable', 'required_if:trigger_type,keyword', Rule::in(['exact', 'contains', 'regex'])],
            'reply_type' => ['required', Rule::in(['text', 'template', 'media'])],
            'reply_text' => [Rule::requiredIf($replyType === 'text'), 'nullable', 'string', 'max:4096'],
            'message_template_id' => [
                Rule::requiredIf($replyType === 'template'),
                'nullable',
                'integer',
                Rule::exists('message_templates', 'id')->where(fn ($query) => $query
                    ->where('workspace_id', $workspace?->id)
                    ->where('provider', 'whatsapp')
                    ->where('status', MessageTemplateStatus::Approved->value)),
            ],
            'media_id' => [
                Rule::requiredIf($replyType === 'media' && blank($this->input('media_url'))),
                'nullable',
                'integer',
                Rule::exists('media', 'id')->where(fn ($query) => $query->where('uploaded_by', $this->user()?->id)),
            ],
            'media_url' => [
                Rule::requiredIf($replyType === 'media' && blank($this->input('media_id'))),
                'nullable',
                'url',
                'max:2048',
            ],
            'media_type' => ['nullable', Rule::in(['image', 'video', 'audio', 'document'])],
            'media_caption' => ['nullable', 'string', 'max:1024'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'trigger_value.required_if' => 'Add at least one keyword for keyword-triggered replies.',
            'reply_text.required' => 'Add the reply text that should be sent.',
            'message_template_id.required' => 'Choose an approved WhatsApp template for template replies.',
            'message_template_id.exists' => 'Choose an approved WhatsApp template from this workspace.',
            'media_id.required' => 'Choose a media file or add a media URL.',
            'media_url.required_if' => 'Add a media URL for media replies.',
            'media_url.required' => 'Choose a media file or add a media URL.',
        ];
    }
}
