<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\Inbox\Services\InboxService;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function chatbotWidgetMiddleware(): array
{
    return [Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class];
}

function chatbotWidgetContext(?User $user = null): array
{
    $user ??= User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Website Assistant',
        'persona' => 'Answer website visitor questions.',
        'greeting' => 'Hi, how can I help?',
        'is_active' => true,
    ]);

    return [$user, $workspace, null, $chatbot];
}

it('renders the widget form as a step setup with preview summary and embed code', function (): void {
    [$user, $workspace, , $chatbot] = chatbotWidgetContext();
    $widget = ChatbotWidget::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_id' => $chatbot->id,
        'name' => 'Stepper Widget',
        'public_token' => 'stepper-widget-token',
        'is_active' => true,
        'allowed_domains' => ['example.com'],
        'lead_fields' => ['name', 'email'],
        'greeting' => 'Hello from the widget.',
        'settings' => ['primary_color' => '#16a34a', 'position' => 'right', 'launcher_label' => 'Chat'],
    ]);

    $this->withoutMiddleware(chatbotWidgetMiddleware())
        ->actingAs($user)
        ->get(route('user.chatbots.widgets.create'))
        ->assertOk()
        ->assertSee('Basic info')
        ->assertSee('Appearance')
        ->assertSee('Allowed domains')
        ->assertSee('Embed Codes')
        ->assertSee('Automated chatbot replies')
        ->assertSee('Preview')
        ->assertSee('Summary')
        ->assertSee('Save the widget to generate its public embed code.');

    $this->withoutMiddleware(chatbotWidgetMiddleware())
        ->actingAs($user)
        ->get(route('user.chatbots.widgets.edit', $widget))
        ->assertOk()
        ->assertSee('Stepper Widget')
        ->assertSee('Script tag')
        ->assertSee(route('widgets.chatbot.loader', $widget->public_token), false)
        ->assertSee('Domain summary');
});

it('lets a user create update and delete a website chatbot widget', function (): void {
    [$user, $workspace, , $chatbot] = chatbotWidgetContext();

    $this->withoutMiddleware(chatbotWidgetMiddleware())
        ->actingAs($user)
        ->post(route('user.chatbots.widgets.store'), [
            'name' => 'Storefront Chat',
            'chatbot_id' => $chatbot->id,
            'greeting' => 'Welcome to our store.',
            'allowed_domains' => "example.com\nshop.example.com",
            'primary_color' => '#15803d',
            'position' => 'right',
            'launcher_label' => 'Help',
            'automated_reply_enabled' => '1',
            'lead_fields' => ['name', 'email'],
            'is_active' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Website widget created.');

    $widget = ChatbotWidget::query()->firstOrFail();
    $embedCode = '<script src="'.route('widgets.chatbot.loader', $widget->public_token).'" async></script>';

    expect($widget)
        ->workspace_id->toBe($workspace->id)
        ->chatbot_id->toBe($chatbot->id)
        ->name->toBe('Storefront Chat')
        ->public_token->not->toBeEmpty()
        ->allowed_domains->toBe(['example.com', 'shop.example.com'])
        ->lead_fields->toBe(['name', 'email'])
        ->and($widget->setting('primary_color'))->toBe('#15803d')
        ->and($widget->automatedReplyEnabled())->toBeTrue();

    $this->withoutMiddleware(chatbotWidgetMiddleware())
        ->actingAs($user)
        ->get(route('user.chatbots.widgets.index'))
        ->assertOk()
        ->assertSee('Storefront Chat')
        ->assertDontSee('sk-widget-secret')
        ->assertSee(route('widgets.chatbot.loader', $widget->public_token), false)
        ->assertSee('data-copy="'.e($embedCode).'"', false);

    $this->withoutMiddleware(chatbotWidgetMiddleware())
        ->actingAs($user)
        ->put(route('user.chatbots.widgets.update', $widget), [
            'name' => 'Support Widget',
            'chatbot_id' => $chatbot->id,
            'greeting' => 'Support is here.',
            'allowed_domains' => 'example.com',
            'primary_color' => '#2563eb',
            'position' => 'left',
            'launcher_label' => 'Support',
            'automated_reply_enabled' => '0',
            'lead_fields' => ['phone'],
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.chatbots.widgets.edit', $widget))
        ->assertSessionHas('status', 'Website widget updated.');

    expect($widget->refresh())
        ->name->toBe('Support Widget')
        ->allowed_domains->toBe(['example.com'])
        ->lead_fields->toBe(['phone'])
        ->and($widget->setting('position'))->toBe('left')
        ->and($widget->automatedReplyEnabled())->toBeFalse();

    $this->withoutMiddleware(chatbotWidgetMiddleware())
        ->actingAs($user)
        ->delete(route('user.chatbots.widgets.destroy', $widget))
        ->assertRedirect(route('user.chatbots.widgets.index'))
        ->assertSessionHas('status', 'Website widget deleted.');

    expect(ChatbotWidget::query()->exists())->toBeFalse();
});

it('prevents users from managing another workspace widget', function (): void {
    [$owner, $workspace, , $chatbot] = chatbotWidgetContext();
    $other = User::factory()->create(['email_verified_at' => now()]);
    $widget = ChatbotWidget::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_id' => $chatbot->id,
        'name' => 'Private Widget',
        'public_token' => 'widget-private-token',
        'is_active' => true,
        'allowed_domains' => ['example.com'],
        'settings' => [],
    ]);

    $this->withoutMiddleware(chatbotWidgetMiddleware())
        ->actingAs($other)
        ->get(route('user.chatbots.widgets.edit', $widget))
        ->assertNotFound();
});

it('enforces active token and allowed domains while allowing localhost', function (): void {
    [$user, , , $chatbot] = chatbotWidgetContext();
    $workspace = app(WorkspaceResolver::class)->current($user);
    $widget = ChatbotWidget::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_id' => $chatbot->id,
        'name' => 'Domain Widget',
        'public_token' => 'domain-token',
        'is_active' => true,
        'allowed_domains' => ['example.com'],
        'lead_fields' => [],
        'settings' => ['primary_color' => '#16a34a', 'position' => 'right', 'launcher_label' => 'Chat'],
    ]);

    $this->withHeader('Origin', 'https://evil.test')
        ->getJson(route('widgets.chatbot.config', $widget->public_token))
        ->assertForbidden();

    $this->withHeader('Origin', 'https://example.com')
        ->getJson(route('widgets.chatbot.config', $widget->public_token))
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'https://example.com')
        ->assertJsonPath('name', 'Domain Widget')
        ->assertJsonMissingPath('credentials');

    $this->call('OPTIONS', url('widgets/chatbot/'.$widget->public_token.'/messages'), server: ['HTTP_ORIGIN' => 'https://example.com'])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://example.com');

    $this->withHeader('Origin', 'http://localhost:5173')
        ->getJson(route('widgets.chatbot.config', $widget->public_token))
        ->assertOk();

    $widget->update(['is_active' => false]);

    $this->withHeader('Origin', 'https://example.com')
        ->getJson(route('widgets.chatbot.config', $widget->public_token))
        ->assertNotFound();
});

it('stores visitor messages in inbox generates chatbot replies and exposes agent replies by polling', function (): void {
    [$user, $workspace, , $chatbot] = chatbotWidgetContext();

    Storage::fake('public');
    $widget = ChatbotWidget::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_id' => $chatbot->id,
        'name' => 'Public Widget',
        'public_token' => 'public-widget-token',
        'is_active' => true,
        'allowed_domains' => ['example.com'],
        'lead_fields' => ['name', 'email'],
        'settings' => ['primary_color' => '#16a34a', 'position' => 'right', 'launcher_label' => 'Chat'],
        'greeting' => 'Welcome.',
    ]);

    $sessionResponse = $this->withHeader('Origin', 'https://example.com')
        ->postJson(route('widgets.chatbot.sessions', $widget->public_token), [
            'visitor_uid' => 'visitor-123',
            'name' => 'Rina Visitor',
            'email' => 'rina@example.com',
            'page_url' => 'https://example.com/pricing',
        ])
        ->assertOk()
        ->assertJsonStructure(['session_token', 'visitor_uid', 'conversation_id']);

    $sessionToken = $sessionResponse->json('session_token');

    $this->withHeader('Origin', 'https://example.com')
        ->postJson(route('widgets.chatbot.messages', $widget->public_token), [
            'session_token' => $sessionToken,
            'message' => 'Can you help with pricing?',
        ])
        ->assertOk()
        ->assertJsonPath('message.direction', 'inbound')
        ->assertJsonPath('reply.direction', 'outbound')
        ->assertJsonPath('debug.provider', 'openai');

    $contact = Contact::query()->where('workspace_id', $workspace->id)->where('email', 'rina@example.com')->firstOrFail();
    $conversation = Conversation::query()->where('workspace_id', $workspace->id)->where('provider', 'website_widget')->firstOrFail();

    expect($contact->name)->toBe('Rina Visitor')
        ->and($conversation->contact_id)->toBe($contact->id)
        ->and(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'inbound')->where('body', 'Can you help with pricing?')->exists())->toBeTrue()
        ->and(Message::query()->where('conversation_id', $conversation->id)->where('direction', 'outbound')->where('body', 'Test reply generated from the saved chatbot configuration.')->exists())->toBeTrue();

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.can_reply', false)
        ->assertJsonPath('conversation.automated_reply_enabled', true)
        ->assertJsonPath('conversation.reply_disabled_reason', 'Automated chatbot replies are enabled for this widget. Turn them off before replying from Inbox.');

    expect(fn () => app(InboxService::class)->sendMessage($user, $conversation->id, 'An agent can help too.'))
        ->toThrow(ValidationException::class);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.automation', $conversation), [
            'automated_reply_enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('conversation.can_reply', true)
        ->assertJsonPath('conversation.automated_reply_enabled', false)
        ->assertJsonPath('conversation.reply_disabled_reason', null);

    expect($widget->refresh()->automatedReplyEnabled())->toBeFalse();

    $outboundCount = Message::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->count();

    $this->withHeader('Origin', 'https://example.com')
        ->postJson(route('widgets.chatbot.messages', $widget->public_token), [
            'session_token' => $sessionToken,
            'message' => 'Can a person reply?',
        ])
        ->assertOk()
        ->assertJsonPath('message.direction', 'inbound')
        ->assertJsonPath('reply', null);

    expect(Message::query()
        ->where('conversation_id', $conversation->id)
        ->where('direction', 'outbound')
        ->count())->toBe($outboundCount);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->getJson(route('user.inbox.conversations.show', $conversation))
        ->assertOk()
        ->assertJsonPath('conversation.can_reply', true)
        ->assertJsonPath('conversation.automated_reply_enabled', false)
        ->assertJsonPath('conversation.reply_disabled_reason', null);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.automation', $conversation), [
            'automated_reply_enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('conversation.can_reply', false)
        ->assertJsonPath('conversation.automated_reply_enabled', true);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.inbox.conversations.automation', $conversation), [
            'automated_reply_enabled' => false,
        ])
        ->assertOk()
        ->assertJsonPath('conversation.can_reply', true)
        ->assertJsonPath('conversation.automated_reply_enabled', false);

    app(InboxService::class)->sendMessage($user, $conversation->id, 'An agent can help too.');
    app(InboxService::class)->sendMessage($user, $conversation->id, 'Here is the file.', UploadedFile::fake()->image('agent-widget.jpg', 640, 480));

    $this->withHeader('Origin', 'https://example.com')
        ->getJson(route('widgets.chatbot.sessions.messages', [$widget->public_token, $sessionToken]))
        ->assertOk()
        ->assertJsonFragment(['body' => 'An agent can help too.'])
        ->assertJsonFragment(['name' => 'agent-widget.jpg']);
});

it('stores visitor widget image uploads and exposes attachment metadata by polling', function (): void {
    [, $workspace, , $chatbot] = chatbotWidgetContext();

    Storage::fake('public');
    $widget = ChatbotWidget::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_id' => $chatbot->id,
        'name' => 'Upload Widget',
        'public_token' => 'upload-widget-token',
        'is_active' => true,
        'allowed_domains' => ['example.com'],
        'lead_fields' => [],
        'settings' => ['primary_color' => '#16a34a', 'position' => 'right', 'launcher_label' => 'Chat'],
    ]);

    $sessionToken = $this->withHeader('Origin', 'https://example.com')
        ->postJson(route('widgets.chatbot.sessions', $widget->public_token), [
            'visitor_uid' => 'visitor-upload',
            'page_url' => 'https://example.com/support',
        ])
        ->assertOk()
        ->json('session_token');

    $this->withHeader('Origin', 'https://example.com')
        ->post(route('widgets.chatbot.messages', $widget->public_token), [
            'session_token' => $sessionToken,
            'attachment' => UploadedFile::fake()->image('visitor-upload.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('message.direction', 'inbound')
        ->assertJsonPath('message.type', 'image')
        ->assertJsonPath('message.body', 'visitor-upload.jpg')
        ->assertJsonPath('message.attachment.name', 'visitor-upload.jpg')
        ->assertJsonPath('reply', null);

    $message = Message::query()
        ->where('workspace_id', $workspace->id)
        ->where('provider', 'website_widget')
        ->where('type', 'image')
        ->firstOrFail();

    Storage::disk('public')->assertExists(data_get($message->payload, 'attachment.path'));

    $this->withHeader('Origin', 'https://example.com')
        ->getJson(route('widgets.chatbot.sessions.messages', [$widget->public_token, $sessionToken]))
        ->assertOk()
        ->assertJsonPath('messages.0.attachment.name', 'visitor-upload.jpg')
        ->assertJsonPath('messages.0.attachment.type', 'image');
});

it('renders widget loader attachment upload and preview hooks', function (): void {
    [, $workspace, , $chatbot] = chatbotWidgetContext();
    $widget = ChatbotWidget::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_id' => $chatbot->id,
        'name' => 'Loader Widget',
        'public_token' => 'loader-widget-token',
        'is_active' => true,
        'allowed_domains' => ['example.com'],
        'lead_fields' => [],
        'settings' => ['primary_color' => '#16a34a', 'position' => 'right', 'launcher_label' => 'Chat'],
    ]);

    $this->withHeader('Origin', 'https://example.com')
        ->get(route('widgets.chatbot.loader', $widget->public_token))
        ->assertOk()
        ->assertSee('wapro-file-input', false)
        ->assertSee('renderAttachment', false)
        ->assertSee('selectedAttachment', false);
});
