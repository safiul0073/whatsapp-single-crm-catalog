<?php

namespace App\Modules\MarketingChannels\Contracts;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use Illuminate\Http\Request;

interface MarketingChannelDriver
{
    public function provider(): string;

    public function sendMessage(ChannelAccount $account, array $recipient, array $payload): array;

    public function verifyWebhook(Request $request, ChannelAccount $account): bool;

    public function handleWebhook(Request $request, ChannelAccount $account): array;

    public function syncTemplates(ChannelAccount $account): array;

    public function getHealthStatus(ChannelAccount $account): array;

    /**
     * Test the channel connection and return a status array.
     */
    public function testConnection(ChannelAccount $account): array;

    /**
     * Process a webhook payload that has already been received and return normalized events.
     */
    public function processWebhook(ChannelAccount $account, array $payload): array;

    /**
     * Return the capabilities of this provider, e.g. ['Campaigns', 'Inbox', 'Templates'].
     *
     * @return array<int, string>
     */
    public function getCapabilities(): array;

    /**
     * Validate campaign rules for this provider. Throw ValidationException on failure.
     */
    public function validateCampaign(ChannelAccount $account, Campaign $campaign): void;
}
