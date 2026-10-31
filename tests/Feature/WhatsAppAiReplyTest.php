<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\WhatsAppAiReplyService;
use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function whatsAppAiContext(bool $enabled = true): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $chatbot = Chatbot::query()->create(['workspace_id' => $workspace->id, 'name' => 'Bot', 'persona' => 'Help.', 'greeting' => 'Hi', 'is_active' => true]);
    app(WhatsAppAiReplyService::class)->updateSettings($workspace, $enabled, $chatbot->id);

    $account = ChannelAccount::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'WA', 'status' => 'connected']);
    $contact = Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Ann', 'phone' => '+15551230000']);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp',
        'provider_conversation_id' => 'c1', 'contact_id' => $contact->id, 'status' => 'open', 'session_expires_at' => now()->addHours(5),
    ]);
    $inbound = Message::query()->create([
        'workspace_id' => $workspace->id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp',
        'conversation_id' => $conversation->id, 'contact_id' => $contact->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'What are your hours?', 'status' => 'received',
    ]);

    return [$user, $workspace, $conversation, $inbound];
}

function fakeChannelSend(int $times): void
{
    $mock = Mockery::mock(ChannelManager::class);
    $mock->shouldReceive('sendMessage')->times($times)->andReturn(['ok' => true, 'provider_message_id' => 'wamid.1']);
    app()->instance(ChannelManager::class, $mock);
}

it('sends an AI reply when the owner enabled WhatsApp AI', function (): void {
    [, , $conversation, $inbound] = whatsAppAiContext();
    fakeChannelSend(1);

    $reply = app(WhatsAppAiReplyService::class)->reply($inbound);

    expect($reply)->not->toBeNull()
        ->and($reply->direction)->toBe('outbound')
        ->and($reply->payload['source'])->toBe('ai_chatbot');
});

it('does not reply when disabled, assigned to a human, or outside the 24h window', function (): void {
    [, , , $inbound] = whatsAppAiContext(false);
    fakeChannelSend(0);
    expect(app(WhatsAppAiReplyService::class)->reply($inbound))->toBeNull();

    [, , $conversation, $inbound2] = whatsAppAiContext();
    $conversation->update(['session_expires_at' => now()->subMinute()]);
    expect(app(WhatsAppAiReplyService::class)->reply($inbound2))->toBeNull();
});

it('lets the owner toggle the setting', function (): void {
    [$user, $workspace] = whatsAppAiContext(false);
    $chatbot = Chatbot::query()->where('workspace_id', $workspace->id)->first();

    $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->put(route('user.chatbots.whatsapp-ai.update'), ['enabled' => '1', 'chatbot_id' => $chatbot->id])
        ->assertRedirect();

    expect(app(WhatsAppAiReplyService::class)->enabledFor($workspace->id))->toBeTrue();
});
