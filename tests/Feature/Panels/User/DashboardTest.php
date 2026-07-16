<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('redirects guests to login', function () {
    $this->get(route('user.dashboard'))
        ->assertRedirect(route('login'));
});

it('renders the dashboard for authenticated users', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceResolver::class)->current($user);

    // 1. Seed Plan
    $plan = Plan::query()->create([
        'name' => 'Business',
        'slug' => 'business',
        'description' => 'Business plan',
        'price' => 49,
        'interval' => 'month',
        'limits' => [
            'messages_per_month' => 100000,
            'whatsapp_numbers' => 5,
            'team_members' => 10,
            'ai_tokens' => 50000,
        ],
        'is_active' => true,
        'sort_order' => 10,
    ]);

    // 2. Seed Subscription
    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now()->subDays(5),
        'renews_at' => now()->addDays(25),
        'usage' => [
            'messages_per_month' => 42600,
            'ai_tokens' => 18000,
        ],
    ]);

    // 3. Seed messages for sent count (26 current period, 3 previous period)
    for ($i = 0; $i < 26; $i++) {
        DB::table('messages')->insert([
            'workspace_id' => $workspace->id,
            'direction' => 'outbound',
            'provider' => 'whatsapp',
            'type' => 'text',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);
    }
    for ($i = 0; $i < 3; $i++) {
        DB::table('messages')->insert([
            'workspace_id' => $workspace->id,
            'direction' => 'outbound',
            'provider' => 'whatsapp',
            'type' => 'text',
            'created_at' => now()->subDays(35),
            'updated_at' => now()->subDays(35),
        ]);
    }

    // 4. Seed received messages (39 current period, 6 previous period)
    for ($i = 0; $i < 39; $i++) {
        DB::table('messages')->insert([
            'workspace_id' => $workspace->id,
            'direction' => 'inbound',
            'provider' => 'whatsapp',
            'type' => 'text',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);
    }
    for ($i = 0; $i < 6; $i++) {
        DB::table('messages')->insert([
            'workspace_id' => $workspace->id,
            'direction' => 'inbound',
            'provider' => 'whatsapp',
            'type' => 'text',
            'created_at' => now()->subDays(35),
            'updated_at' => now()->subDays(35),
        ]);
    }

    // 5. Seed conversations (new: 25 current period, 3 previous period)
    for ($i = 0; $i < 25; $i++) {
        DB::table('conversations')->insert([
            'workspace_id' => $workspace->id,
            'provider' => 'whatsapp',
            'status' => 'open',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);
    }
    for ($i = 0; $i < 3; $i++) {
        DB::table('conversations')->insert([
            'workspace_id' => $workspace->id,
            'provider' => 'whatsapp',
            'status' => 'closed',
            'created_at' => now()->subDays(35),
            'updated_at' => now()->subDays(35),
        ]);
    }

    // 6. Seed contacts (total 84, new 9 this period)
    for ($i = 0; $i < 84; $i++) {
        DB::table('contacts')->insert([
            'workspace_id' => $workspace->id,
            'name' => 'Contact '.$i,
            'phone' => '123456789'.$i,
            'created_at' => $i < 9 ? now()->subDays(1) : now()->subDays(35),
            'updated_at' => now(),
        ]);
    }

    // 7. Seed campaigns (9 total, 3 scheduled)
    for ($i = 0; $i < 9; $i++) {
        DB::table('campaigns')->insert([
            'workspace_id' => $workspace->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Campaign '.$i,
            'status' => $i < 3 ? 'scheduled' : 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // 8. Seed active automations (3 active)
    for ($i = 0; $i < 3; $i++) {
        DB::table('automations')->insert([
            'workspace_id' => $workspace->id,
            'name' => 'Automation '.$i,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // 9. Seed AI runs (32 messages with chatbot payload)
    for ($i = 0; $i < 32; $i++) {
        DB::table('messages')->insert([
            'workspace_id' => $workspace->id,
            'direction' => 'outbound',
            'provider' => 'whatsapp',
            'type' => 'text',
            'payload' => json_encode(['chatbot_id' => 1]),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);
    }

    // 10. Seed recent conversations
    $contactsData = [
        ['name' => 'Aisha Rahman', 'status' => 'open', 'msg' => 'Thanks! Is the Ethiopia roast back in stock?'],
        ['name' => 'Theo Sullivan', 'status' => 'closed', 'msg' => 'Order shipped — thanks so much! 🎉'],
        ['name' => 'Nadia B.', 'status' => 'open', 'msg' => 'Do you offer a subscription bundle?'],
        ['name' => 'Diego R.', 'status' => 'pending', 'msg' => "Perfect, I'll reorder the House Blend."],
    ];

    foreach ($contactsData as $data) {
        $contactId = DB::table('contacts')->insertGetId([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
            'phone' => '987654321'.rand(0, 999),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $convId = DB::table('conversations')->insertGetId([
            'workspace_id' => $workspace->id,
            'contact_id' => $contactId,
            'provider' => 'whatsapp',
            'status' => $data['status'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('messages')->insert([
            'workspace_id' => $workspace->id,
            'conversation_id' => $convId,
            'contact_id' => $contactId,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => $data['msg'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $response = $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->get(route('user.dashboard'));

    $response->assertOk()
        ->assertViewIs('panels.user.dashboard')
        ->assertViewHas('stats')
        ->assertSee('Launch campaigns, manage conversations, and automate WhatsApp replies from one workspace.')
        ->assertSee('id="appSidebar"', false)
        ->assertSee('app-topbar', false)
        ->assertSee('data-page-help-banner', false)
        ->assertSee('Review workspace performance, channel health, recent conversations, and the actions that need attention.')
        ->assertSee('id="userPageHelp"', false)
        ->assertSee('Help for this page')
        ->assertDontSee('WhatsApp Cloud API Workspace')
        ->assertDontSee('WhatsApp Cloud API workspace')
        ->assertDontSee('SaaS')
        ->assertDontSee('Support</a>', false)
        ->assertSee("Here's how your WhatsApp workspace is performing.")
        ->assertSee('Messages sent')
        ->assertSee('26')
        ->assertSee('42.6k / 100k')
        ->assertSee('WhatsApp Business')
        ->assertSee('Connect WhatsApp')
        ->assertSee('0 / 5')
        ->assertSee('Current plan')
        ->assertSee('Recent conversations')
        ->assertSee('Quick actions');

    expect($response->viewData('charts'))->toHaveCount(3)
        ->sequence(
            fn ($chart) => $chart->toMatchArray(['Messages by day', 'bar', 'primary']),
            fn ($chart) => $chart->toMatchArray(['Conversations', 'line', 'deep']),
            fn ($chart) => $chart->toMatchArray(['AI tokens', 'bar', 'accent']),
        )
        ->and($response->viewData('chartLabels'))->toBeString()->not->toBeEmpty();
});

it('passes zeroed stats when there is no data', function () {
    $user = User::factory()->create();

    $response = $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->get(route('user.dashboard'));

    $stats = $response->viewData('stats');

    expect($stats['contacts'])->toBe(0)
        ->and($stats['campaigns'])->toBe(0)
        ->and($stats['open_conversations'])->toBe(0)
        ->and($stats['leads'])->toBe(0);
});

it('falls back to the user initial in the topbar when no avatar exists', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'avatar' => null,
    ]);

    $response = $this->withoutMiddleware([EnsureOnboardingComplete::class, EnsureTwoFactorAuthenticated::class])
        ->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertOk();

    expect($response->getContent())
        ->toMatch('/<span\s+class="[^"]*bg-deep[^"]*">\s*JA\s*<\/span>/');
});
