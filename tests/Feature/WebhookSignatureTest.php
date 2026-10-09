<?php

use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Models\ChannelWebhookEvent;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MessageTemplates\Models\MessageTemplateSubmission;
use App\Modules\MetaSocial\Services\MetaSocialSettingsService;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $workspace = app(WorkspaceResolver::class)->current(User::factory()->create(['email_verified_at' => now()]));
    ChannelAccount::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'Shop', 'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'x'], 'provider_account_id' => 'waba-1', 'provider_phone_id' => 'phone-1', 'connected_at' => now()]);
});

function metaWebhookBody(): string
{
    return json_encode(['entry' => [['id' => 'waba-1', 'changes' => [['field' => 'messages', 'value' => [
        'metadata' => ['phone_number_id' => 'phone-1'],
        'messages' => [['from' => '8801711223344', 'id' => 'wamid.sig', 'type' => 'text', 'text' => ['body' => 'hi']]],
    ]]]]]]);
}

function postMetaWebhook($test, string $body, ?string $signature)
{
    $server = ['CONTENT_TYPE' => 'application/json'] + ($signature ? ['HTTP_X_HUB_SIGNATURE_256' => $signature] : []);

    return $test->call('POST', '/webhooks/channels/whatsapp', [], [], [], $server, $body);
}

function signed(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

it('accepts a correctly signed webhook in enforce mode', function () {
    config(['services.meta.webhook_signature' => 'enforce']);
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_secret' => 'app-secret']);
    $body = metaWebhookBody();

    postMetaWebhook($this, $body, signed($body, 'app-secret'))->assertOk();

    expect(ChannelWebhookEvent::query()->count())->toBe(1);
});

it('rejects wrong, missing and tampered signatures in enforce mode without storing anything', function (?string $signature) {
    config(['services.meta.webhook_signature' => 'enforce']);
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_secret' => 'app-secret']);
    $body = metaWebhookBody();

    postMetaWebhook($this, $body, $signature === 'tampered' ? signed($body.' ', 'app-secret') : $signature)->assertForbidden();

    expect(ChannelWebhookEvent::query()->count())->toBe(0);
})->with([[null], ['sha256=deadbeef'], ['tampered']]);

it('accepts a signature made with any saved Meta app secret', function () {
    config(['services.meta.webhook_signature' => 'enforce']);
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_secret' => 'whatsapp-secret']);
    app(MetaSocialSettingsService::class)->update(['meta_social_app_secret' => 'social-secret']);
    $body = metaWebhookBody();

    postMetaWebhook($this, $body, signed($body, 'social-secret'))->assertOk();
});

it('only logs failures in the default log mode and still processes the webhook', function () {
    config(['services.meta.webhook_signature' => 'log']);
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_secret' => 'app-secret']);
    Log::spy();

    postMetaWebhook($this, metaWebhookBody(), 'sha256=wrong')->assertOk();

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'not enforced'))->once();
    expect(ChannelWebhookEvent::query()->count())->toBe(1);
});

it('keeps working when no Meta app secret is saved', function () {
    config(['services.meta.webhook_signature' => 'enforce']);
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_secret' => '']);

    postMetaWebhook($this, metaWebhookBody(), null)->assertOk();

    expect(ChannelWebhookEvent::query()->count())->toBe(1);
});

it('skips the check entirely when switched off', function () {
    config(['services.meta.webhook_signature' => 'off']);
    app(WhatsAppSettingsService::class)->update(['whatsapp_meta_app_secret' => 'app-secret']);

    postMetaWebhook($this, metaWebhookBody(), 'sha256=wrong')->assertOk();
});

it('ignores template updates that name a different WhatsApp Business Account', function () {
    $account = ChannelAccount::query()->firstOrFail();
    $driver = app(WhatsAppCloudDriver::class);
    $template = MessageTemplate::query()->create(['workspace_id' => $account->workspace_id, 'provider' => 'whatsapp', 'name' => 'welcome', 'language' => 'en', 'category' => 'marketing', 'status' => 'pending', 'body' => 'x', 'components' => [['type' => 'BODY', 'text' => 'x']]]);
    $submission = MessageTemplateSubmission::query()->create(['workspace_id' => $account->workspace_id, 'message_template_id' => $template->id, 'channel_account_id' => $account->id, 'provider' => 'whatsapp', 'provider_account_id' => 'waba-1', 'whatsapp_template_id' => '9', 'status' => 'submitted']);
    $forged = ['entry' => [['id' => 'someone-elses-waba', 'changes' => [['field' => 'message_template_status_update', 'value' => ['event' => 'APPROVED', 'message_template_id' => 9, 'message_template_name' => 'welcome', 'message_template_language' => 'en']]]]]];

    $driver->processWebhook($account, $forged);

    expect($submission->fresh()->status->value)->toBe('submitted');
});
