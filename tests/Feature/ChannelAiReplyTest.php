<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Services\ChannelAiReplyService;
use App\Modules\Commerce\AiAgents\StoreAssistantAgent;
use App\Modules\Commerce\AiTools\GetProductDetailsTool;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\Media\Models\Media;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as ToolRequest;

uses(RefreshDatabase::class);

function channelAiContext(string $provider = 'whatsapp', bool $enabled = true): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $chatbot = Chatbot::query()->create(['workspace_id' => $workspace->id, 'name' => 'Bot', 'persona' => 'Help.', 'greeting' => 'Hi', 'is_active' => true]);
    app(ChannelAiReplyService::class)->updateSettings($workspace, [$provider => ['enabled' => $enabled, 'chatbot_id' => $chatbot->id]]);

    $account = ChannelAccount::query()->create(['workspace_id' => $workspace->id, 'provider' => $provider, 'name' => $provider, 'status' => 'connected']);
    $contact = Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Ann', 'phone' => $provider === 'whatsapp' ? '+15551230000' : null]);
    if ($provider !== 'whatsapp') {
        ContactProviderIdentity::query()->create(['workspace_id' => $workspace->id, 'contact_id' => $contact->id, 'channel_account_id' => $account->id, 'provider' => $provider, 'provider_contact_id' => 'psid-1']);
    }
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id, 'channel_account_id' => $account->id, 'provider' => $provider,
        'provider_conversation_id' => 'c1', 'contact_id' => $contact->id, 'status' => 'open', 'session_expires_at' => now()->addHours(5),
    ]);
    $inbound = Message::query()->create([
        'workspace_id' => $workspace->id, 'channel_account_id' => $account->id, 'provider' => $provider,
        'conversation_id' => $conversation->id, 'contact_id' => $contact->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Do you have the hoodie in other colors?', 'status' => 'received',
    ]);

    return [$user, $workspace, $conversation, $inbound];
}

/**
 * @param  array<int, array<string, mixed>>  $sent
 */
function fakeChannelSend(?int $times = null, array &$sent = []): void
{
    $mock = Mockery::mock(ChannelManager::class);
    $expectation = $mock->shouldReceive('sendMessage');
    if ($times !== null) {
        $expectation->times($times);
    }
    $expectation->andReturnUsing(function ($account, $recipient, $payload) use (&$sent): array {
        $sent[] = ['recipient' => $recipient, 'payload' => $payload];

        return ['ok' => true, 'provider_message_id' => 'mid.'.count($sent)];
    });
    app()->instance(ChannelManager::class, $mock);
}

function hoodieWithColors(int $workspaceId, int $colors): Product
{
    $media = Media::query()->create(['name' => 'hoodie', 'file_name' => 'hoodie.png', 'original_name' => 'hoodie.png', 'mime_type' => 'image/png', 'extension' => 'png', 'type' => 'image', 'size' => 100, 'disk' => 'public', 'path' => 'products/hoodie.png']);
    $product = Product::query()->create(['workspace_id' => $workspaceId, 'name' => 'Fleece Hoodie', 'slug' => 'fleece-hoodie', 'sku' => 'hoodie-'.$workspaceId, 'status' => 'active', 'visibility' => 'published', 'selling_mode' => 'both', 'single_piece_price' => 25, 'wholesale_price' => 12, 'ws_enabled' => true, 'ws_main_moq' => 24, 'primary_media_id' => $media->id]);
    foreach (array_slice(['Black', 'Navy', 'Red', 'Grey', 'Olive', 'Cream'], 0, $colors) as $name) {
        ProductColor::query()->create(['workspace_id' => $workspaceId, 'product_id' => $product->id, 'name' => $name, 'slug' => strtolower($name)]);
    }

    return $product;
}

it('sends an AI reply when the owner enabled WhatsApp AI', function (): void {
    [, , , $inbound] = channelAiContext();
    fakeChannelSend(1);

    $reply = app(ChannelAiReplyService::class)->reply($inbound);

    expect($reply)->not->toBeNull()
        ->and($reply->direction)->toBe('outbound')
        ->and($reply->payload['source'])->toBe('ai_chatbot');
});

it('does not reply when disabled, assigned to a human, or outside the 24h window', function (): void {
    [, , , $inbound] = channelAiContext('whatsapp', false);
    fakeChannelSend(0);
    expect(app(ChannelAiReplyService::class)->reply($inbound))->toBeNull();

    [, , $conversation, $inbound2] = channelAiContext();
    $conversation->update(['session_expires_at' => now()->subMinute()]);
    expect(app(ChannelAiReplyService::class)->reply($inbound2))->toBeNull();

    [$user, , $conversation3, $inbound3] = channelAiContext();
    $conversation3->update(['assigned_to' => $user->id]);
    expect(app(ChannelAiReplyService::class)->reply($inbound3))->toBeNull();
});

it('keeps reading the old WhatsApp AI setting', function (): void {
    [, $workspace] = channelAiContext('whatsapp', false);
    $chatbot = Chatbot::query()->where('workspace_id', $workspace->id)->first();
    $workspace->update(['settings' => ['whatsapp_ai' => ['enabled' => true, 'chatbot_id' => $chatbot->id]]]);

    expect(app(ChannelAiReplyService::class)->enabledFor($workspace->id, 'whatsapp'))->toBeTrue()
        ->and(app(ChannelAiReplyService::class)->enabledFor($workspace->id, 'messenger'))->toBeFalse()
        ->and(app(ChannelAiReplyService::class)->enabledFor($workspace->id, 'telegram'))->toBeFalse();
});

it('answers Messenger only when that channel is switched on', function (): void {
    [, $workspace, , $inbound] = channelAiContext('messenger');
    $sent = [];
    fakeChannelSend(1, $sent);

    expect(app(ChannelAiReplyService::class)->reply($inbound))->not->toBeNull()
        ->and($sent[0]['recipient']['id'])->toBe('psid-1');

    app(ChannelAiReplyService::class)->updateSettings($workspace, ['messenger' => ['enabled' => false, 'chatbot_id' => null]]);
    expect(app(ChannelAiReplyService::class)->reply($inbound->fresh()))->toBeNull();
});

it('sends up to four color photos with captions after a WhatsApp product reply', function (): void {
    [, $workspace, , $inbound] = channelAiContext();
    hoodieWithColors($workspace->id, 6);
    $sent = [];
    fakeChannelSend(null, $sent);
    StoreAssistantAgent::fake(function () use ($workspace): string {
        (new GetProductDetailsTool($workspace->id))->handle(new ToolRequest(['product' => 'fleece-hoodie']));

        return 'Yes! The Fleece Hoodie comes in 6 colors. Photos below.';
    });

    $reply = app(ChannelAiReplyService::class)->reply($inbound);

    $images = collect($sent)->where('payload.type', 'image');
    expect($reply->body)->toContain('Fleece Hoodie')
        ->and($sent[0]['payload']['type'])->toBe('text')
        ->and($images)->toHaveCount(4)
        ->and($images->first()['payload']['caption'])->toContain('Fleece Hoodie - Black')
        ->and($images->first()['payload']['caption'])->toContain('Retail:')
        ->and(collect($sent)->last()['payload']['body'])->toContain('+2 more colors')
        ->and(Message::query()->where('conversation_id', $inbound->conversation_id)->where('type', 'image')->count())->toBe(4);
});

it('lets the owner save AI auto-reply for each channel', function (): void {
    [$user, $workspace] = channelAiContext('whatsapp', false);
    $chatbot = Chatbot::query()->where('workspace_id', $workspace->id)->first();
    $http = $this->withoutMiddleware([Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])->actingAs($user);

    $http->get(route('user.chatbots.channel-ai.edit'))->assertOk()->assertSee('Messenger')->assertSee('Instagram');
    $http->put(route('user.chatbots.channel-ai.update'), ['channels' => [
        'whatsapp' => ['enabled' => '1', 'chatbot_id' => $chatbot->id],
        'messenger' => ['enabled' => '0', 'chatbot_id' => ''],
        'instagram' => ['enabled' => '1', 'chatbot_id' => $chatbot->id],
    ]])->assertRedirect()->assertSessionHasNoErrors();
    $http->put(route('user.chatbots.channel-ai.update'), ['channels' => ['messenger' => ['enabled' => '1', 'chatbot_id' => '']]])
        ->assertSessionHasErrors('channels.messenger.chatbot_id');

    $service = app(ChannelAiReplyService::class);
    expect($service->enabledFor($workspace->id, 'whatsapp'))->toBeTrue()
        ->and($service->enabledFor($workspace->id, 'messenger'))->toBeFalse()
        ->and($service->enabledFor($workspace->id, 'instagram'))->toBeTrue();
});
