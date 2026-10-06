<?php

use App\Modules\Commerce\Models\CustomerAuthSetting;
use App\Modules\Commerce\Models\WhatsAppAuthChallenge;
use App\Modules\Commerce\Models\WhatsAppCustomerRegistration;
use App\Modules\Commerce\Services\WhatsAppCustomerAuthService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\WhatsAppCloud\Services\WhatsAppCloudDriver;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake();
    $this->workspace = Workspace::query()->create(['name' => 'Customer auth test', 'slug' => 'customer-auth-'.Str::uuid(), 'status' => 'active']);
    config(['commerce.store_workspace_id' => $this->workspace->id]);
    $this->channel = ChannelAccount::query()->create(['workspace_id' => $this->workspace->id, 'provider' => 'whatsapp', 'name' => 'Login channel', 'status' => 'connected', 'provider_account_id' => 'waba-auth-test', 'provider_phone_id' => '123', 'credentials' => ['access_token' => 'test-token']]);
    $this->authentication = MessageTemplate::query()->create(['workspace_id' => $this->workspace->id, 'provider' => 'whatsapp', 'name' => 'login_code', 'language' => 'en_US', 'category' => 'authentication', 'status' => 'approved', 'components' => [['type' => 'BODY', 'text' => '{{1}} is your code'], ['type' => 'BUTTONS', 'buttons' => [['type' => 'OTP', 'otp_type' => 'COPY_CODE']]]]]);
    $this->welcome = MessageTemplate::query()->create(['workspace_id' => $this->workspace->id, 'provider' => 'whatsapp', 'name' => 'account_welcome', 'language' => 'en_US', 'category' => 'utility', 'status' => 'approved', 'components' => [['type' => 'BODY', 'text' => 'Welcome {{1}}. Your account is ready.']]]);
    foreach ([$this->authentication, $this->welcome] as $template) {
        $template->submissions()->create(['workspace_id' => $this->workspace->id, 'channel_account_id' => $this->channel->id, 'provider_account_id' => 'waba-auth-test', 'status' => 'approved']);
    }
    $this->settings = CustomerAuthSetting::forWorkspace($this->workspace->id);
    $this->settings->update(['enabled' => true, 'channel_id' => $this->channel->id, 'authentication_template_id' => $this->authentication->id, 'welcome_template_id' => $this->welcome->id]);
    $this->sent = [];
    $this->sendResult = ['ok' => true, 'provider_message_id' => 'test-message'];
    $this->mock(ChannelManager::class)->shouldReceive('sendMessage')->andReturnUsing(function ($account, $recipient, $payload) {
        $this->sent[] = $payload;

        return $this->sendResult;
    });
    $this->auth = app(WhatsAppCustomerAuthService::class);
    $this->challengeData = ['phone' => '+8801700000000', 'session_binding' => str_repeat('s', 64), 'client_ip' => '127.0.0.1', 'consent' => true];
});

function customerAuthVerified($test): array
{
    $challenge = $test->auth->challenge($test->workspace->id, $test->challengeData);
    $code = $test->sent[array_key_last($test->sent)]['components'][0]['parameters'][0]['text'];
    $verified = $test->auth->verify($test->workspace->id, $challenge['challenge_id'], ['session_binding' => $test->challengeData['session_binding'], 'code' => $code]);

    return ['challenge_id' => $challenge['challenge_id'], 'grant' => $verified['grant'], 'session_binding' => $test->challengeData['session_binding'], 'registration_reference' => (string) Str::uuid(), 'customer_reference' => 'ecommarce:123', 'name' => 'Customer Name', 'email' => null, 'new_customer' => true];
}

it('sends the OTP without saving its content or creating a contact', function () {
    $beforeContacts = Contact::count();
    $beforeMessages = Message::count();
    $response = $this->postJson('/api/commerce/store/auth/whatsapp/challenges', $this->challengeData)->assertOk();
    $code = $this->sent[0]['components'][0]['parameters'][0]['text'];
    $challenge = WhatsAppAuthChallenge::findOrFail($response->json('challenge_id'));
    expect($challenge->code_hash)->not->toBe($code)
        ->and($response->getContent())->not->toContain($code)
        ->and($challenge->toArray())->not->toHaveKeys(['code_hash', 'grant_hash', 'session_hash'])
        ->and(Contact::count())->toBe($beforeContacts)->and(Message::count())->toBe($beforeMessages);
    expect($this->sent[0]['components'][1]['parameters'][0]['text'])->toBe($code);
});

it('persists five incorrect attempts and rejects the correct code afterwards', function () {
    $created = $this->auth->challenge($this->workspace->id, $this->challengeData);
    $code = $this->sent[0]['components'][0]['parameters'][0]['text'];
    for ($i = 0; $i < 5; $i++) {
        expect(fn () => $this->auth->verify($this->workspace->id, $created['challenge_id'], ['session_binding' => $this->challengeData['session_binding'], 'code' => '000000']))->toThrow(ValidationException::class);
    }
    expect(WhatsAppAuthChallenge::find($created['challenge_id'])->attempts)->toBe(5);
    expect(fn () => $this->auth->verify($this->workspace->id, $created['challenge_id'], ['session_binding' => $this->challengeData['session_binding'], 'code' => $code]))->toThrow(ValidationException::class);
});

it('rejects expired codes and verified-code replay', function () {
    $payload = customerAuthVerified($this);
    $code = $this->sent[0]['components'][0]['parameters'][0]['text'];
    expect(fn () => $this->auth->verify($this->workspace->id, $payload['challenge_id'], ['session_binding' => $this->challengeData['session_binding'], 'code' => $code]))->toThrow(ValidationException::class);
    $this->travel(6)->minutes();
    $created = $this->auth->challenge($this->workspace->id, $this->challengeData);
    $code = $this->sent[1]['components'][0]['parameters'][0]['text'];
    $this->travel(6)->minutes();
    expect(fn () => $this->auth->verify($this->workspace->id, $created['challenge_id'], ['session_binding' => $this->challengeData['session_binding'], 'code' => $code]))->toThrow(ValidationException::class);
});

it('enforces resend cooldown, invalidates older grants and limits hourly sends', function () {
    $payload = customerAuthVerified($this);
    expect(fn () => $this->auth->challenge($this->workspace->id, $this->challengeData))->toThrow(HttpException::class);
    for ($i = 0; $i < 4; $i++) {
        $this->travel(61)->seconds();
        $this->auth->challenge($this->workspace->id, $this->challengeData);
    }
    expect(fn () => $this->auth->register($this->workspace->id, $payload))->toThrow(HttpException::class);
    $this->travel(61)->seconds();
    expect(fn () => $this->auth->challenge($this->workspace->id, $this->challengeData))->toThrow(HttpException::class);
    expect($this->sent)->toHaveCount(5);
});

it('rejects cross-store and cross-session requests', function () {
    $payload = customerAuthVerified($this);
    $this->postJson('/api/commerce/store/auth/whatsapp/challenges/'.$payload['challenge_id'].'/verify', ['session_binding' => str_repeat('x', 64), 'code' => '000000'])->assertNotFound();
    $other = Workspace::query()->create(['name' => 'Other', 'slug' => (string) Str::uuid()]);
    expect(fn () => $this->auth->register($other->id, $payload))->toThrow(ModelNotFoundException::class);
});

it('registers once, reuses the contact and sends one welcome across retries and later logins', function () {
    $payload = customerAuthVerified($this);
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->workspace->id, 'phone' => $this->challengeData['phone'], 'name' => 'Existing CRM Name', 'source' => 'manual', 'opt_in_status' => 'unknown']));
    $result = $this->auth->register($this->workspace->id, $payload);
    expect($this->auth->register($this->workspace->id, $payload))->toBe($result);
    $registration = WhatsAppCustomerRegistration::where('workspace_id', $this->workspace->id)->first();
    $this->auth->sendWelcome($registration->id);
    $this->auth->sendWelcome($registration->id);
    expect($result['contact_id'])->toBe($contact->id)->and($contact->fresh()->name)->toBe('Existing CRM Name')->and($contact->fresh()->opt_in_status->value)->toBe('unknown')->and($this->sent)->toHaveCount(2);
    $this->travel(61)->seconds();
    $next = customerAuthVerified($this);
    $next['new_customer'] = false;
    $this->auth->register($this->workspace->id, $next);
    $this->auth->sendWelcome($registration->id);
    expect(WhatsAppCustomerRegistration::where('workspace_id', $this->workspace->id)->count())->toBe(1)->and($this->sent)->toHaveCount(3);
});

it('rejects altered registration retries and a second customer using the same number', function () {
    $payload = customerAuthVerified($this);
    $this->auth->register($this->workspace->id, $payload);
    $altered = $payload;
    $altered['name'] = 'Different';
    expect(fn () => $this->auth->register($this->workspace->id, $altered))->toThrow(HttpException::class);
    $this->travel(61)->seconds();
    $next = customerAuthVerified($this);
    $next['customer_reference'] = 'ecommarce:999';
    expect(fn () => $this->auth->register($this->workspace->id, $next))->toThrow(HttpException::class);
});

it('skips welcome for opted-out contacts and for existing-account linking', function () {
    $payload = customerAuthVerified($this);
    $contact = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->workspace->id, 'phone' => $this->challengeData['phone'], 'name' => 'Opted out', 'source' => 'manual', 'opt_in_status' => 'unsubscribed', 'opt_out_at' => now()]));
    $this->auth->register($this->workspace->id, $payload);
    $registration = WhatsAppCustomerRegistration::where('workspace_id', $this->workspace->id)->first();
    $this->auth->sendWelcome($registration->id);
    expect($registration->fresh()->welcome_status)->toBe('skipped')->and($contact->fresh()->opt_in_status->value)->toBe('unsubscribed')->and($this->sent)->toHaveCount(1);
});

it('retries definite welcome failures but holds uncertain deliveries', function () {
    $payload = customerAuthVerified($this);
    $this->auth->register($this->workspace->id, $payload);
    $registration = WhatsAppCustomerRegistration::where('workspace_id', $this->workspace->id)->first();
    $this->sendResult = ['ok' => false, 'error_code' => 'rejected'];
    expect(fn () => $this->auth->sendWelcome($registration->id))->toThrow(RuntimeException::class);
    expect($registration->fresh()->welcome_status)->toBe('failed');
    $this->sendResult = ['ok' => false, 'error_code' => 'connection_error'];
    $this->auth->sendWelcome($registration->id);
    $this->auth->sendWelcome($registration->id);
    expect($registration->fresh()->welcome_status)->toBe('uncertain')->and($registration->fresh()->welcome_attempts)->toBe(2)->and($this->sent)->toHaveCount(3);
});

it('disables login for missing configuration or rejected templates', function () {
    $this->settings->update(['enabled' => false]);
    expect($this->auth->available($this->workspace->id))->toBeFalse();
    $this->settings->update(['enabled' => true]);
    $this->authentication->submissions()->update(['status' => 'rejected']);
    expect($this->auth->available($this->workspace->id))->toBeFalse();
    expect(fn () => $this->auth->challenge($this->workspace->id, $this->challengeData))->toThrow(HttpException::class);
    expect($this->sent)->toHaveCount(0);
});

it('invalidates a code after a provider failure', function () {
    $this->sendResult = ['ok' => false, 'error_code' => 'connection_error'];
    expect(fn () => $this->auth->challenge($this->workspace->id, $this->challengeData))->toThrow(HttpException::class);
    $challenge = WhatsAppAuthChallenge::where('workspace_id', $this->workspace->id)->first();
    expect($challenge->invalidated_at)->not->toBeNull()->and($challenge->send_status)->toBe('failed');
});

it('reconciles an uncertain welcome through the live webhook processing path', function () {
    $payload = customerAuthVerified($this);
    $this->auth->register($this->workspace->id, $payload);
    $registration = WhatsAppCustomerRegistration::where('workspace_id', $this->workspace->id)->first();
    $this->sendResult = ['ok' => false, 'error_code' => 'connection_error'];
    $this->auth->sendWelcome($registration->id);
    $callback = $this->sent[1]['meta_payload']['biz_opaque_callback_data'];
    app(WhatsAppCloudDriver::class)->processWebhook($this->channel, ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'confirmed-message', 'status' => 'delivered', 'biz_opaque_callback_data' => $callback]]]]]]]]);
    $this->auth->sendWelcome($registration->id);
    expect($registration->fresh()->welcome_status)->toBe('sent')->and($registration->fresh()->delivery_status)->toBe('delivered')->and($this->sent)->toHaveCount(2);
});

it('creates a separate phone contact without claiming an existing contacts email', function () {
    $original = Contact::withoutEvents(fn () => Contact::query()->create(['workspace_id' => $this->workspace->id, 'phone' => '+8801800000000', 'email' => 'old@example.test', 'name' => 'Original', 'source' => 'manual', 'opt_in_status' => 'unknown']));
    $payload = customerAuthVerified($this);
    $payload['email'] = $original->email;
    $result = $this->auth->register($this->workspace->id, $payload);
    expect($result['contact_id'])->not->toBe($original->id)->and(Contact::find($result['contact_id'])->email)->toBeNull()->and($original->fresh()->email)->toBe('old@example.test');
});

it('holds server-error welcome responses instead of risking a duplicate send', function () {
    $payload = customerAuthVerified($this);
    $this->auth->register($this->workspace->id, $payload);
    $registration = WhatsAppCustomerRegistration::where('workspace_id', $this->workspace->id)->first();
    $this->sendResult = ['ok' => false, 'http_status' => 500, 'error_code' => 2];
    $this->auth->sendWelcome($registration->id);
    $this->auth->sendWelcome($registration->id);
    expect($registration->fresh()->welcome_status)->toBe('uncertain')->and($this->sent)->toHaveCount(2);
});

it('blocks in-progress verification and registration when the owner disables login', function () {
    $payload = customerAuthVerified($this);
    $this->settings->update(['enabled' => false]);
    expect(fn () => $this->auth->register($this->workspace->id, $payload))->toThrow(HttpException::class);
    expect(fn () => $this->auth->verify($this->workspace->id, $payload['challenge_id'], ['session_binding' => $this->challengeData['session_binding'], 'code' => '123456']))->toThrow(HttpException::class);
});
