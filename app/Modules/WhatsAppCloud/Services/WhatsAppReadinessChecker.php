<?php

namespace App\Modules\WhatsAppCloud\Services;

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ReadinessChecker;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ReadinessItem;
use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use Illuminate\Http\Client\Response;

/**
 * What a connected WhatsApp number still needs in Meta Business settings before it can
 * send messages, templates and campaigns, read from Meta's live phone and WABA data.
 */
class WhatsAppReadinessChecker implements ReadinessChecker
{
    protected const WHATSAPP_MANAGER = 'https://business.facebook.com/wa/manage/phone-numbers/';

    protected const PAYMENT_SETTINGS = 'https://business.facebook.com/billing_hub/payment_settings/';

    protected const SECURITY_CENTER = 'https://business.facebook.com/settings/security';

    public function __construct(
        protected WhatsAppCloudClient $client,
        protected MetaErrorTranslator $errors,
    ) {}

    public function provider(): string
    {
        return 'whatsapp';
    }

    public function check(ChannelAccount $account): array
    {
        $token = (string) $account->credential('access_token');
        $phoneId = (string) $account->provider_phone_id;
        $wabaId = (string) $account->provider_account_id;

        if ($token === '' || $phoneId === '' || $wabaId === '') {
            return ['items' => [ReadinessItem::make(
                'connection',
                __('Connect WhatsApp with Meta'),
                'blocked',
                __('The channel has no Meta access yet.'),
                [__('Go to Channels and use Connect with Meta.')],
                null,
                ['receive', 'send', 'templates', 'campaigns'],
            )]];
        }

        $phone = $this->safe(fn (): Response => $this->client->node($phoneId, $token, 'display_phone_number,verified_name,name_status,code_verification_status,quality_rating,messaging_limit_tier,status,platform_type,health_status'));

        if (! $phone?->successful()) {
            return ['items' => [$this->unreachable($phone)]];
        }

        $waba = $this->safe(fn (): Response => $this->client->node($wabaId, $token, 'name,account_review_status,business_verification_status'));
        $funding = $this->safe(fn (): Response => $this->client->node($wabaId, $token, 'primary_funding_id'));
        $subscriptions = $this->safe(fn (): Response => $this->client->subscribedApps($wabaId, $token));

        $p = $phone->json() ?? [];
        $w = $waba?->successful() ? ($waba->json() ?? []) : null;
        $coexistence = data_get($account->settings, 'signup_mode') === 'coexistence' || strtoupper((string) ($p['platform_type'] ?? '')) === 'ON_PREMISE_COEXISTENCE';

        $items = array_values(array_filter([
            $this->registration($p),
            $this->displayName($p),
            $this->health($p),
            $this->accountReview($w),
            $this->payment($funding),
            $this->webhooks($subscriptions),
            $this->templates($account),
            $this->verification($w),
        ]));

        return [
            'items' => $items,
            'facts' => array_values(array_filter([
                filled($p['display_phone_number'] ?? null) ? ['label' => __('Number'), 'value' => (string) $p['display_phone_number']] : null,
                filled($p['verified_name'] ?? null) ? ['label' => __('Display name'), 'value' => (string) $p['verified_name']] : null,
                filled($p['quality_rating'] ?? null) ? ['label' => __('Quality'), 'value' => $this->qualityLabel((string) $p['quality_rating'])] : null,
                filled($p['messaging_limit_tier'] ?? null) ? ['label' => __('Daily limit'), 'value' => $this->tierLabel((string) $p['messaging_limit_tier'])] : null,
                ['label' => __('Mode'), 'value' => $coexistence ? __('WhatsApp Business app + Cloud API (coexistence)') : __('Cloud API')],
            ])),
            'notes' => array_values(array_filter([
                __('Customers can be answered freely for 24 hours after their last message. After that only approved templates can be sent.'),
                $coexistence ? __('Keep the WhatsApp Business app installed on the phone and open it at least once every 14 days, or Meta disconnects it.') : null,
                filled($account->credential('two_step_pin')) ? __('A two-step verification PIN was set for this number when it was registered. You can change it in WhatsApp Manager.') : null,
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $p
     */
    protected function registration(array $p): array
    {
        $status = strtoupper((string) ($p['status'] ?? ''));
        $ok = in_array($status, ['CONNECTED', ''], true);

        return ReadinessItem::make(
            'registered',
            __('Number registered with WhatsApp Cloud API'),
            $ok ? 'done' : 'blocked',
            __('Only registered numbers can send and receive messages through the app.'),
            $ok ? [] : [
                __('Open WhatsApp Manager → Phone numbers and check the number status.'),
                __('If it shows Pending, reconnect WhatsApp from Channels so the app can register it again.'),
                __('If it is Flagged or Restricted, follow the steps Meta shows there.'),
            ],
            $ok ? null : self::WHATSAPP_MANAGER,
            ['receive', 'send', 'templates', 'campaigns'],
            $ok ? null : __('Meta status: :status', ['status' => $status ?: __('unknown')]),
        );
    }

    /**
     * @param  array<string, mixed>  $p
     */
    protected function displayName(array $p): array
    {
        $status = strtoupper((string) ($p['name_status'] ?? ''));

        [$state, $detail, $how] = match ($status) {
            'APPROVED', 'AVAILABLE_WITHOUT_REVIEW', 'NON_EXISTS', '' => ['done', null, []],
            'PENDING_REVIEW' => ['waiting', __('Meta is reviewing the display name. This usually takes 1–2 business days.'), [__('Nothing to do. Press Re-check later.')]],
            'DECLINED' => ['action', __('Meta declined the display name.'), [
                __('Open WhatsApp Manager → Phone numbers → your number → Profile.'),
                __('Change the display name so it matches your business or brand, following Meta\'s display name rules.'),
                __('Submit it and wait for the review.'),
            ]],
            default => ['action', __('Display name status: :status', ['status' => $status]), [
                __('Open WhatsApp Manager → Phone numbers and finish the display name setup.'),
            ]],
        };

        return ReadinessItem::make(
            'display_name',
            __('Display name approved'),
            $state,
            __('Meta has to approve the business name customers see before you can send messages at scale.'),
            $how,
            $state === 'done' ? null : self::WHATSAPP_MANAGER,
            ['send', 'templates', 'campaigns'],
            $detail,
        );
    }

    /**
     * health_status says per entity (app, business, WABA, number) whether messages can be sent,
     * with Meta's own explanation and fix.
     *
     * @param  array<string, mixed>  $p
     */
    protected function health(array $p): ?array
    {
        $health = $p['health_status'] ?? null;

        if (! is_array($health)) {
            return null;
        }

        $overall = strtoupper((string) ($health['can_send_message'] ?? 'AVAILABLE'));
        $errors = collect($health['entities'] ?? [])
            ->filter(fn (array $entity): bool => strtoupper((string) ($entity['can_send_message'] ?? 'AVAILABLE')) !== 'AVAILABLE')
            ->flatMap(fn (array $entity): array => collect($entity['errors'] ?? [])
                ->map(fn (array $error): array => $error + ['entity_type' => (string) ($entity['entity_type'] ?? '')])
                ->all());
        $messagingErrors = $errors->reject(fn (array $error): bool => $this->isCallingOnly($error));

        if ($overall === 'LIMITED' && $errors->isNotEmpty() && $messagingErrors->isEmpty()) {
            $overall = 'AVAILABLE';
        }

        $problems = $messagingErrors
            ->map(fn (array $error): string => trim(
                ucfirst(strtolower($error['entity_type'])).': '.($error['error_description'] ?? '').' '.($error['possible_solution'] ?? '')
            ))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $state = match ($overall) {
            'AVAILABLE' => 'done',
            'LIMITED' => 'action',
            default => 'blocked',
        };

        return ReadinessItem::make(
            'can_send',
            __('Meta allows this number to send messages'),
            $state,
            __('Meta checks the app, the business, the WhatsApp account and the number. All of them must be in good standing.'),
            $state === 'done' ? [] : ($problems ?: [__('Open WhatsApp Manager and follow the warning shown for this number.')]),
            $state === 'done' ? null : self::WHATSAPP_MANAGER,
            $state === 'action' ? ['campaigns'] : ['send', 'templates', 'campaigns'],
            $overall === 'LIMITED' ? __('Meta is limiting how much this number can send.') : null,
        );
    }

    /**
     * Meta also reports WhatsApp voice-calling (SIP) problems in health_status; they do not affect messaging.
     *
     * @param  array<string, mixed>  $error
     */
    protected function isCallingOnly(array $error): bool
    {
        $text = strtolower(($error['error_description'] ?? '').' '.($error['possible_solution'] ?? ''));

        return str_contains($text, 'calling') || preg_match('/\bsip\b/', $text) === 1;
    }

    /**
     * @param  array<string, mixed>|null  $w
     */
    protected function accountReview(?array $w): ?array
    {
        if ($w === null || blank($w['account_review_status'] ?? null)) {
            return null;
        }

        $status = strtoupper((string) $w['account_review_status']);
        $state = match ($status) {
            'APPROVED' => 'done',
            'PENDING' => 'waiting',
            default => 'blocked',
        };

        return ReadinessItem::make(
            'account_review',
            __('WhatsApp Business Account approved'),
            $state,
            __('Meta reviews every new WhatsApp Business Account against its commerce and business policies.'),
            match ($state) {
                'done' => [],
                'waiting' => [__('Meta is reviewing the account. Nothing to do yet. Press Re-check later.')],
                default => [__('Open WhatsApp Manager to see why the account was rejected and request a review.')],
            },
            $state === 'done' ? null : self::WHATSAPP_MANAGER,
            ['send', 'templates', 'campaigns'],
        );
    }

    protected function payment(?Response $funding): array
    {
        if (! $funding?->successful()) {
            return ReadinessItem::make(
                'payment',
                __('Payment method added'),
                'unknown',
                __('Template and marketing messages are charged by Meta, so the WhatsApp account needs a payment method.'),
                [__('Open Payment settings in Meta Business and check that a card is attached to this WhatsApp account.')],
                self::PAYMENT_SETTINGS,
                ['templates', 'campaigns'],
                __('Meta did not share the payment status with this app.'),
            );
        }

        $hasFunding = filled($funding->json('primary_funding_id'));

        return ReadinessItem::make(
            'payment',
            __('Payment method added'),
            $hasFunding ? 'done' : 'action',
            __('Template and marketing messages are charged by Meta, so the WhatsApp account needs a payment method.'),
            $hasFunding ? [] : [
                __('Open WhatsApp Manager → Overview → Add payment method (or Billing → Payment settings).'),
                __('Add a card and select this WhatsApp Business Account.'),
                __('Come back and press Re-check.'),
            ],
            $hasFunding ? null : self::PAYMENT_SETTINGS,
            ['templates', 'campaigns'],
            null,
            __('Add payment method'),
        );
    }

    protected function webhooks(?Response $subscriptions): array
    {
        $subscribed = $subscriptions?->successful() && collect($subscriptions->json('data', []))->isNotEmpty();

        return ReadinessItem::make(
            'webhooks',
            __('Incoming messages delivered to the inbox'),
            $subscribed ? 'done' : ($subscriptions?->successful() ? 'action' : 'unknown'),
            __('Meta only forwards messages and delivery updates to apps subscribed to the WhatsApp account.'),
            $subscribed ? [] : [__('Press Sync on this page. It subscribes the app again.'), __('If it still fails, reconnect WhatsApp from Channels.')],
            null,
            ['receive'],
        );
    }

    protected function templates(ChannelAccount $account): array
    {
        $approved = MessageTemplate::query()
            ->where('workspace_id', $account->workspace_id)
            ->where('provider', 'whatsapp')
            ->where('status', MessageTemplateStatus::Approved->value)
            ->count();

        return ReadinessItem::make(
            'templates',
            __('At least one approved message template'),
            $approved > 0 ? 'done' : 'action',
            __('Starting a conversation (or messaging after 24 hours) and every campaign need a template Meta approved.'),
            $approved > 0 ? [] : [__('Open Templates and create one, or press Sync if you already made templates in Meta.'), __('Meta usually reviews templates within minutes, sometimes up to a day.')],
            null,
            ['templates', 'campaigns'],
            $approved > 0 ? trans_choice(':count approved template|:count approved templates', $approved, ['count' => $approved]) : null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $w
     */
    protected function verification(?array $w): ?array
    {
        if ($w === null || blank($w['business_verification_status'] ?? null)) {
            return null;
        }

        $verified = strtolower((string) $w['business_verification_status']) === 'verified';

        return ReadinessItem::make(
            'business_verification',
            __('Business verified by Meta'),
            $verified ? 'done' : 'optional',
            __('Not needed to start. Without it Meta limits you to messaging 250 customers a day and keeps the display name review stricter.'),
            $verified ? [] : [
                __('Open Business Settings → Security Center → Start verification.'),
                __('Upload your business documents (trade licence, utility bill) with the same name and address.'),
            ],
            $verified ? null : self::SECURITY_CENTER,
        );
    }

    protected function unreachable(?Response $response): array
    {
        $error = $this->errors->translate($response?->json(), __('Meta could not be reached.'));

        return ReadinessItem::make(
            'connection',
            __('Meta access to this number'),
            'blocked',
            $error['message'],
            array_filter([$error['action']]),
            $error['link'],
            ['receive', 'send', 'templates', 'campaigns'],
            $error['trace_id'] ? __('Support code: :code', ['code' => $error['trace_id']]) : null,
        );
    }

    protected function qualityLabel(string $rating): string
    {
        return match (strtoupper($rating)) {
            'GREEN' => __('High'),
            'YELLOW' => __('Medium. Fewer customers blocking or reporting you keeps it up.'),
            'RED' => __('Low. Meta may lower your limit. Send fewer, more relevant messages.'),
            default => $rating,
        };
    }

    protected function tierLabel(string $tier): string
    {
        return match (strtoupper($tier)) {
            'TIER_50' => __('50 customers / day'),
            'TIER_250' => __('250 customers / day'),
            'TIER_1K' => __('1,000 customers / day'),
            'TIER_10K' => __('10,000 customers / day'),
            'TIER_100K' => __('100,000 customers / day'),
            'TIER_UNLIMITED' => __('Unlimited'),
            default => $tier,
        };
    }

    protected function safe(callable $request): ?Response
    {
        try {
            return $request();
        } catch (\Throwable) {
            return null;
        }
    }
}
