<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Chatbots\Services\ChannelAiReplyService;
use App\Modules\Commerce\AiAgents\StoreAssistantAgent;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\SystemNotifications\Models\SystemNotification;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const HANDOFF_MESSAGE = 'A team member will reply shortly.';

/**
 * @param  array<string, mixed>  $handoffRules
 * @return array{0: User, 1: mixed, 2: Chatbot, 3: Conversation}
 */
function handoffContext(array $handoffRules = ['message' => HANDOFF_MESSAGE], bool $active = true): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $chatbot = Chatbot::query()->create(['workspace_id' => $workspace->id, 'name' => 'Shop Bot', 'persona' => 'Help.', 'greeting' => 'Hi', 'is_active' => $active, 'handoff_rules' => $handoffRules]);
    app(ChannelAiReplyService::class)->updateSettings($workspace, ['whatsapp' => ['enabled' => true, 'chatbot_id' => $chatbot->id]]);
    $account = ChannelAccount::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'WA', 'status' => 'connected']);
    $contact = Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Rafi', 'phone' => '+8801700000001']);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp',
        'provider_conversation_id' => 'h1', 'contact_id' => $contact->id, 'status' => 'open', 'session_expires_at' => now()->addHours(5),
    ]);

    return [$user, $workspace, $chatbot, $conversation];
}

function inboundOn(Conversation $conversation, string $body): Message
{
    return Message::query()->create([
        'workspace_id' => $conversation->workspace_id, 'channel_account_id' => $conversation->channel_account_id, 'provider' => $conversation->provider,
        'conversation_id' => $conversation->id, 'contact_id' => $conversation->contact_id, 'direction' => 'inbound', 'type' => 'text', 'body' => $body, 'status' => 'received',
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $sent
 */
function recordChannelSends(array &$sent): void
{
    $mock = Mockery::mock(ChannelManager::class);
    $mock->shouldReceive('sendMessage')->andReturnUsing(function ($account, $recipient, $payload) use (&$sent): array {
        $sent[] = $payload;

        return ['ok' => true, 'provider_message_id' => 'wamid.'.count($sent)];
    });
    app()->instance(ChannelManager::class, $mock);
}

it('hands the chat to a human when the customer asks for one, then stays quiet', function (): void {
    [$user, , , $conversation] = handoffContext();
    $sent = [];
    recordChannelSends($sent);
    StoreAssistantAgent::fake(['should not be used']);

    $reply = app(ChannelAiReplyService::class)->reply(inboundOn($conversation, 'I want to talk to a human please'));

    $conversation->refresh();
    expect($reply->body)->toBe(HANDOFF_MESSAGE)
        ->and($sent)->toHaveCount(1)
        ->and($conversation->isAiPaused())->toBeTrue()
        ->and($conversation->ai_pause_reason)->toBe(Conversation::AI_PAUSE_CUSTOMER_REQUEST)
        ->and(SystemNotification::query()->where('notifiable_id', $user->id)->where('type', 'inbox')->count())->toBe(1);
    StoreAssistantAgent::assertNeverPrompted();

    expect(app(ChannelAiReplyService::class)->reply(inboundOn($conversation, 'hello? anyone there?')))->toBeNull()
        ->and($sent)->toHaveCount(1)
        ->and(SystemNotification::query()->where('notifiable_id', $user->id)->count())->toBe(1);
});

it('hands off when the assistant answers with the handoff message', function (): void {
    [, , , $conversation] = handoffContext();
    $sent = [];
    recordChannelSends($sent);
    StoreAssistantAgent::fake(['Sorry, I do not know that. '.HANDOFF_MESSAGE]);

    app(ChannelAiReplyService::class)->reply(inboundOn($conversation, 'Do you ship to Japan by sea freight?'));

    expect($conversation->fresh()->ai_pause_reason)->toBe(Conversation::AI_PAUSE_BOT_UNSURE)
        ->and($sent)->toHaveCount(1);
});

it('does not pause the chat when the AI provider fails', function (): void {
    [, , , $conversation] = handoffContext();
    $sent = [];
    recordChannelSends($sent);
    StoreAssistantAgent::fake(fn () => throw new RuntimeException('quota exceeded'));

    expect(app(ChannelAiReplyService::class)->reply(inboundOn($conversation, 'Price of the black hoodie?')))->toBeNull()
        ->and($conversation->fresh()->isAiPaused())->toBeFalse()
        ->and($sent)->toBe([]);
});

it('respects the owner turning automatic handoff off, and treats never-saved rules as on', function (): void {
    [, , , $conversation] = handoffContext(['message' => HANDOFF_MESSAGE, 'on_request' => false, 'on_unsure' => false, 'configured' => true]);
    $sent = [];
    recordChannelSends($sent);
    StoreAssistantAgent::fake(['Sure, here is how to reach us.']);

    app(ChannelAiReplyService::class)->reply(inboundOn($conversation, 'can I talk to a human'));
    expect($conversation->fresh()->isAiPaused())->toBeFalse()
        ->and($sent[0]['body'])->toBe('Sure, here is how to reach us.');

    [, , , $legacy] = handoffContext(['message' => HANDOFF_MESSAGE, 'on_request' => false, 'on_unsure' => false]);
    app(ChannelAiReplyService::class)->reply(inboundOn($legacy, 'can I talk to a human'));
    expect($legacy->fresh()->isAiPaused())->toBeTrue();
});

it('stops replying on every channel when the chatbot master switch is off', function (): void {
    [, , , $conversation] = handoffContext(active: false);
    $sent = [];
    recordChannelSends($sent);

    expect(app(ChannelAiReplyService::class)->reply(inboundOn($conversation, 'Price?')))->toBeNull()
        ->and($sent)->toBe([]);
});

it('keeps the widget quiet in a paused chat and hands off on request', function (): void {
    [, $workspace, $chatbot] = handoffContext();
    $widget = ChatbotWidget::query()->create(['workspace_id' => $workspace->id, 'chatbot_id' => $chatbot->id, 'name' => 'Store', 'public_token' => 'handoff-widget', 'is_active' => true, 'allowed_domains' => ['shop.example.com'], 'settings' => []]);
    StoreAssistantAgent::fake(['should not be used']);
    $origin = ['Origin' => 'https://shop.example.com'];
    $session = $this->withHeaders($origin)->postJson(route('widgets.chatbot.sessions', $widget->public_token), ['visitor_uid' => 'v-h'])->json('session_token');

    $this->withHeaders($origin)->postJson(route('widgets.chatbot.messages', $widget->public_token), ['session_token' => $session, 'message' => 'Talk to a real person'])
        ->assertOk()
        ->assertJsonPath('reply.body', HANDOFF_MESSAGE);
    $conversation = Conversation::query()->where('workspace_id', $workspace->id)->where('provider', 'website_widget')->firstOrFail();
    expect($conversation->ai_pause_reason)->toBe(Conversation::AI_PAUSE_CUSTOMER_REQUEST);

    $this->withHeaders($origin)->postJson(route('widgets.chatbot.messages', $widget->public_token), ['session_token' => $session, 'message' => 'What colors do you have?'])
        ->assertOk()
        ->assertJsonPath('reply', null)
        ->assertJsonPath('error', 'Thanks. A team member will reply here soon.');
    StoreAssistantAgent::assertNeverPrompted();
});

it('lets a team member switch human takeover on and off from the web and mobile inbox', function (): void {
    [$user, $workspace, , $conversation] = handoffContext();
    $web = $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])->actingAs($user);

    $web->postJson(route('user.inbox.conversations.ai-pause', $conversation), ['paused' => true])
        ->assertOk()
        ->assertJsonPath('conversation.ai_paused', true)
        ->assertJsonPath('conversation.ai_pause_reason', Conversation::AI_PAUSE_MANUAL);
    expect($conversation->fresh()->ai_paused_by)->toBe($user->id);

    $web->postJson(route('user.inbox.conversations.ai-pause', $conversation), ['paused' => false])
        ->assertOk()
        ->assertJsonPath('conversation.ai_paused', false);

    Sanctum::actingAs($user);
    $this->postJson("/api/v1/mobile/workspaces/{$workspace->id}/inbox/conversations/{$conversation->id}/ai-pause", ['paused' => true])
        ->assertOk()
        ->assertJsonPath('conversation.ai_paused', true);

    $stranger = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($stranger)->postJson(route('user.inbox.conversations.ai-pause', $conversation), ['paused' => false])->assertNotFound();
    expect($conversation->fresh()->isAiPaused())->toBeTrue();
});
