<?php

use App\Models\User;
use App\Modules\Automations\Jobs\RunAutomationStepJob;
use App\Modules\Automations\Models\Automation;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Automations\Models\AutomationStepLog;
use App\Modules\Automations\Services\AutomationDispatcher;
use App\Modules\Automations\Services\AutomationRunner;
use App\Modules\Automations\Services\AutomationService;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function activateAutomationAiPlan(User $user, bool $enabled = true): Workspace
{
    $workspace = app(WorkspaceResolver::class)->current($user);
    $plan = Plan::query()->create([
        'name' => $enabled ? 'Automation AI Premium' : 'Automation Free',
        'slug' => $enabled ? 'automation-ai-premium' : 'automation-free',
        'description' => 'Test plan',
        'price' => $enabled ? 29 : 0,
        'interval' => 'month',
        'limits' => [
            'automation_ai_builder' => $enabled,
        ],
        'features' => $enabled ? ['AI automation builder'] : ['Basic automation builder'],
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'renews_at' => now()->addMonth(),
        'usage' => [],
    ]);

    return $workspace;
}

function automationTestNode(string $id, string $type, string $kind, array $data = []): array
{
    $ports = match ($type) {
        'trigger' => [
            ['id' => 'default', 'direction' => 'output'],
        ],
        'condition' => [
            ['id' => 'in', 'direction' => 'input'],
            ['id' => 'true', 'direction' => 'output'],
            ['id' => 'false', 'direction' => 'output'],
        ],
        'end' => [
            ['id' => 'in', 'direction' => 'input'],
        ],
        default => [
            ['id' => 'in', 'direction' => 'input'],
            ['id' => 'default', 'direction' => 'output'],
        ],
    };

    return compact('id', 'type', 'kind', 'data', 'ports') + ['label' => str($kind)->headline()->toString()];
}

function automationTestEdge(string $source, string $sourcePort, string $target): array
{
    return [
        'id' => "{$source}-{$sourcePort}-{$target}",
        'sourceNodeId' => $source,
        'sourcePortId' => $sourcePort,
        'targetNodeId' => $target,
        'targetPortId' => 'in',
    ];
}

function registerAutomationTestWhatsappDriver(): void
{
    app(ChannelManager::class)->register(new class implements MarketingChannelDriver
    {
        public function provider(): string
        {
            return 'whatsapp';
        }

        public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
        {
            return [
                'ok' => true,
                'provider' => 'whatsapp',
                'provider_message_id' => 'wamid.test.'.md5(json_encode([$recipient, $payload])),
                'status' => MessageStatus::Sent->value,
                'response' => ['messages' => [['id' => 'wamid.test']]],
            ];
        }

        public function verifyWebhook(Request $request, ChannelAccount $account): bool
        {
            return true;
        }

        public function handleWebhook(Request $request, ChannelAccount $account): array
        {
            return [];
        }

        public function syncTemplates(ChannelAccount $account): array
        {
            return ['ok' => true];
        }

        public function getHealthStatus(ChannelAccount $account): array
        {
            return ['connected' => true];
        }

        public function testConnection(ChannelAccount $account): array
        {
            return ['ok' => true];
        }

        public function processWebhook(ChannelAccount $account, array $payload): array
        {
            return [];
        }

        public function getCapabilities(): array
        {
            return ['Inbox', 'Campaigns', 'Templates'];
        }

        public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
        {
            //
        }
    });
}

it('opens a new automation builder with an empty flow', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->withViewErrors([])
        ->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.automations.create'))
        ->assertOk()
        ->assertViewHas('flow', [
            'nodes' => [],
            'edges' => [],
        ])
        ->assertSee('Start a new WhatsApp flow')
        ->assertSee('Zoom with Ctrl/Cmd + wheel')
        ->assertDontSee('New contact added');
});

it('shows ai generation on the automations list', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    activateAutomationAiPlan($user);

    $this->withViewErrors([])
        ->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.automations.index'))
        ->assertOk()
        ->assertSee('Generate an automation')
        ->assertSee('Write your prompt with')
        ->assertSee('Example prompt')
        ->assertSee('Generate with AI');
});

it('shows premium upgrade action when automation ai is not included', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    activateAutomationAiPlan($user, false);

    $this->withViewErrors([])
        ->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.automations.index'))
        ->assertOk()
        ->assertSee('Upgrade for AI')
        ->assertDontSee('Generate an automation');
});

it('opens the builder with an ai generated draft from a prompt', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    activateAutomationAiPlan($user);

    $this->withViewErrors([])
        ->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.automations.create', [
            'ai_prompt' => 'Welcome new pricing leads, ask a question, show quick reply buttons, wait one day, and assign interested leads to sales.',
        ]))
        ->assertOk()
        ->assertViewHas('aiDraft')
        ->assertViewHas('flow', function (array $flow): bool {
            return count($flow['nodes']) >= 4
                && collect($flow['nodes'])->contains(fn (array $node): bool => $node['kind'] === 'reply_matches')
                && collect($flow['nodes'])->contains(fn (array $node): bool => $node['kind'] === 'wait_duration');
        })
        ->assertSee('Generate an automation flow')
        ->assertSee('Welcome New Pricing Leads');
});

it('generates an automation flow as json', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    activateAutomationAiPlan($user);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.automations.generate'), [
            'prompt' => 'Create a WhatsApp flow for new leads with yes or no quick replies and assign yes replies to a sales agent.',
        ])
        ->assertOk()
        ->assertJsonPath('source', 'guided')
        ->assertJsonStructure([
            'name',
            'description',
            'flow' => [
                'nodes',
                'edges',
            ],
            'summary',
            'source',
        ])
        ->assertJson(fn ($json) => $json
            ->whereType('flow.nodes.0.id', 'string')
            ->etc()
        );
});

it('blocks automation ai generation for non premium plans', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    activateAutomationAiPlan($user, false);

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.automations.generate'), [
            'prompt' => 'Create a WhatsApp lead qualification flow with quick replies and sales handoff.',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'AI automation generation is available on premium plans. Upgrade to use this feature.');
});

it('redirects non premium users away from ai builder drafts', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    activateAutomationAiPlan($user, false);

    $this->withViewErrors([])
        ->withoutMiddleware()
        ->actingAs($user)
        ->get(route('user.automations.create', [
            'ai_prompt' => 'Build a WhatsApp automation that welcomes new leads and asks a qualifying question.',
        ]))
        ->assertRedirect(route('user.automations.index'))
        ->assertSessionHas('status', 'AI automation generation is available on premium plans. Upgrade to use this feature.');
});

it('builds a blank automation flow without a default node', function (): void {
    expect(app(AutomationService::class)->blankFlow())->toBe([
        'nodes' => [],
        'edges' => [],
    ]);
});

it('saves an active automation with the five block schema', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $nodes = [
        automationTestNode('trigger-1', 'trigger', 'message_received', ['event' => 'message_received']),
        automationTestNode('action-1', 'action', 'send_whatsapp_message', ['body' => 'Thanks for messaging us.']),
    ];
    $edges = [
        automationTestEdge('trigger-1', 'default', 'action-1'),
    ];

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.automations.store'), [
            'name' => 'Inbound welcome',
            'description' => 'Reply to inbound messages.',
            'nodes' => json_encode($nodes),
            'edges' => json_encode($edges),
            'activate' => '1',
        ])
        ->assertRedirect(route('user.automations.index'))
        ->assertSessionHasNoErrors();

    $automation = Automation::query()->firstOrFail();

    expect($automation->is_active)->toBeTrue()
        ->and($automation->trigger['kind'])->toBe('message_received')
        ->and($automation->nodes)->toHaveCount(2);
});

it('normalizes missing false ports on condition nodes before saving', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $nodes = [
        [
            'id' => 'trigger-1',
            'type' => 'trigger',
            'kind' => 'message_received',
            'label' => 'Message received',
            'data' => ['event' => 'message_received'],
            'ports' => [
                ['id' => 'default', 'direction' => 'output'],
            ],
        ],
        [
            'id' => 'condition-1',
            'type' => 'condition',
            'kind' => 'message_contains',
            'label' => 'Message contains pricing',
            'data' => ['operator' => 'contains', 'value' => 'pricing'],
            'ports' => [
                ['id' => 'input', 'direction' => 'input'],
                ['id' => 'true', 'direction' => 'output'],
            ],
        ],
        [
            'id' => 'action-1',
            'type' => 'action',
            'kind' => 'send_whatsapp_message',
            'label' => 'Send reply',
            'data' => ['body' => 'Thanks for asking about pricing.'],
            'ports' => [
                ['id' => 'input', 'direction' => 'input'],
                ['id' => 'default', 'direction' => 'output'],
            ],
        ],
    ];
    $edges = [
        ['sourceNodeId' => 'trigger-1', 'sourcePortId' => 'default', 'targetNodeId' => 'condition-1', 'targetPortId' => 'input'],
        ['sourceNodeId' => 'condition-1', 'sourcePortId' => 'true', 'targetNodeId' => 'action-1', 'targetPortId' => 'input'],
    ];

    $this->withoutMiddleware()
        ->actingAs($user)
        ->post(route('user.automations.store'), [
            'name' => 'Pricing branch',
            'description' => 'Reply when pricing is mentioned.',
            'nodes' => json_encode($nodes),
            'edges' => json_encode($edges),
            'activate' => '1',
        ])
        ->assertRedirect(route('user.automations.index'))
        ->assertSessionHasNoErrors();

    $condition = collect(Automation::query()->firstOrFail()->nodes)
        ->firstWhere('id', 'condition-1');

    expect(collect($condition['ports'])->pluck('direction', 'id')->all())
        ->toHaveKey('false', 'output');
});

it('rejects chatbot reply nodes that point to another workspace chatbot', function (): void {
    $owner = User::factory()->create(['email_verified_at' => now()]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $ownerWorkspace = app(WorkspaceResolver::class)->current($owner);
    $chatbot = Chatbot::query()->create([
        'workspace_id' => $ownerWorkspace->id,
        'name' => 'Owner Bot',
        'persona' => 'Private workspace bot.',
        'is_active' => true,
    ]);
    $nodes = [
        automationTestNode('trigger-1', 'trigger', 'message_received', ['event' => 'message_received']),
        automationTestNode('action-1', 'action', 'generate_chatbot_reply', ['chatbot_id' => $chatbot->id]),
    ];
    $edges = [
        automationTestEdge('trigger-1', 'default', 'action-1'),
    ];

    $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.automations.create'))
        ->post(route('user.automations.store'), [
            'name' => 'Cross workspace chatbot',
            'nodes' => json_encode($nodes),
            'edges' => json_encode($edges),
            'activate' => '1',
        ])
        ->assertRedirect(route('user.automations.create'))
        ->assertSessionHasErrors(['nodes']);
});

it('rejects invalid five block node configuration', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $nodes = [
        automationTestNode('trigger-1', 'trigger', 'keyword_matched', []),
        automationTestNode('condition-1', 'condition', 'message_contains', ['field' => 'message_body', 'value' => 'price']),
        automationTestNode('action-1', 'action', 'send_whatsapp_message', []),
    ];
    $edges = [
        automationTestEdge('trigger-1', 'default', 'condition-1'),
        automationTestEdge('condition-1', 'true', 'action-1'),
    ];

    $this->withoutMiddleware()
        ->actingAs($user)
        ->from(route('user.automations.create'))
        ->post(route('user.automations.store'), [
            'name' => 'Broken automation',
            'nodes' => json_encode($nodes),
            'edges' => json_encode($edges),
            'activate' => '1',
        ])
        ->assertRedirect(route('user.automations.create'))
        ->assertSessionHasErrors(['nodes']);
});

it('dry runs the current automation canvas and reports completed chatbot steps without sending messages', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Automation Test Bot',
        'persona' => 'Answer test questions.',
        'is_active' => true,
    ]);
    $nodes = [
        automationTestNode('trigger-1', 'trigger', 'message_received', ['event' => 'message_received']),
        automationTestNode('condition-1', 'condition', 'message_contains', [
            'field' => 'message_body',
            'operator' => 'contains',
            'value' => 'pricing',
        ]),
        automationTestNode('action-1', 'action', 'generate_chatbot_reply', ['chatbot_id' => $chatbot->id]),
        automationTestNode('end-1', 'end', 'customer_became_lead', ['reason' => 'Finished test path']),
    ];
    $edges = [
        automationTestEdge('trigger-1', 'default', 'condition-1'),
        automationTestEdge('condition-1', 'true', 'action-1'),
        automationTestEdge('action-1', 'default', 'end-1'),
    ];

    $this->withoutMiddleware()
        ->actingAs($user)
        ->postJson(route('user.automations.test-flow'), [
            'nodes' => $nodes,
            'edges' => $edges,
            'message' => 'I need pricing details',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('completed', true)
        ->assertJsonPath('steps.1.port', 'true')
        ->assertJsonPath('steps.2.output.chatbot_id', $chatbot->id)
        ->assertJsonPath('steps.2.output.provider', 'openai')
        ->assertJsonPath('steps.3.summary', 'Finished test path');

    expect(Message::query()->exists())->toBeFalse()
        ->and(AutomationRun::query()->exists())->toBeFalse()
        ->and(AutomationStepLog::query()->exists())->toBeFalse();
});

it('uses a selected chatbot to answer inbound inbox messages from automation', function (): void {
    config(['queue.default' => 'sync']);
    registerAutomationTestWhatsappDriver();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Support Bot',
        'persona' => 'Answer support questions.',
        'is_active' => true,
    ]);
    $account = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'WhatsApp Test',
        'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'token'],
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-1',
        'connected_at' => now(),
    ]);
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Safi',
        'phone' => '+8801711111111',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $account->id,
        'provider' => 'whatsapp',
        'contact_id' => $contact->id,
        'status' => ConversationStatus::Open,
    ]);
    $inbound = Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $account->id,
        'provider' => 'whatsapp',
        'conversation_id' => $conversation->id,
        'contact_id' => $contact->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'Can you help me?',
        'status' => MessageStatus::Received,
    ]);
    $automation = Automation::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Chatbot inbox reply',
        'trigger' => ['id' => 'trigger-1', 'type' => 'trigger', 'kind' => 'message_received'],
        'nodes' => [
            automationTestNode('trigger-1', 'trigger', 'message_received', ['event' => 'message_received']),
            automationTestNode('action-1', 'action', 'generate_chatbot_reply', ['chatbot_id' => $chatbot->id]),
        ],
        'edges' => [
            automationTestEdge('trigger-1', 'default', 'action-1'),
        ],
        'is_active' => true,
    ]);

    app(AutomationDispatcher::class)->dispatch([
        'type' => 'message_received',
        'workspace_id' => $workspace->id,
        'channel_account_id' => $account->id,
        'provider' => 'whatsapp',
        'contact_id' => $contact->id,
        'conversation_id' => $conversation->id,
        'message_id' => $inbound->id,
        'body' => $inbound->body,
        'event_key' => 'message:chatbot:'.$inbound->id,
    ]);

    $run = AutomationRun::query()->where('automation_id', $automation->id)->firstOrFail();
    $reply = Message::query()->where('direction', 'outbound')->firstOrFail();
    $log = AutomationStepLog::query()
        ->where('automation_run_id', $run->id)
        ->where('node_id', 'action-1')
        ->firstOrFail();

    expect($run->refresh()->status)->toBe('completed')
        ->and($reply->body)->toBe('Test reply generated from the saved chatbot configuration.')
        ->and(data_get($reply->payload, 'chatbot_id'))->toBe($chatbot->id)
        ->and(data_get($log->output, 'chatbot_id'))->toBe($chatbot->id)
        ->and(data_get($log->output, 'provider'))->toBe('openai')
        ->and(data_get($log->output, 'knowledge_context_count'))->toBe(0);
});

it('executes message received condition and send message blocks', function (): void {
    config(['queue.default' => 'sync']);
    registerAutomationTestWhatsappDriver();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $account = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'WhatsApp Test',
        'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'token'],
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-1',
        'connected_at' => now(),
    ]);
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Safi',
        'phone' => '+8801711111111',
        'city' => 'Dhaka',
    ]);
    $conversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $account->id,
        'provider' => 'whatsapp',
        'contact_id' => $contact->id,
        'status' => ConversationStatus::Open,
    ]);
    $inbound = Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $account->id,
        'provider' => 'whatsapp',
        'conversation_id' => $conversation->id,
        'contact_id' => $contact->id,
        'direction' => 'inbound',
        'type' => 'text',
        'body' => 'Can I get the price?',
        'status' => MessageStatus::Received,
    ]);
    $automation = Automation::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Price reply',
        'trigger' => ['id' => 'trigger-1', 'type' => 'trigger', 'kind' => 'message_received'],
        'nodes' => [
            automationTestNode('trigger-1', 'trigger', 'message_received', ['event' => 'message_received']),
            automationTestNode('condition-1', 'condition', 'message_contains', [
                'field' => 'message_body',
                'operator' => 'contains',
                'value' => 'price',
            ]),
            automationTestNode('action-1', 'action', 'send_whatsapp_message', ['body' => 'Our price starts at 999.']),
        ],
        'edges' => [
            automationTestEdge('trigger-1', 'default', 'condition-1'),
            automationTestEdge('condition-1', 'true', 'action-1'),
        ],
        'is_active' => true,
    ]);

    app(AutomationDispatcher::class)->dispatch([
        'type' => 'message_received',
        'workspace_id' => $workspace->id,
        'channel_account_id' => $account->id,
        'contact_id' => $contact->id,
        'conversation_id' => $conversation->id,
        'message_id' => $inbound->id,
        'body' => $inbound->body,
        'event_key' => 'message:'.$inbound->id,
    ]);

    $run = AutomationRun::query()->where('automation_id', $automation->id)->firstOrFail();

    expect($run->refresh()->status)->toBe('completed')
        ->and(AutomationStepLog::query()->where('automation_run_id', $run->id)->count())->toBe(2)
        ->and(Message::query()->where('direction', 'outbound')->where('body', 'Our price starts at 999.')->exists())->toBeTrue();
});

it('dispatches keyword matched automations to approved templates once per event', function (): void {
    config(['queue.default' => 'sync']);
    registerAutomationTestWhatsappDriver();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $account = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'WhatsApp Test',
        'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'token'],
        'provider_account_id' => 'waba-1',
        'provider_phone_id' => 'phone-1',
        'connected_at' => now(),
    ]);
    $contact = Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Safi',
        'phone' => '+8801711111111',
    ]);
    $template = MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'price_update',
        'language' => 'en_US',
        'category' => 'marketing',
        'status' => MessageTemplateStatus::Approved,
        'components' => [],
    ]);
    $automation = Automation::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Keyword template',
        'trigger' => ['id' => 'trigger-1', 'type' => 'trigger', 'kind' => 'keyword_matched'],
        'nodes' => [
            automationTestNode('trigger-1', 'trigger', 'keyword_matched', ['keyword' => 'price']),
            automationTestNode('action-1', 'action', 'send_approved_template', ['template_id' => $template->id]),
        ],
        'edges' => [
            automationTestEdge('trigger-1', 'default', 'action-1'),
        ],
        'is_active' => true,
    ]);
    $event = [
        'type' => 'message_received',
        'workspace_id' => $workspace->id,
        'channel_account_id' => $account->id,
        'contact_id' => $contact->id,
        'body' => 'price please',
        'event_key' => 'keyword-event-1',
    ];

    app(AutomationDispatcher::class)->dispatch($event);
    app(AutomationDispatcher::class)->dispatch($event);

    expect(AutomationRun::query()->where('automation_id', $automation->id)->count())->toBe(1)
        ->and(Message::query()->where('direction', 'outbound')->where('type', 'template')->count())->toBe(1);
});

it('schedules delay blocks without completing the run immediately', function (): void {
    Queue::fake();

    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $automation = Automation::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Delay follow up',
        'trigger' => ['id' => 'trigger-1', 'type' => 'trigger', 'kind' => 'message_received'],
        'nodes' => [
            automationTestNode('trigger-1', 'trigger', 'message_received', ['event' => 'message_received']),
            automationTestNode('delay-1', 'delay', 'wait_duration', ['value' => 5, 'unit' => 'minutes']),
            automationTestNode('action-1', 'action', 'notify_admin', ['message' => 'Follow up with this contact.']),
        ],
        'edges' => [
            automationTestEdge('trigger-1', 'default', 'delay-1'),
            automationTestEdge('delay-1', 'default', 'action-1'),
        ],
        'is_active' => true,
    ]);
    $run = AutomationRun::query()->create([
        'workspace_id' => $workspace->id,
        'automation_id' => $automation->id,
        'status' => 'running',
        'trigger_type' => 'message_received',
        'started_at' => now(),
        'context' => [],
    ]);

    (new RunAutomationStepJob($automation->id, ['workspace_id' => $workspace->id], $run->id, 'delay-1'))
        ->handle(app(AutomationDispatcher::class), app(AutomationRunner::class));

    Queue::assertPushed(RunAutomationStepJob::class);

    expect($run->refresh()->status)->toBe('running')
        ->and(AutomationStepLog::query()->where('automation_run_id', $run->id)->where('status', 'scheduled')->exists())->toBeTrue();
});

it('exposes the five flow builder block groups in the frontend palette', function (): void {
    $script = file_get_contents(resource_path('js/components/automation-builder.js'));
    $view = file_get_contents(base_path('app/Modules/Automations/Resources/views/user/builder.blade.php'));
    $indexView = file_get_contents(base_path('app/Modules/Automations/Resources/views/user/index.blade.php'));
    $helpDrawer = file_get_contents(base_path('app/Modules/Automations/Resources/views/user/partials/flow-help-drawer.blade.php'));
    $dashboardStyles = file_get_contents(resource_path('css/components/classic.css'));

    expect($script)->toContain('Trigger')
        ->and($script)->toContain('Condition')
        ->and($script)->toContain('Action')
        ->and($script)->toContain('Delay')
        ->and($script)->toContain('Exit / Goal')
        ->and($script)->toContain('message_received')
        ->and($script)->toContain('send_approved_template')
        ->and($script)->toContain('generate_chatbot_reply')
        ->and($script)->toContain('Ask chatbot')
        ->and($script)->toContain('direction: "output", y: 61, tone: "success"')
        ->and($script)->toContain('id: "default", label: "Next", direction: "output", y: 61')
        ->and($script)->toContain('const handle = Math.min(160, Math.max(72')
        ->and($script)->not->toContain(' L ${targetLaneX}')
        ->and($script)->not->toContain('direction: "output", y: 161, tone: "error", branch: true')
        ->and($script)->toContain('this.findPort(source, requestedSourcePortId)?.id || this.firstOutputPort(source)?.id')
        ->and($script)->toContain('edgeToneClass(edge)')
        ->and($script)->toContain('flow-edge__signal')
        ->and($script)->toContain('undoFlow()')
        ->and($script)->toContain('fitToNodes()')
        ->and($script)->toContain('minimapNodeStyle')
        ->and($script)->toContain('focusInspector(id)')
        ->and($script)->toContain('selectNode(id)')
        ->and($script)->toContain('this.inspectorOpen = true;')
        ->and($script)->toContain('minimizeInspector()')
        ->and($script)->toContain('expandInspector()')
        ->and($script)->not->toContain('editingNodeId')
        ->and($script)->not->toContain('toggleFavorite(node)')
        ->and($script)->toContain('testFlow()')
        ->and($script)->toContain('testResult')
        ->and($view)->toContain('is-palette-collapsed')
        ->and($view)->toContain('is-palette-open')
        ->and($view)->toContain('flow-port--start')
        ->and($view)->toContain('Flow starts here')
        ->and($view)->toContain('node.type === \'condition\'')
        ->and($view)->toContain('x-show="selectedNode && inspectorOpen"')
        ->and($view)->toContain('@click="minimizeInspector()"')
        ->and($view)->toContain('@click="expandInspector()"')
        ->and($view)->toContain("'is-palette-open lg:grid-cols-[16rem_1fr]'")
        ->and($view)->not->toContain('lg:grid-cols-[16rem_1fr_17rem]')
        ->and($view)->not->toContain('aria-labelledby="nodeEditorTitle"')
        ->and($view)->not->toContain('aria-label="Favorite node"')
        ->and($view)->toContain('data-drawer-trigger="automationFlowHelpDrawer"')
        ->and($indexView)->toContain('data-drawer-trigger="automationFlowHelpDrawer"')
        ->and($helpDrawer)->toContain('Build a flow in minutes')
        ->and($helpDrawer)->toContain('Drag from the right dot of one node to the left dot of the next node.')
        ->and($view)->toContain('lg:grid-cols-[16rem_1fr]')
        ->and($dashboardStyles)->toContain('.flow-builder-shell.is-palette-open .flow-palette-toggle')
        ->and($dashboardStyles)->toContain('.flow-inspector')
        ->and($dashboardStyles)->toContain('.flow-inspector.is-minimized')
        ->and($dashboardStyles)->toContain('.flow-port--start')
        ->and($dashboardStyles)->toContain('.flow-port--input::before')
        ->and($dashboardStyles)->toContain('@keyframes flow-edge-signal')
        ->and($dashboardStyles)->toContain('.flow-canvas-controls');
});
