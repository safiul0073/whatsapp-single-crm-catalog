<?php

namespace App\Modules\Telegram\Services;

use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\Contacts\Services\ContactService;
use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Telegram\Models\TelegramOptInToken;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramOptInService
{
    public function __construct(protected ContactService $contacts) {}

    public function linkFor(Contact $contact, ?ChannelAccount $account = null): ?array
    {
        $account ??= $this->connectedAccount((int) $contact->workspace_id);

        if (! $account) {
            return null;
        }

        $token = $this->tokenFor($contact, $account);
        $botUsername = $this->botUsername($account);

        if (blank($botUsername)) {
            return null;
        }

        return [
            'token' => $token->token,
            'url' => 'https://t.me/'.ltrim((string) $botUsername, '@').'?start='.$token->token,
            'bot_username' => ltrim((string) $botUsername, '@'),
            'expires_at' => optional($token->expires_at)->toIso8601String(),
        ];
    }

    public function publicLinksFor(ChannelAccount $account): ?array
    {
        $botUsername = $this->botUsername($account);

        if (blank($botUsername)) {
            return null;
        }

        $base = 'https://t.me/'.ltrim((string) $botUsername, '@');

        return [
            'bot_username' => ltrim((string) $botUsername, '@'),
            'bot_link' => $base,
            'generic_subscribe_link' => $base.'?start=subscribe',
        ];
    }

    public function linkFromToken(ChannelAccount $account, string $token, array $event): ?ContactProviderIdentity
    {
        $optIn = TelegramOptInToken::query()
            ->where('channel_account_id', $account->id)
            ->where('token', $token)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with('contact')
            ->first();

        if (! $optIn?->contact) {
            return null;
        }

        $identity = $this->linkContact($account, $optIn->contact, $event);

        if (! $optIn->used_at) {
            $optIn->update(['used_at' => now()]);
        }

        return $identity;
    }

    public function linkFromSharedPhone(ChannelAccount $account, array $event): ?ContactProviderIdentity
    {
        $phone = data_get($event, 'contact.phone_number');

        if (blank($phone)) {
            return null;
        }

        $sharedUserId = data_get($event, 'contact.user_id');
        if (filled($sharedUserId) && (string) $sharedUserId !== (string) ($event['provider_contact_id'] ?? '')) {
            return null;
        }

        try {
            $normalized = $this->contacts->normalizePhone(str_starts_with((string) $phone, '+') ? (string) $phone : '+'.$phone);
        } catch (ValidationException) {
            return null;
        }

        $contact = $this->contacts->findByPhone($account->workspace_id, $normalized)
            ?: $this->contacts->upsert($account->workspace_id, [
                'phone' => $normalized,
                'name' => $this->nameFromEvent($event) ?: $normalized,
            ]);

        return $this->linkContact($account, $contact, $event);
    }

    public function linkContact(ChannelAccount $account, Contact $contact, array $event): ContactProviderIdentity
    {
        $providerContactId = (string) ($event['provider_contact_id'] ?? '');
        $chatId = (string) ($event['chat_id'] ?? $providerContactId);
        $username = $event['username'] ?? null;

        $contact->updateQuietly([
            'opt_in_status' => ContactOptInStatus::Subscribed->value,
            'opt_in_at' => $contact->opt_in_at ?? now(),
            'opt_out_at' => null,
            'last_interaction_at' => now(),
        ]);

        return ContactProviderIdentity::query()->updateOrCreate(
            [
                'workspace_id' => $account->workspace_id,
                'provider' => 'telegram',
                'provider_contact_id' => $chatId,
            ],
            [
                'contact_id' => $contact->id,
                'channel_account_id' => $account->id,
                'address' => $chatId,
                'username' => $username,
                'identity_type' => 'telegram_chat_id',
                'status' => 'active',
                'metadata' => $event['payload'] ?? [],
                'last_interaction_at' => now(),
            ]
        );
    }

    protected function tokenFor(Contact $contact, ChannelAccount $account): TelegramOptInToken
    {
        $existing = TelegramOptInToken::query()
            ->where('workspace_id', $contact->workspace_id)
            ->where('contact_id', $contact->id)
            ->where('channel_account_id', $account->id)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        return TelegramOptInToken::query()->create([
            'workspace_id' => $contact->workspace_id,
            'contact_id' => $contact->id,
            'channel_account_id' => $account->id,
            'token' => Str::random(32),
            'expires_at' => now()->addDays(30),
        ]);
    }

    protected function connectedAccount(int $workspaceId): ?ChannelAccount
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'telegram')
            ->where('status', ChannelAccountStatus::Connected->value)
            ->orderByDesc('connected_at')
            ->orderByDesc('id')
            ->first();
    }

    protected function botUsername(ChannelAccount $account): ?string
    {
        return $account->settings['telegram_bot_username']
            ?? $account->provider_account_id
            ?? null;
    }

    protected function nameFromEvent(array $event): ?string
    {
        $first = trim((string) data_get($event, 'contact.first_name', ''));
        $last = trim((string) data_get($event, 'contact.last_name', ''));
        $name = trim($first.' '.$last);

        return $name !== '' ? $name : null;
    }
}
