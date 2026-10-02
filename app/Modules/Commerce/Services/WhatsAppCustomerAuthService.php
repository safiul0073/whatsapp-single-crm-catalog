<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Jobs\SendCustomerWhatsAppWelcome;
use App\Modules\Commerce\Models\CustomerAuthSetting;
use App\Modules\Commerce\Models\WhatsAppAuthChallenge;
use App\Modules\Commerce\Models\WhatsAppCustomerRegistration;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelManager;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\WhatsAppCloud\Services\WhatsAppMessagePayloadBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WhatsAppCustomerAuthService
{
    public function __construct(protected ChannelManager $channels) {}

    public function available(int $workspaceId): bool
    {
        try {
            $this->configuration($workspaceId);

            return true;
        } catch (HttpException) {
            return false;
        }
    }

    public function configuration(int $workspaceId, ?CustomerAuthSetting $settings = null): array
    {
        $settings ??= CustomerAuthSetting::forWorkspace($workspaceId);
        abort_unless($settings->enabled, 503, 'WhatsApp login is temporarily unavailable. Please retry later or contact the store.');
        $channel = ChannelAccount::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->where('status', 'connected')->find($settings->channel_id);
        $authentication = MessageTemplate::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->where('category', 'authentication')->find($settings->authentication_template_id);
        $welcome = MessageTemplate::query()->where('workspace_id', $workspaceId)->where('provider', 'whatsapp')->whereIn('category', ['utility', 'marketing'])->find($settings->welcome_template_id);
        abort_unless($channel && filled($channel->provider_account_id) && filled($channel->provider_phone_id) && filled($channel->credential('access_token')) && $authentication && $welcome
            && $authentication->approvedForWaba((string) $channel->provider_account_id) && $welcome->approvedForWaba((string) $channel->provider_account_id), 503, 'WhatsApp login is temporarily unavailable. Please retry later or contact the store.');
        $buttons = collect($authentication->components ?? [])->first(fn ($component) => strtoupper($component['type'] ?? '') === 'BUTTONS');
        $button = $buttons['buttons'][0] ?? [];
        abort_unless(count($buttons['buttons'] ?? []) === 1 && (($button['otp_type'] ?? '') === 'COPY_CODE' || (($button['type'] ?? '') === 'URL' && str_contains($button['url'] ?? '', 'otp_type=COPY_CODE'))), 503, 'WhatsApp login needs a copy-code authentication template.');
        $components = collect($welcome->components ?? []);
        $body = $components->first(fn ($component) => strtoupper($component['type'] ?? '') === 'BODY');
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body['text'] ?? '', $variables);
        abort_unless(! $components->contains(fn ($component) => in_array(strtoupper($component['type'] ?? ''), ['HEADER', 'BUTTONS']))
            && in_array(array_values(array_unique($variables[1])), [[], ['1']], true), 503, 'Choose a welcome template with no parameters or one customer-name body parameter.');

        return [$channel, $authentication, $welcome, count($variables[1]) > 0];
    }

    public function challenge(int $workspaceId, array $data): array
    {
        [$channel, $template] = $this->configuration($workspaceId);
        $ipKey = 'customer-auth:'.$workspaceId.':ip:'.hash('sha256', $data['client_ip']);
        abort_if(RateLimiter::tooManyAttempts($ipKey, 20), 429, 'Too many requests. Please try again later.');
        RateLimiter::hit($ipKey, 3600);
        $code = (string) random_int(100000, 999999);
        $challenge = DB::transaction(function () use ($workspaceId, $data, $code): WhatsAppAuthChallenge {
            CustomerAuthSetting::query()->where('workspace_id', $workspaceId)->lockForUpdate()->firstOrFail();
            abort_if(WhatsAppAuthChallenge::query()->where('workspace_id', $workspaceId)->where('created_at', '>', now()->subHour())->count() >= 200, 429, 'WhatsApp login is busy. Please try again later.');
            $recent = WhatsAppAuthChallenge::query()->where('workspace_id', $workspaceId)->where('phone', $data['phone'])->where('created_at', '>', now()->subHour());
            abort_if((clone $recent)->count() >= 5, 429, 'Too many codes requested. Please try again in an hour.');
            abort_if((clone $recent)->where('created_at', '>', now()->subSeconds(60))->exists(), 429, 'Please wait 60 seconds before requesting another code.');
            WhatsAppAuthChallenge::query()->where('workspace_id', $workspaceId)->where('phone', $data['phone'])->whereNull('consumed_at')->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
            $id = (string) Str::uuid();

            return WhatsAppAuthChallenge::query()->create(['id' => $id, 'workspace_id' => $workspaceId, 'phone' => $data['phone'], 'session_hash' => hash('sha256', $data['session_binding']), 'code_hash' => $this->codeHash($id, $code), 'expires_at' => now()->addMinutes(5), 'consented_at' => now()]);
        });
        $result = $this->channels->sendMessage($channel, ['to' => $data['phone']], ['type' => 'template', 'template_name' => $template->name, 'language' => $template->language, 'components' => [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
        ]]);
        $challenge->update(['send_status' => ($result['ok'] ?? false) ? 'sent' : 'failed', 'provider_message_id' => $result['provider_message_id'] ?? null, 'invalidated_at' => ($result['ok'] ?? false) ? null : now()]);
        abort_unless($result['ok'] ?? false, 503, 'We could not send your WhatsApp code. Please try again later.');

        return ['challenge_id' => $challenge->id, 'expires_in' => 300, 'resend_after' => 60];
    }

    public function verify(int $workspaceId, string $id, array $data): array
    {
        $result = DB::transaction(function () use ($workspaceId, $id, $data): array {
            $settings = CustomerAuthSetting::query()->where('workspace_id', $workspaceId)->lockForUpdate()->firstOrFail();
            abort_unless($settings->enabled, 503, 'WhatsApp login is temporarily unavailable.');
            $challenge = WhatsAppAuthChallenge::query()->where('workspace_id', $workspaceId)->lockForUpdate()->findOrFail($id);
            abort_unless(hash_equals($challenge->session_hash, hash('sha256', $data['session_binding'])), 404);
            if ($challenge->invalidated_at || $challenge->expires_at->isPast() || $challenge->verified_at || $challenge->attempts >= 5 || $challenge->send_status !== 'sent') {
                return ['error' => 'This code has expired or was already used. Request a new code.'];
            }
            $challenge->increment('attempts');
            if (! hash_equals($challenge->code_hash, $this->codeHash($id, $data['code']))) {
                return ['error' => 'The verification code is incorrect.'];
            }
            $grant = Str::random(64);
            $challenge->update(['verified_at' => now(), 'grant_hash' => hash('sha256', $grant), 'grant_expires_at' => now()->addMinutes(10)]);

            return ['phone' => $challenge->phone, 'grant' => $grant, 'expires_in' => 600];
        });
        if (isset($result['error'])) {
            throw ValidationException::withMessages(['code' => $result['error']]);
        }

        return $result;
    }

    public function register(int $workspaceId, array $data): array
    {
        $result = DB::transaction(function () use ($workspaceId, $data): array {
            $settings = CustomerAuthSetting::query()->where('workspace_id', $workspaceId)->lockForUpdate()->firstOrFail();
            abort_unless($settings->enabled, 503, 'WhatsApp login is temporarily unavailable.');
            $challenge = WhatsAppAuthChallenge::query()->where('workspace_id', $workspaceId)->lockForUpdate()->findOrFail($data['challenge_id']);
            abort_unless(hash_equals($challenge->session_hash, hash('sha256', $data['session_binding'])) && $challenge->grant_hash && hash_equals($challenge->grant_hash, hash('sha256', $data['grant'])), 404);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $registration = WhatsAppCustomerRegistration::query()->where('workspace_id', $workspaceId)->where('challenge_id', $challenge->id)->first();
            if ($challenge->consumed_at) {
                abort_unless($registration && hash_equals($registration->payload_hash, $hash), 409, 'This verification has already been used.');

                return ['contact_id' => $registration->contact_id, 'phone' => $registration->phone];
            }
            abort_unless($challenge->verified_at && ! $challenge->invalidated_at && $challenge->grant_expires_at?->isFuture(), 422, 'Verification expired. Request a new code.');
            $registration = WhatsAppCustomerRegistration::query()->where('workspace_id', $workspaceId)->where('customer_reference', $data['customer_reference'])->first();
            $occupied = WhatsAppCustomerRegistration::query()->where('workspace_id', $workspaceId)->where('phone', $challenge->phone)->where('customer_reference', '!=', $data['customer_reference'])->exists();
            abort_if($occupied, 409, 'This WhatsApp number is already connected to another customer.');
            $contact = Contact::query()->where('workspace_id', $workspaceId)->where('phone', $challenge->phone)->first();
            $email = $data['email'] ?? null;
            if ($email && Contact::query()->where('workspace_id', $workspaceId)->where('email', $email)->exists()) {
                $email = null;
            }
            $contact ??= Contact::query()->create(['workspace_id' => $workspaceId, 'phone' => $challenge->phone, 'name' => $data['name'], 'email' => $email, 'source' => 'website', 'opt_in_status' => 'unknown']);
            $isFirst = ! $registration;
            $registration ??= new WhatsAppCustomerRegistration(['id' => $data['registration_reference'], 'workspace_id' => $workspaceId, 'customer_reference' => $data['customer_reference'], 'welcome_status' => $data['new_customer'] ? 'pending' : 'skipped']);
            $registration->fill(['challenge_id' => $challenge->id, 'phone' => $challenge->phone, 'contact_id' => $contact->id, 'payload_hash' => $hash, 'consented_at' => $challenge->consented_at])->save();
            $challenge->update(['consumed_at' => now()]);
            if ($isFirst && $registration->welcome_status === 'pending') {
                DB::afterCommit(fn () => rescue(fn () => SendCustomerWhatsAppWelcome::dispatch($registration->id), report: true));
            }

            return ['contact_id' => $contact->id, 'phone' => $challenge->phone];
        });

        return $result;
    }

    public function sendWelcome(string $id): void
    {
        $registration = DB::transaction(function () use ($id): ?WhatsAppCustomerRegistration {
            $registration = WhatsAppCustomerRegistration::query()->lockForUpdate()->findOrFail($id);
            if (! in_array($registration->welcome_status, ['pending', 'failed'], true) || $registration->welcome_attempts >= 3) {
                return null;
            }
            $registration->update(['welcome_status' => 'processing', 'welcome_attempts' => $registration->welcome_attempts + 1, 'delivery_status' => null, 'provider_message_id' => null]);

            return $registration;
        });
        if (! $registration) {
            return;
        }
        $contact = Contact::query()->where('workspace_id', $registration->workspace_id)->find($registration->contact_id);
        if (! $contact || $contact->isOptedOut() || $contact->phone !== $registration->phone) {
            $registration->update(['welcome_status' => 'skipped']);

            return;
        }
        try {
            [$channel, , $template, $hasName] = $this->configuration($registration->workspace_id);
        } catch (HttpException) {
            $registration->update(['welcome_status' => 'failed']);
            throw new \RuntimeException('WhatsApp welcome configuration is unavailable.');
        }
        $payload = ['type' => 'template', 'template_name' => $template->name, 'language' => $template->language, 'components' => $hasName ? [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $contact->name]]]] : []];
        $payload['meta_payload'] = app(WhatsAppMessagePayloadBuilder::class)->build($registration->phone, $payload);
        $payload['meta_payload']['biz_opaque_callback_data'] = 'customer-welcome:'.$registration->id.':'.$registration->welcome_attempts;
        $result = $this->channels->sendMessage($channel, ['to' => $registration->phone], $payload);
        $uncertain = ($result['error_code'] ?? '') === 'connection_error' || ($result['http_status'] ?? 0) >= 500 || (blank($result['error_code'] ?? null) && blank($result['http_status'] ?? null));
        $status = ($result['ok'] ?? false) ? 'sent' : ($uncertain ? 'uncertain' : 'failed');
        DB::transaction(function () use ($registration, $status, $result): void {
            $locked = WhatsAppCustomerRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            if (in_array($locked->delivery_status, ['sent', 'delivered', 'read'], true)) {
                return;
            }
            $locked->update(['welcome_status' => $status, 'provider_message_id' => $result['provider_message_id'] ?? null, 'welcome_sent_at' => $status === 'sent' ? now() : null]);
        });
        if ($status === 'failed') {
            throw new \RuntimeException('WhatsApp welcome delivery failed.');
        }
    }

    private function codeHash(string $id, string $code): string
    {
        return hash_hmac('sha256', $id.':'.$code, (string) config('app.key'));
    }

    public function recordWelcomeDelivery(ChannelAccount $account, array $status): void
    {
        $callback = explode(':', (string) ($status['biz_opaque_callback_data'] ?? ''));
        if (count($callback) !== 3 || $callback[0] !== 'customer-welcome') {
            return;
        }
        DB::transaction(function () use ($account, $status, $callback): void {
            $registration = WhatsAppCustomerRegistration::query()->where('workspace_id', $account->workspace_id)->lockForUpdate()->find($callback[1]);
            if (! $registration || $registration->welcome_attempts !== (int) $callback[2] || in_array($registration->delivery_status, ['delivered', 'read'], true)) {
                return;
            }
            $delivered = in_array($status['status'] ?? '', ['sent', 'delivered', 'read'], true);
            $registration->update(['provider_message_id' => $status['id'] ?? $registration->provider_message_id, 'delivery_status' => $status['status'] ?? null, 'welcome_status' => $delivered ? 'sent' : (($status['status'] ?? '') === 'failed' ? 'failed' : $registration->welcome_status), 'welcome_sent_at' => $delivered ? ($registration->welcome_sent_at ?? now()) : $registration->welcome_sent_at]);
        });
    }
}
