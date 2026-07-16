<?php

namespace App\Modules\Chatbots\Http\Requests;

use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatbotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = app(WorkspaceResolver::class)->current($this->user());
        $workspaceId = $workspace?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'persona' => ['required', 'string', 'max:5000'],
            'greeting' => ['nullable', 'string', 'max:500'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:64', 'max:8192'],
            'fallback_only_knowledge_base' => ['nullable', 'boolean'],
            'confidence_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'handoff_on_request' => ['nullable', 'boolean'],
            'handoff_on_unsure' => ['nullable', 'boolean'],
            'handoff_off_hours' => ['nullable', 'boolean'],
            'handoff_message' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'knowledge_bases' => ['nullable', 'array'],
            'knowledge_bases.*' => [
                'integer',
                Rule::exists(KnowledgeBase::class, 'id')->where('workspace_id', $workspaceId),
            ],
        ];
    }
}
