<?php

namespace App\Modules\Chatbots\Services;

use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\NotificationTemplates\Notifications\MobileAlert;
use App\Modules\SystemNotifications\Services\SystemNotificationService;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Hands a chat from the AI chatbot to the team: turns on Human takeover and tells the owner.
 */
class HumanHandoffService
{
    /** Reasons that switch Human takeover on. Technical AI failures never do. */
    public const PAUSING_REASONS = [Conversation::AI_PAUSE_CUSTOMER_REQUEST, Conversation::AI_PAUSE_BOT_UNSURE];

    public function __construct(protected SystemNotificationService $notifications) {}

    /**
     * Whether this chatbot's handoff rules allow pausing for the given draft reason.
     */
    public function shouldHandOff(Chatbot $chatbot, ?string $reason): bool
    {
        return match ($reason) {
            Conversation::AI_PAUSE_CUSTOMER_REQUEST => $chatbot->handoffEnabled('on_request'),
            Conversation::AI_PAUSE_BOT_UNSURE => $chatbot->handoffEnabled('on_unsure'),
            default => false,
        };
    }

    public function handOff(Conversation $conversation, Chatbot $chatbot, string $reason, ?string $customerMessage = null): void
    {
        if ($conversation->isAiPaused()) {
            return;
        }

        $conversation->pauseAi($reason);
        $conversation->loadMissing('contact');

        $customer = $conversation->contact?->name ?: __('A customer');
        $title = $reason === Conversation::AI_PAUSE_CUSTOMER_REQUEST
            ? __(':customer asked for a team member', ['customer' => $customer])
            : __(':chatbot could not answer :customer', ['chatbot' => $chatbot->name, 'customer' => $customer]);
        $body = Str::limit(trim((string) $customerMessage) ?: __('Open the chat to reply. AI replies are off for this conversation.'), 140);

        foreach ($this->recipients($conversation) as $user) {
            try {
                $this->notifications->send($user, [
                    'title' => $title,
                    'body' => $body,
                    'icon' => 'ph-user-switch',
                    'url' => route('user.inbox.index', ['conversation' => $conversation->id]),
                ], 'inbox');

                if ($user->deviceTokens()->exists()) {
                    $user->notify(new MobileAlert($title, $body, [
                        'type' => 'inbox',
                        'workspace_id' => $conversation->workspace_id,
                        'conversation_id' => $conversation->id,
                    ]));
                }
            } catch (Throwable $exception) {
                Log::warning('Human handoff notification failed.', ['conversation_id' => $conversation->id, 'user_id' => $user->id, 'message' => $exception->getMessage()]);
            }
        }
    }

    /**
     * @return list<User>
     */
    protected function recipients(Conversation $conversation): array
    {
        $users = collect([
            Workspace::query()->find($conversation->workspace_id)?->owner,
            $conversation->assigned_to ? User::query()->find($conversation->assigned_to) : null,
        ])->filter()->unique('id');

        return $users->values()->all();
    }
}
