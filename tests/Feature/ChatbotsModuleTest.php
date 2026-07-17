<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotAiProvider;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use App\Modules\KnowledgeBases\Models\KnowledgeBaseChunk;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Enums\WorkspaceMemberRole;
use App\Modules\Workspaces\Enums\WorkspaceMemberStatus;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function chatbotTestMiddleware(): array
{
    return [Authorize::class, EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class];
}

function configureChatbotPlatformAi(): void
{
    config([
        'ai.default' => 'openai',
        'ai.providers.openai' => [
            'driver' => 'openai',
            'key' => 'sk-platform-test',
        ],
    ]);
}

function chatbotAiCreditPlan(int $workspaceId, int $credits, int $used = 0): Subscription
{
    $plan = Plan::query()->create([
        'name' => 'Chatbot AI Credits',
        'slug' => 'chatbot-ai-credits-'.$workspaceId.'-'.$credits.'-'.$used,
        'price' => 0,
        'interval' => 'month',
        'limits' => ['max_ai_credits' => $credits],
        'features' => [],
        'is_active' => true,
    ]);

    return Subscription::query()->updateOrCreate(
        ['workspace_id' => $workspaceId],
        ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'renews_at' => now()->addMonth(), 'usage' => ['max_ai_credits' => $used]]
    );
}

it('shows website widgets in the automation sidebar group', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Permission::findOrCreate('chatbots.manage', 'web');
    $user->givePermissionTo('chatbots.manage');

    $this->actingAs($user);

    $html = view('components.layouts.partials.user-sidebar')->render();

    expect($html)
        ->toContain('>Automation<')
        ->toContain(route('user.chatbots.widgets.index'))
        ->toContain('Website Widgets')
        ->not->toContain('ai-providers')
        ->not->toContain('AI Providers')
        ->not->toContain('user.chat-widget.index');
});

it('lets a user create update test and delete workspace ai providers', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->post(route('user.chatbots.ai-providers.store'), [
            'name' => 'Workspace OpenAI',
            'provider' => 'openai',
            'api_key' => 'sk-workspace-secret',
            'base_url' => 'https://api.openai.com/v1',
            'default_model' => 'gpt-4o',
            'temperature' => '0.4',
            'max_tokens' => '512',
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.chatbots.ai-providers.index'))
        ->assertSessionHas('status', 'AI provider saved.');

    $provider = ChatbotAiProvider::query()->firstOrFail();
    $rawCredentials = DB::table('chatbot_ai_providers')->where('id', $provider->id)->value('credentials');

    expect($provider)
        ->workspace_id->toBe(app(WorkspaceResolver::class)->current($user)->id)
        ->provider->toBe('openai')
        ->name->toBe('Workspace OpenAI')
        ->is_active->toBeTrue()
        ->and($provider->credential('api_key'))->toBe('sk-workspace-secret')
        ->and($rawCredentials)->not->toContain('sk-workspace-secret');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->get(route('user.chatbots.ai-providers.index'))
        ->assertOk()
        ->assertSee('Workspace OpenAI')
        ->assertDontSee('sk-workspace-secret');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->put(route('user.chatbots.ai-providers.update', $provider), [
            'name' => 'Workspace Anthropic',
            'provider' => 'anthropic',
            'temperature' => '0.3',
            'max_tokens' => '768',
            'is_active' => '1',
        ])
        ->assertRedirect(route('user.chatbots.ai-providers.index'))
        ->assertSessionHas('status', 'AI provider updated.');

    expect($provider->refresh())
        ->name->toBe('Workspace Anthropic')
        ->provider->toBe('anthropic')
        ->and($provider->setting('default_model'))->toBe('claude-sonnet-4-20250514')
        ->and($provider->credential('api_key'))->toBe('sk-workspace-secret');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->post(route('user.chatbots.ai-providers.test', $provider))
        ->assertRedirect()
        ->assertSessionHas('status', 'AI provider settings are ready.');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->delete(route('user.chatbots.ai-providers.destroy', $provider))
        ->assertRedirect(route('user.chatbots.ai-providers.index'))
        ->assertSessionHas('status', 'AI provider deleted.');

    expect(ChatbotAiProvider::query()->exists())->toBeFalse();
});

it('prevents users from accessing another workspace ai provider', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($owner);

    $provider = ChatbotAiProvider::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'openai',
        'name' => 'Owner provider',
        'credentials' => ['api_key' => 'sk-owner'],
        'settings' => ['default_model' => 'gpt-4o'],
        'is_active' => true,
    ]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($other)
        ->get(route('user.chatbots.ai-providers.edit', $provider))
        ->assertNotFound();
});

it('renders the ai persona generator on the new chatbot form', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->get(route('user.chatbots.create'))
        ->assertOk()
        ->assertSee('generate_persona_button', false)
        ->assertSee('ph-sparkle', false)
        ->assertSee(route('user.chatbots.persona.generate'), false);
});

it('generates chatbot persona instructions from platform ai without creating a chatbot', function (): void {
    configureChatbotPlatformAi();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $subscription = chatbotAiCreditPlan($workspace->id, 5);
    $provider = ChatbotAiProvider::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'openai',
        'name' => 'Workspace OpenAI',
        'credentials' => ['api_key' => 'sk-workspace'],
        'settings' => ['default_model' => 'gpt-4o'],
        'is_active' => true,
    ]);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Billing KB',
        'settings' => [],
    ]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->postJson(route('user.chatbots.persona.generate'), [
            'name' => 'Billing Assistant',
            'chatbot_ai_provider_id' => $provider->id,
            'knowledge_bases' => [$knowledgeBase->id],
            'greeting' => 'Hi, billing help is here.',
            'instruction' => 'Be warm and concise.',
        ])
        ->assertOk()
        ->assertJsonPath('provider', 'openai')
        ->assertJsonPath('persona', 'Billing Assistant is a helpful, concise customer support chatbot powered by Workspace OpenAI. Use these knowledge bases when answering: Billing KB. Start conversations warmly and align with this greeting: Hi, billing help is here. Follow this extra direction: Be warm and concise. Escalate to a human when the answer is uncertain, sensitive, or outside the available context.');

    expect(Chatbot::query()->exists())->toBeFalse()
        ->and((int) data_get($subscription->fresh()->usage, 'max_ai_credits'))->toBe(1);
});

it('rejects chatbot persona generation when platform ai credits are over', function (): void {
    configureChatbotPlatformAi();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $subscription = chatbotAiCreditPlan($workspace->id, 1, 1);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->postJson(route('user.chatbots.persona.generate'), [
            'name' => 'Support Bot',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan')
        ->assertJsonPath('errors.plan.0', 'Your platform AI credits are over. Please upgrade your plan or buy more credits.');

    expect(Chatbot::query()->exists())->toBeFalse()
        ->and((int) data_get($subscription->fresh()->usage, 'max_ai_credits'))->toBe(1);
});

it('rejects chatbot persona generation when platform ai is not configured', function (): void {
    config([
        'ai.default' => 'openai',
        'ai.providers.openai' => [
            'driver' => 'openai',
        ],
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->postJson(route('user.chatbots.persona.generate'), [
            'name' => 'Support Bot',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ai');
});

it('validates workspace-scoped inputs before generating chatbot persona instructions', function (): void {
    configureChatbotPlatformAi();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $otherWorkspace = app(WorkspaceResolver::class)->current($other);
    $otherProvider = ChatbotAiProvider::query()->create([
        'workspace_id' => $otherWorkspace->id,
        'provider' => 'openai',
        'name' => 'Other OpenAI',
        'credentials' => ['api_key' => 'sk-other'],
        'settings' => ['default_model' => 'gpt-4o'],
        'is_active' => true,
    ]);
    $otherKnowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other KB',
        'settings' => [],
    ]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($owner)
        ->postJson(route('user.chatbots.persona.generate'), [
            'chatbot_ai_provider_id' => $otherProvider->id,
            'knowledge_bases' => [$otherKnowledgeBase->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['chatbot_ai_provider_id', 'knowledge_bases.0']);
});

it('requires chatbots manage permission before generating chatbot persona instructions', function (): void {
    configureChatbotPlatformAi();
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($owner);
    $member = User::factory()->create(['email_verified_at' => now()]);
    $workspace->members()->attach($member->id, [
        'role' => WorkspaceMemberRole::Staff->value,
        'status' => WorkspaceMemberStatus::Active->value,
    ]);

    $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($member)
        ->postJson(route('user.chatbots.persona.generate'), [
            'name' => 'Support Bot',
        ])
        ->assertForbidden();
});

it('uses database chatbots and supports chatbot crud', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $provider = ChatbotAiProvider::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'openai',
        'name' => 'Workspace OpenAI',
        'credentials' => ['api_key' => 'sk-workspace'],
        'settings' => ['default_model' => 'gpt-4o'],
        'is_active' => true,
    ]);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Billing KB',
        'description' => 'Billing and invoices',
        'settings' => [],
        'chunks_count' => 0,
    ]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->get(route('user.chatbots.index'))
        ->assertOk()
        ->assertSee('No chatbots yet')
        ->assertDontSee('Sales Concierge')
        ->assertDontSee('Order Helper');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->post(route('user.chatbots.store'), [
            'chatbot_ai_provider_id' => $provider->id,
            'name' => 'Billing Assistant',
            'persona' => 'Answer billing questions clearly.',
            'greeting' => 'How can I help with billing?',
            'model' => 'gpt-4o',
            'temperature' => '0.2',
            'max_tokens' => '512',
            'fallback_only_knowledge_base' => '1',
            'confidence_threshold' => '0.7',
            'handoff_on_request' => '1',
            'handoff_on_unsure' => '1',
            'handoff_message' => 'I will connect you with billing.',
            'is_active' => '1',
            'knowledge_bases' => [$knowledgeBase->id],
        ])
        ->assertRedirect();

    $chatbot = Chatbot::query()->firstOrFail();

    expect($chatbot)
        ->workspace_id->toBe($workspace->id)
        ->chatbot_ai_provider_id->toBe($provider->id)
        ->name->toBe('Billing Assistant')
        ->is_active->toBeTrue()
        ->and($chatbot->knowledgeBases()->pluck('knowledge_bases.id')->all())->toBe([$knowledgeBase->id]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->get(route('user.chatbots.config', $chatbot))
        ->assertOk()
        ->assertSee('Billing KB')
        ->assertSee('0 chunks');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->get(route('user.chatbots.index'))
        ->assertOk()
        ->assertSee('Billing Assistant')
        ->assertDontSee('Sales Concierge');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->put(route('user.chatbots.update', $chatbot), [
            'chatbot_ai_provider_id' => $provider->id,
            'name' => 'Billing Bot',
            'persona' => 'Answer invoices and plan questions.',
            'greeting' => 'Billing help is here.',
            'model' => 'gpt-4o-mini',
            'temperature' => '0.3',
            'max_tokens' => '768',
            'confidence_threshold' => '0.75',
            'handoff_on_request' => '1',
            'handoff_message' => 'A teammate will help.',
            'knowledge_bases' => [$knowledgeBase->id],
        ])
        ->assertRedirect(route('user.chatbots.config', $chatbot))
        ->assertSessionHas('status', 'Chatbot updated.');

    expect($chatbot->refresh())
        ->name->toBe('Billing Bot')
        ->model->toBe('gpt-4o-mini')
        ->is_active->toBeFalse();

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->patch(route('user.chatbots.toggle', $chatbot))
        ->assertRedirect()
        ->assertSessionHas('status', 'Chatbot activated.');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->delete(route('user.chatbots.destroy', $chatbot))
        ->assertRedirect(route('user.chatbots.index'))
        ->assertSessionHas('status', 'Chatbot deleted.');

    expect(Chatbot::query()->exists())->toBeFalse();
});

it('prevents attaching another workspace ai provider to a chatbot', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $other = User::factory()->create(['email_verified_at' => now()]);
    $ownerWorkspace = app(WorkspaceResolver::class)->current($owner);

    $provider = ChatbotAiProvider::query()->create([
        'workspace_id' => $ownerWorkspace->id,
        'provider' => 'openai',
        'name' => 'Owner provider',
        'credentials' => ['api_key' => 'sk-owner'],
        'settings' => ['default_model' => 'gpt-4o'],
        'is_active' => true,
    ]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($other)
        ->from(route('user.chatbots.create'))
        ->post(route('user.chatbots.store'), [
            'chatbot_ai_provider_id' => $provider->id,
            'name' => 'Invalid Bot',
            'persona' => 'Use someone else provider.',
        ])
        ->assertRedirect(route('user.chatbots.create'))
        ->assertSessionHasErrors('chatbot_ai_provider_id');
});

it('tests chatbot responses through the selected workspace provider and fails cleanly when missing', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $provider = ChatbotAiProvider::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'openai',
        'name' => 'Workspace OpenAI',
        'credentials' => ['api_key' => 'sk-workspace'],
        'settings' => ['default_model' => 'gpt-4o'],
        'is_active' => true,
    ]);
    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_ai_provider_id' => $provider->id,
        'name' => 'Support Bot',
        'persona' => 'Answer support questions.',
        'model' => 'gpt-4o',
        'is_active' => true,
    ]);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Support KB',
        'settings' => [],
        'sources_count' => 1,
        'chunks_count' => 1,
    ]);
    $source = $knowledgeBase->sources()->create([
        'type' => 'text',
        'title' => 'Plans',
        'content' => 'Premium plans include priority onboarding and invoice exports.',
        'status' => 'ready',
        'metadata' => [],
    ]);
    KnowledgeBaseChunk::query()->create([
        'knowledge_base_id' => $knowledgeBase->id,
        'source_id' => $source->id,
        'content' => 'Premium plans include priority onboarding and invoice exports.',
        'token_count' => 8,
        'position' => 0,
        'metadata' => [],
    ]);
    $chatbot->knowledgeBases()->sync([$knowledgeBase->id]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->postJson(route('user.chatbots.test', $chatbot), ['message' => 'What premium plans include?'])
        ->assertOk()
        ->assertJsonPath('provider', 'openai')
        ->assertJsonPath('model', 'gpt-4o')
        ->assertJsonPath('handoff', false)
        ->assertJsonPath('context.knowledge_context_count', 1)
        ->assertJsonPath('context.search_mode', 'database_fallback')
        ->assertJsonPath('context.sources_used.0.title', 'Plans')
        ->assertJsonStructure(['reply', 'confidence', 'handoff', 'provider', 'model'])
        ->assertJsonFragment(['reply' => 'Test reply generated from knowledge context: Premium plans include priority onboarding and invoice exports.']);

    $chatbot->update(['chatbot_ai_provider_id' => null]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->postJson(route('user.chatbots.test', $chatbot), ['message' => 'Hello'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Connect an active workspace AI provider before testing this chatbot.');
});

it('chatbot test respects fallback only knowledge base when no relevant chunks exist', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $provider = ChatbotAiProvider::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'openai',
        'name' => 'Workspace OpenAI',
        'credentials' => ['api_key' => 'sk-workspace'],
        'settings' => ['default_model' => 'gpt-4o'],
        'is_active' => true,
    ]);
    $knowledgeBase = KnowledgeBase::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Shipping KB',
        'settings' => [],
    ]);
    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_ai_provider_id' => $provider->id,
        'name' => 'KB Only Bot',
        'persona' => 'Answer only from knowledge.',
        'model' => 'gpt-4o',
        'fallback_only_knowledge_base' => true,
        'handoff_rules' => ['message' => 'I could not find this in our knowledge base. A teammate will help.'],
        'is_active' => true,
    ]);
    $chatbot->knowledgeBases()->sync([$knowledgeBase->id]);

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->postJson(route('user.chatbots.test', $chatbot), ['message' => 'hi'])
        ->assertOk()
        ->assertJsonPath('handoff', false)
        ->assertJsonPath('context.knowledge_context_count', 0)
        ->assertJsonPath('reply', 'Hi, how can I help you?');

    $this->withoutMiddleware(chatbotTestMiddleware())
        ->actingAs($user)
        ->postJson(route('user.chatbots.test', $chatbot), ['message' => 'Do you support refunds?'])
        ->assertOk()
        ->assertJsonPath('handoff', true)
        ->assertJsonPath('context.search_mode', 'database_fallback')
        ->assertJsonPath('context.knowledge_context_count', 0)
        ->assertJsonPath('reply', 'I could not find this in our knowledge base. A teammate will help.');
});
