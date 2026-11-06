<?php

namespace App\Modules\MetaSocial\Services;

use App\Modules\Contacts\Models\ContactProviderIdentity;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Replaces the "Messenger 1234…" / "Instagram 1234…" placeholder name with the sender's real
 * name from Meta (Business Asset User Profile Access). Best effort: a failed lookup keeps the
 * placeholder and is retried at most once a day.
 */
class MetaSocialProfileService
{
    public const PROVIDERS = ['messenger', 'instagram'];

    protected const RETRY_AFTER_HOURS = 24;

    public function __construct(protected MetaSocialClient $client) {}

    public function enrich(ChannelAccount $account, ContactProviderIdentity $identity): void
    {
        if (! in_array($account->provider, self::PROVIDERS, true)) {
            return;
        }

        $contact = $identity->contact;
        $metadata = (array) ($identity->metadata ?? []);

        if (! $contact || ! $contact->hasPlaceholderName() || $this->checkedRecently($metadata)) {
            return;
        }

        $token = (string) $account->credential('access_token', '');

        if ($token === '' || blank($identity->provider_contact_id)) {
            return;
        }

        try {
            $response = $this->client->userProfile((string) $identity->provider_contact_id, $token, $account->provider);
            $profile = $response->successful() ? (array) $response->json() : [];
        } catch (Throwable) {
            $profile = [];
        }

        $username = trim((string) ($profile['username'] ?? ''));
        $name = $account->provider === 'instagram'
            ? (trim((string) ($profile['name'] ?? '')) ?: ($username !== '' ? '@'.ltrim($username, '@') : ''))
            : trim(trim((string) ($profile['first_name'] ?? '')).' '.trim((string) ($profile['last_name'] ?? '')));

        $identity->forceFill([
            'username' => $identity->username ?: ($username !== '' ? $username : null),
            'metadata' => array_merge($metadata, array_filter([
                'profile_checked_at' => now()->toIso8601String(),
                'profile_pic' => is_string($profile['profile_pic'] ?? null) ? $profile['profile_pic'] : null,
            ])),
        ])->save();

        $contact->improveName($name);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function checkedRecently(array $metadata): bool
    {
        $checkedAt = $metadata['profile_checked_at'] ?? null;

        if (! is_string($checkedAt) || $checkedAt === '') {
            return false;
        }

        try {
            return Carbon::parse($checkedAt)->gt(now()->subHours(self::RETRY_AFTER_HOURS));
        } catch (Throwable) {
            return false;
        }
    }
}
