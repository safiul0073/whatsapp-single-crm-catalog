<?php

namespace App\Modules\MarketingChannels\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\MarketingChannels\Contracts\MarketingChannelDriver;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use App\Modules\PlansSubscriptions\Services\SubscriptionAccessService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ChannelManager
{
    public const META_PROVIDERS = ['whatsapp', 'messenger', 'instagram', 'threads'];

    /** @var array<string, MarketingChannelDriver> */
    protected array $drivers = [];

    public function register(MarketingChannelDriver $driver): void
    {
        $this->drivers[$driver->provider()] = $driver;
    }

    public function driver(string $provider): MarketingChannelDriver
    {
        return $this->drivers[$provider] ?? throw new InvalidArgumentException("Marketing channel driver [{$provider}] is not registered.");
    }

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array
    {
        if (! $this->subscriptionAccess()->canUseServices((int) $account->workspace_id)) {
            return [
                'ok' => false,
                'error_code' => 'subscription_expired',
                'error' => __(SubscriptionAccessService::EXPIRED_MESSAGE),
            ];
        }

        $limits = app(PlanLimitService::class);

        if (! $limits->allows((int) $account->workspace_id, 'messages_per_month')) {
            return [
                'ok' => false,
                'error_code' => 'plan_message_quota_exceeded',
                'error' => __('Your monthly message quota is used up. Upgrade your plan to keep sending.'),
            ];
        }

        $result = $this->explainMetaError($account, $this->driver($account->provider)->sendMessage($account, $recipient, $payload));

        if ($result['ok'] ?? false) {
            $limits->consume((int) $account->workspace_id, 'messages_per_month');
        }

        return $result;
    }

    /**
     * Meta send failures get a plain-language error and a next step (error_detail), while the raw
     * Meta response stays in "response" for support.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function explainMetaError(ChannelAccount $account, array $result): array
    {
        if (($result['ok'] ?? false) || ! in_array($account->provider, self::META_PROVIDERS, true)) {
            return $result;
        }

        $error = data_get($result, 'response.error');

        if (! is_array($error) || blank($error['code'] ?? null)) {
            return $result;
        }

        $translator = app(MetaErrorTranslator::class);
        $result['error_detail'] = $translator->translate(['error' => $error], $result['error'] ?? null);
        $result['error'] = $translator->summary(['error' => $error], $result['error'] ?? null);
        $result['error_code'] = $result['error_code'] ?? (string) $error['code'];

        return $result;
    }

    public function verifyWebhook(Request $request, ChannelAccount $account): bool
    {
        return $this->driver($account->provider)->verifyWebhook($request, $account);
    }

    public function handleWebhook(Request $request, ChannelAccount $account): array
    {
        return $this->driver($account->provider)->handleWebhook($request, $account);
    }

    public function syncTemplates(ChannelAccount $account): array
    {
        if (! $this->subscriptionAccess()->canUseServices((int) $account->workspace_id)) {
            return ['ok' => false, 'error' => __(SubscriptionAccessService::EXPIRED_MESSAGE)];
        }

        return $this->driver($account->provider)->syncTemplates($account);
    }

    public function health(ChannelAccount $account): array
    {
        return $this->driver($account->provider)->getHealthStatus($account);
    }

    public function testConnection(ChannelAccount $account): array
    {
        if (! $this->subscriptionAccess()->canUseServices((int) $account->workspace_id)) {
            return ['ok' => false, 'error' => __(SubscriptionAccessService::EXPIRED_MESSAGE)];
        }

        return $this->driver($account->provider)->testConnection($account);
    }

    public function processWebhook(ChannelAccount $account, array $payload): array
    {
        if (! $this->subscriptionAccess()->canUseServices((int) $account->workspace_id)) {
            return ['events' => [], 'blocked' => true, 'reason' => 'subscription_expired'];
        }

        return $this->driver($account->provider)->processWebhook($account, $payload);
    }

    public function getCapabilities(string $provider): array
    {
        return $this->driver($provider)->getCapabilities();
    }

    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void
    {
        $this->subscriptionAccess()->assertActiveForUse((int) $account->workspace_id);

        $this->driver($account->provider)->validateCampaign($account, $campaign);
    }

    protected function subscriptionAccess(): SubscriptionAccessService
    {
        return app(SubscriptionAccessService::class);
    }

    /**
     * @return array<int, string>
     */
    public function providers(): array
    {
        return array_keys($this->drivers);
    }
}
