<?php

use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsureSubscriptionUsable;
use App\Http\Middleware\EnsureTwoFactorAuthenticated;
use App\Models\User;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Http\Requests\StoreMessageTemplateRequest;
use App\Modules\MessageTemplates\Jobs\SyncWhatsAppTemplatesJob;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\MessageTemplates\Models\MessageTemplateSubmission;
use App\Modules\MessageTemplates\Services\MessageTemplateService;
use App\Modules\SchedulerQueue\Services\SchedulerRegistry;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;
use App\Modules\WhatsAppCloud\Services\WhatsAppSettingsService;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function withoutGuards($test)
{
    return $test->withoutMiddleware([EnsureOnboardingComplete::class, EnsureSubscriptionUsable::class, EnsureTwoFactorAuthenticated::class, Authorize::class]);
}

function templateOwner(): array
{
    app(WhatsAppSettingsService::class)->update(['whatsapp_graph_api_version' => 'v20.0']);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceResolver::class)->current($user);
    $channel = ChannelAccount::query()->create([
        'workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'Shop', 'status' => ChannelAccountStatus::Connected,
        'credentials' => ['access_token' => 'EAA-test'], 'provider_account_id' => 'waba-1', 'provider_phone_id' => 'phone-1', 'connected_at' => now(),
    ]);

    return [$user, $workspace, $channel];
}

function authenticationInput(array $overrides = []): array
{
    return array_merge(['provider' => 'whatsapp', 'name' => 'customer_login_code', 'language' => 'en', 'category' => 'authentication',
        'security_recommendation' => true, 'code_expiration_minutes' => 5, 'otp_button_text' => 'Copy code'], $overrides);
}

it('builds authentication templates the way Meta requires', function () {
    [$user] = templateOwner();

    $template = app(MessageTemplateService::class)->store($user, authenticationInput());

    expect($template->components)->toEqual([
        ['type' => 'BODY', 'add_security_recommendation' => true],
        ['type' => 'FOOTER', 'code_expiration_minutes' => 5],
        ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE', 'text' => 'Copy code']]],
    ])->and($template->body)->toBe('{{1}} is your verification code. For your security, do not share this code. This code expires in 5 minutes.');
});

it('resubmits an old authentication template that stored free text in the body', function () {
    [$user, $workspace] = templateOwner();
    Http::fake(['https://graph.facebook.com/v20.0/waba-1/message_templates' => Http::response(['id' => 'meta-1'])]);
    $template = MessageTemplate::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'customer_login_code', 'language' => 'en', 'category' => 'authentication', 'status' => 'failed',
        'body' => 'Your verification code is {{verification_code}}.', 'components' => [['type' => 'BODY', 'text' => 'Your verification code is {{verification_code}}. For your security, do not share this code.']]]);

    $result = app(MessageTemplateService::class)->submit($user, $template);

    expect($result['ok'])->toBeTrue();
    Http::assertSent(function ($request) {
        $components = collect($request['components']);

        return $request['category'] === 'AUTHENTICATION'
            && ! array_key_exists('text', $components->firstWhere('type', 'BODY'))
            && $components->firstWhere('type', 'BODY')['add_security_recommendation'] === true
            && data_get($components->firstWhere('type', 'BUTTONS'), 'buttons.0.otp_type') === 'COPY_CODE';
    });
});

it('does not require a body for authentication but still does for other categories', function () {
    $rules = (new StoreMessageTemplateRequest)->rules();

    expect(Validator::make(authenticationInput(), $rules)->fails())->toBeFalse()
        ->and(Validator::make(authenticationInput(['code_expiration_minutes' => 120]), $rules)->errors()->keys())->toContain('code_expiration_minutes')
        ->and(Validator::make(['provider' => 'whatsapp', 'name' => 'promo', 'language' => 'en', 'category' => 'utility'], $rules)->errors()->keys())->toContain('body');
});

it('rejects Meta rule violations before submitting', function (array $components, string $field) {
    [$user, $workspace] = templateOwner();
    Http::fake();
    $template = MessageTemplate::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'rule_check', 'language' => 'en', 'category' => 'utility', 'status' => 'draft', 'body' => 'x', 'components' => $components]);

    try {
        app(MessageTemplateService::class)->submit($user, $template);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }

    Http::assertNothingSent();
})->with([
    'two header variables' => [[['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Hi {{a}} and {{b}} today'], ['type' => 'BODY', 'text' => 'Hello {{name}}, thanks.']], 'header.text'],
    'footer variable' => [[['type' => 'BODY', 'text' => 'Hello {{name}}, thanks.'], ['type' => 'FOOTER', 'text' => 'Ref {{order}}']], 'footer.text'],
    'adjacent variables' => [[['type' => 'BODY', 'text' => 'Hi {{first}}{{last}} welcome']], 'body'],
    'split quick replies' => [[['type' => 'BODY', 'text' => 'Choose below please'], ['type' => 'BUTTONS', 'buttons' => [['type' => 'QUICK_REPLY', 'text' => 'A'], ['type' => 'URL', 'text' => 'Site', 'url' => 'https://a.test'], ['type' => 'QUICK_REPLY', 'text' => 'B']]]], 'buttons'],
]);

it('blocks changing the category of a template that already exists on WhatsApp', function () {
    [$user, $workspace, $channel] = templateOwner();
    $template = MessageTemplate::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'order_update', 'language' => 'en', 'category' => 'utility', 'status' => 'approved', 'body' => 'Your order shipped today.', 'components' => [['type' => 'BODY', 'text' => 'Your order shipped today.']]]);
    MessageTemplateSubmission::query()->create(['workspace_id' => $workspace->id, 'message_template_id' => $template->id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'provider_account_id' => 'waba-1', 'whatsapp_template_id' => 'meta-9', 'status' => 'approved']);

    expect(fn () => app(MessageTemplateService::class)->update($user, $template, ['name' => 'order_update', 'language' => 'en', 'category' => 'marketing', 'body' => 'Your order shipped today.']))
        ->toThrow(ValidationException::class);
});

it('imports every page when syncing templates from Meta and removes templates deleted on Meta', function () {
    [, $workspace, $channel] = templateOwner();
    $gone = MessageTemplate::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'old_one', 'language' => 'en', 'category' => 'utility', 'status' => 'approved', 'body' => 'x', 'components' => [['type' => 'BODY', 'text' => 'x']]]);
    MessageTemplateSubmission::query()->create(['workspace_id' => $workspace->id, 'message_template_id' => $gone->id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'provider_account_id' => 'waba-1', 'whatsapp_template_id' => 'gone-1', 'status' => 'approved']);
    Http::fakeSequence('https://graph.facebook.com/v20.0/waba-1/message_templates*')
        ->push(['data' => [['id' => '1', 'name' => 'first', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Page one body']]]], 'paging' => ['cursors' => ['after' => 'abc'], 'next' => 'https://next']])
        ->push(['data' => [['id' => '2', 'name' => 'second', 'language' => 'en', 'category' => 'MARKETING', 'status' => 'REJECTED', 'rejected_reason' => 'INVALID_FORMAT', 'components' => [['type' => 'BODY', 'text' => 'Page two body']]]], 'paging' => ['cursors' => ['after' => 'def']]]);

    $result = app(WhatsAppCloudDriver::class)->syncTemplates($channel);

    expect($result['ok'])->toBeTrue()->and($result['synced'])->toBe(2)
        ->and(MessageTemplate::query()->where('name', 'second')->value('rejection_reason'))->toBe('INVALID_FORMAT')
        ->and(MessageTemplate::query()->where('name', 'first')->value('body'))->toBe('Page one body')
        ->and(MessageTemplateSubmission::query()->where('message_template_id', $gone->id)->exists())->toBeFalse()
        ->and($gone->fresh()->status->value)->toBe('draft');
});

it('keeps local templates when Meta cannot be reached during sync', function () {
    [, $workspace, $channel] = templateOwner();
    $kept = MessageTemplate::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'keep_me', 'language' => 'en', 'category' => 'utility', 'status' => 'approved', 'body' => 'x', 'components' => [['type' => 'BODY', 'text' => 'x']]]);
    MessageTemplateSubmission::query()->create(['workspace_id' => $workspace->id, 'message_template_id' => $kept->id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'provider_account_id' => 'waba-1', 'whatsapp_template_id' => 'k-1', 'status' => 'approved']);
    Http::fake(['https://graph.facebook.com/*' => Http::response(['error' => ['message' => 'Temporarily unavailable']], 500)]);

    $result = app(WhatsAppCloudDriver::class)->syncTemplates($channel);

    expect($result['ok'])->toBeFalse()->and($kept->fresh()->status->value)->toBe('approved');
});

it('deletes a template on WhatsApp first and keeps it locally when WhatsApp refuses', function () {
    [$user, $workspace, $channel] = templateOwner();
    $template = MessageTemplate::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'to_delete', 'language' => 'en', 'category' => 'utility', 'status' => 'approved', 'body' => 'x', 'components' => [['type' => 'BODY', 'text' => 'x']]]);
    MessageTemplateSubmission::query()->create(['workspace_id' => $workspace->id, 'message_template_id' => $template->id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'provider_account_id' => 'waba-1', 'whatsapp_template_id' => 'del-1', 'status' => 'approved']);
    Http::fakeSequence('https://graph.facebook.com/v20.0/waba-1/message_templates*')
        ->push(['error' => ['message' => 'Template is in use', 'code' => 200]], 400)
        ->push(['success' => true]);

    expect(fn () => app(MessageTemplateService::class)->delete($user, $template))->toThrow(ValidationException::class);
    expect(MessageTemplate::query()->whereKey($template->id)->exists())->toBeTrue();

    app(MessageTemplateService::class)->delete($user, $template);

    expect(MessageTemplate::query()->whereKey($template->id)->exists())->toBeFalse();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request['name'] === 'to_delete');
});

it('applies Meta status webhooks to template submissions', function () {
    [, $workspace, $channel] = templateOwner();
    $template = MessageTemplate::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => 'welcome_msg', 'language' => 'en', 'category' => 'marketing', 'status' => 'pending', 'body' => 'x', 'components' => [['type' => 'BODY', 'text' => 'x']]]);
    $submission = MessageTemplateSubmission::query()->create(['workspace_id' => $workspace->id, 'message_template_id' => $template->id, 'channel_account_id' => $channel->id, 'provider' => 'whatsapp', 'provider_account_id' => 'waba-1', 'whatsapp_template_id' => '777', 'status' => 'submitted']);
    $webhook = fn (string $event, string $reason) => ['entry' => [['id' => 'waba-1', 'changes' => [['field' => 'message_template_status_update', 'value' => ['event' => $event, 'message_template_id' => 777, 'message_template_name' => 'welcome_msg', 'message_template_language' => 'en', 'reason' => $reason]]]]]];

    app(WhatsAppCloudDriver::class)->processWebhook($channel, $webhook('REJECTED', 'INVALID_FORMAT'));
    expect($template->fresh()->status->value)->toBe('rejected')->and($template->fresh()->rejection_reason)->toBe('INVALID_FORMAT');

    app(WhatsAppCloudDriver::class)->handleWebhook(Request::create('/webhook', 'POST', $webhook('APPROVED', 'NONE')), $channel);
    expect($template->fresh()->status->value)->toBe('approved')->and($template->fresh()->rejection_reason)->toBeNull()->and($submission->fresh()->status->value)->toBe('approved');
});

it('registers the hourly template sync and runs it for connected WhatsApp accounts', function () {
    [, , $channel] = templateOwner();
    Http::fake(['https://graph.facebook.com/*' => Http::response(['data' => [['id' => '5', 'name' => 'synced_one', 'language' => 'en', 'category' => 'UTILITY', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hello there']]]]])]);

    expect(app(SchedulerRegistry::class)->isRegistered('whatsapp-template-sync'))->toBeTrue();
    (new SyncWhatsAppTemplatesJob)->handle(app(ChannelManager::class));

    expect(MessageTemplate::query()->where('workspace_id', $channel->workspace_id)->where('name', 'synced_one')->exists())->toBeTrue();
});

it('lets owners search and filter many templates', function () {
    [$user, $workspace] = templateOwner();
    foreach ([['otp_login', 'authentication', 'approved'], ['spring_sale', 'marketing', 'rejected'], ['order_shipped', 'utility', 'approved']] as [$name, $category, $status]) {
        MessageTemplate::query()->create(['workspace_id' => $workspace->id, 'provider' => 'whatsapp', 'name' => $name, 'language' => 'en', 'category' => $category, 'status' => $status, 'body' => 'x', 'components' => [['type' => 'BODY', 'text' => 'x']]]);
    }
    $service = app(MessageTemplateService::class);

    expect($service->listForUser($user, 'whatsapp', ['search' => 'sale'])->pluck('name')->all())->toBe(['spring_sale'])
        ->and($service->listForUser($user, 'whatsapp', ['status' => 'approved'])->pluck('name')->sort()->values()->all())->toBe(['order_shipped', 'otp_login'])
        ->and($service->listForUser($user, 'whatsapp', ['category' => 'utility', 'status' => 'approved'])->pluck('name')->all())->toBe(['order_shipped']);
});

it('renders the authentication options on the form and the filters on the list', function () {
    [$user, $workspace] = templateOwner();
    $template = app(MessageTemplateService::class)->store($user, authenticationInput());
    withoutGuards($this)->actingAs($user);

    $this->get(route('user.message-templates.edit', $template))->assertOk()
        ->assertSee('Login code message')->assertSee('Code expires after (minutes)')->assertSee('Button label');
    $this->get(route('user.message-templates.create'))->assertOk()->assertSee('Login code message');
    $this->get(route('user.message-templates.index', ['search' => 'login']))->assertOk()
        ->assertSee('data-template-filters', false)->assertSee('customer_login_code')->assertSee('is your verification code');
    $this->get(route('user.message-templates.index', ['search' => 'nomatch']))->assertOk()->assertDontSee('customer_login_code');
});

it('stores an authentication template from the form without body, header or footer input', function () {
    [$user] = templateOwner();
    withoutGuards($this)->actingAs($user);

    $this->post(route('user.message-templates.store'), authenticationInput(['name' => 'login_form_code', 'body' => '', 'header' => ['type' => 'text', 'text' => 'ignored'], 'footer' => ['text' => 'ignored']]))
        ->assertSessionHasNoErrors()->assertRedirect();

    $template = MessageTemplate::query()->where('name', 'login_form_code')->firstOrFail();
    expect(collect($template->components)->pluck('type')->all())->toBe(['BODY', 'FOOTER', 'BUTTONS'])
        ->and(data_get($template->components, '1.code_expiration_minutes'))->toBe(5);
});
