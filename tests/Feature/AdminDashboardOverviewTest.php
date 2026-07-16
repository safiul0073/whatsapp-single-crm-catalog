<?php

use App\Models\Admin;
use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Automations\Models\Automation;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Chatbots\Models\ChatbotWidget;
use App\Modules\Chatbots\Models\ChatbotWidgetSession;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\PlansSubscriptions\Models\Subscription;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('renders admin dashboard overview cards chart and unified activity', function (): void {
    $admin = Admin::factory()->create();
    $activeUser = User::factory()->create(['first_name' => 'Aisha', 'last_name' => 'Rahman', 'is_active' => true]);
    User::factory()->create(['is_active' => true]);
    User::factory()->create(['is_active' => false]);

    $workspace = Workspace::query()->create([
        'owner_id' => $activeUser->id,
        'name' => 'Acme Support',
        'slug' => 'acme-support',
    ]);

    Workspace::query()->create([
        'owner_id' => $activeUser->id,
        'name' => 'Retail Desk',
        'slug' => 'retail-desk',
    ]);

    $plan = Plan::query()->create([
        'name' => 'Business',
        'slug' => 'business',
        'price' => 49,
        'interval' => 'month',
        'limits' => [],
        'features' => [],
        'is_active' => true,
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now()->subDays(10),
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Trialing,
        'starts_at' => now()->subDays(2),
    ]);

    Subscription::query()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Cancelled,
        'starts_at' => now()->subMonth(),
    ]);

    Message::query()->create([
        'workspace_id' => $workspace->id,
        'direction' => 'outbound',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    Message::query()->create([
        'workspace_id' => $workspace->id,
        'direction' => 'inbound',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $oldMessage = Message::query()->create([
        'workspace_id' => $workspace->id,
        'direction' => 'outbound',
    ]);
    $oldMessage->forceFill([
        'created_at' => now()->subDays(35),
        'updated_at' => now()->subDays(35),
    ])->save();

    Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    Automation::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Welcome flow',
        'is_active' => true,
    ]);

    Automation::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Paused flow',
        'is_active' => false,
    ]);

    AuditLog::query()->create([
        'user_id' => $activeUser->id,
        'action' => 'updated',
        'auditable_type' => Workspace::class,
        'auditable_id' => $workspace->id,
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'user_type' => User::class,
        'user_id' => $activeUser->id,
        'gateway' => 'manual',
        'amount' => 49,
        'currency' => 'USD',
        'status' => 'completed',
        'paid_at' => now()->subDay(),
    ]);

    $whatsappChannel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'WhatsApp Main',
        'status' => ChannelAccountStatus::Connected,
        'connected_at' => now()->subHour(),
    ]);

    $telegramChannel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'telegram',
        'name' => 'Telegram Bot',
        'status' => ChannelAccountStatus::Connected,
        'connected_at' => now()->subHour(),
    ]);

    Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $whatsappChannel->id,
        'provider' => 'whatsapp',
        'direction' => 'inbound',
    ]);

    Message::query()->create([
        'workspace_id' => $workspace->id,
        'channel_account_id' => $telegramChannel->id,
        'provider' => 'telegram',
        'direction' => 'inbound',
    ]);

    Contact::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Nadia Contact',
        'phone' => '+15551234567',
    ]);

    $widgetConversation = Conversation::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'website_widget',
    ]);

    $chatbot = Chatbot::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Support Bot',
        'is_active' => true,
    ]);

    $widget = ChatbotWidget::query()->create([
        'workspace_id' => $workspace->id,
        'chatbot_id' => $chatbot->id,
        'name' => 'Website Widget',
        'public_token' => 'widget-token-001',
        'is_active' => true,
    ]);

    ChatbotWidgetSession::query()->create([
        'workspace_id' => $workspace->id,
        'widget_id' => $widget->id,
        'chatbot_id' => $chatbot->id,
        'conversation_id' => $widgetConversation->id,
        'session_token' => 'widget-session-token-001',
    ]);

    Message::query()->create([
        'workspace_id' => $workspace->id,
        'conversation_id' => $widgetConversation->id,
        'provider' => 'website_widget',
        'direction' => 'inbound',
    ]);

    MessageTemplate::query()->create([
        'workspace_id' => $workspace->id,
        'provider' => 'whatsapp',
        'name' => 'order_update',
        'language' => 'en_US',
        'category' => 'utility',
        'status' => MessageTemplateStatus::Approved,
    ]);

    Campaign::query()->create([
        'uuid' => (string) Str::uuid(),
        'workspace_id' => $workspace->id,
        'name' => 'July Promo',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Main Users')
        ->assertSee('Active Main Users')
        ->assertSee('Total Users')
        ->assertSee('Workspaces')
        ->assertSee('Active Subscriptions')
        ->assertSee('Messages (30d)')
        ->assertSee('Active Automations')
        ->assertSee('Connected Channels')
        ->assertSee('Total Widgets')
        ->assertSee('Total Templates')
        ->assertSee('Total Contacts')
        ->assertSee('Total Campaigns')
        ->assertSee('Platform Activity')
        ->assertSee('Revenue')
        ->assertSee('Plan Subscriptions')
        ->assertSee('Channel Usage')
        ->assertSee('Recent Payments')
        ->assertSee('Recent Channels')
        ->assertSee('Messages by Channel')
        ->assertSee('Widget Messages')
        ->assertSee('Daily')
        ->assertSee('Monthly')
        ->assertSee('Users')
        ->assertSee('Messages')
        ->assertSee('Conversations')
        ->assertSee('Business')
        ->assertSee('WhatsApp Main')
        ->assertSee('Telegram Bot')
        ->assertSee('Completed')
        ->assertSee('WhatsApp Main')
        ->assertSee('Connected')
        ->assertDontSee('Recent Activity')
        ->assertDontSee('Login Activity')
        ->assertDontSee('User Login Activity')
        ->assertDontSee('Recent Users')
        ->assertDontSee('User Distribution by Role')
        ->assertDontSee('System Information')
        ->assertDontSee('Sales Overview')
        ->assertDontSee('Daily Messages by Channel')
        ->assertDontSee('Monthly Messages by Channel')
        ->assertDontSee('Daily Widget Messages')
        ->assertDontSee('Monthly Widget Messages')
        ->assertDontSee('Sample');

    expect($response->getContent())
        ->toMatch('/Main Users.*?<h4[^>]*>1<\/h4>/s')
        ->toMatch('/Active Main Users.*?<h4[^>]*>1<\/h4>/s')
        ->toMatch('/Total Users.*?<h4[^>]*>3<\/h4>/s')
        ->toMatch('/Workspaces.*?<h4[^>]*>2<\/h4>/s')
        ->toMatch('/Active Subscriptions.*?<h4[^>]*>2<\/h4>/s')
        ->toMatch('/Messages \(30d\).*?<h4[^>]*>5<\/h4>/s')
        ->toMatch('/Active Automations.*?<h4[^>]*>1<\/h4>/s')
        ->toMatch('/Connected Channels.*?<h4[^>]*>1<\/h4>/s')
        ->toMatch('/Total Widgets.*?<h4[^>]*>1<\/h4>/s')
        ->toMatch('/Total Templates.*?<h4[^>]*>1<\/h4>/s')
        ->toMatch('/Total Contacts.*?<h4[^>]*>1<\/h4>/s')
        ->toMatch('/Total Campaigns.*?<h4[^>]*>1<\/h4>/s');
});
