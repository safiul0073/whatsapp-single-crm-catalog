<?php

namespace App\Modules\MetaSocial\Services;

use App\Modules\Inbox\Models\Message;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ReadinessChecker;
use App\Modules\MarketingChannels\Services\ChannelReadiness\ReadinessItem;
use App\Modules\MarketingChannels\Services\MetaErrorTranslator;
use Illuminate\Http\Client\Response;

/**
 * What a connected Facebook Page (Messenger) or Instagram account still needs before messages
 * flow both ways: Page webhook subscription, granted permissions and Instagram message access.
 */
class MetaSocialReadinessChecker implements ReadinessChecker
{
    protected const MESSENGER_SCOPES = ['pages_messaging', 'pages_manage_metadata'];

    protected const INSTAGRAM_SCOPES = ['instagram_basic', 'instagram_manage_messages', 'pages_manage_metadata'];

    public function __construct(
        protected string $provider,
        protected MetaSocialClient $client,
        protected MetaBusinessClient $business,
        protected MetaErrorTranslator $errors,
    ) {}

    public function provider(): string
    {
        return $this->provider;
    }

    public function check(ChannelAccount $account): array
    {
        $token = (string) $account->credential('access_token');
        $isInstagram = $this->provider === 'instagram';
        $pageId = (string) (data_get($account->settings, 'page_id') ?: $account->provider_phone_id ?: ($isInstagram ? '' : $account->provider_account_id));

        if ($token === '') {
            return ['items' => [ReadinessItem::make(
                'connection',
                $isInstagram ? __('Connect Instagram with Meta') : __('Connect your Facebook Page with Meta'),
                'blocked',
                __('The channel has no Meta access yet.'),
                [__('Go to Channels and use Connect with Meta.')],
                null,
                ['receive', 'send'],
            )]];
        }

        $profile = $this->safe(fn (): Response => $this->client->account((string) $account->provider_account_id, $token, $isInstagram ? ['id', 'username', 'name'] : ['id', 'name']));

        if (! $profile?->successful()) {
            return ['items' => [$this->unreachable($profile, $isInstagram)]];
        }

        $items = [];

        if ($isInstagram) {
            $items[] = ReadinessItem::make(
                'linked_page',
                __('Instagram linked to a Facebook Page'),
                $pageId !== '' ? 'done' : 'blocked',
                __('Meta delivers Instagram messages through the Facebook Page the account is linked to.'),
                $pageId !== '' ? [] : [
                    __('In the Instagram app: Settings → Account type and tools → switch to a Professional account.'),
                    __('In Meta Business Suite → Settings → Instagram accounts, connect it to your Facebook Page.'),
                    __('Reconnect with Meta and pick both the Page and the Instagram account.'),
                ],
                $pageId !== '' ? null : 'https://business.facebook.com/settings/instagram-account-v2s',
                ['receive', 'send'],
            );
        }

        $items[] = $this->permissions($token, $isInstagram);
        $items[] = $this->subscription($pageId, $token);

        if ($isInstagram) {
            $items[] = $this->instagramMessageAccess($account);
        }

        return [
            'items' => array_values(array_filter($items)),
            'facts' => array_values(array_filter([
                ['label' => $isInstagram ? __('Account') : __('Page'), 'value' => (string) ($isInstagram ? '@'.$profile->json('username', '') : $profile->json('name', $account->name))],
            ])),
            'notes' => [
                __('Replies are allowed for 24 hours after the customer\'s last message. After that Meta only allows replies tagged as a human agent within 7 days.'),
                __('Meta only lets apps message people who wrote to the Page or account first.'),
            ],
        ];
    }

    protected function permissions(string $token, bool $isInstagram): array
    {
        $required = $isInstagram ? self::INSTAGRAM_SCOPES : self::MESSENGER_SCOPES;
        $debug = $this->safe(fn (): Response => $this->business->debugToken($token));
        $label = __('Messaging permissions granted');
        $why = __('The Meta login must allow the app to read and send messages for this account.');

        if (! $debug?->successful() || ! is_array($debug->json('data.scopes'))) {
            return ReadinessItem::make('permissions', $label, 'unknown', $why, [__('Press Re-check. If it keeps failing, reconnect with Meta.')], null, ['receive', 'send']);
        }

        $missing = array_values(array_diff($required, $debug->json('data.scopes')));

        return ReadinessItem::make(
            'permissions',
            $label,
            $missing === [] ? 'done' : 'blocked',
            $why,
            $missing === [] ? [] : [
                __('Reconnect with Meta.'),
                __('In the Meta window choose "Edit access" and keep every permission and account checked.'),
            ],
            null,
            ['receive', 'send'],
            $missing === [] ? null : __('Missing: :scopes', ['scopes' => implode(', ', $missing)]),
        );
    }

    protected function subscription(string $pageId, string $token): array
    {
        $label = __('Incoming messages delivered to the inbox');
        $why = __('Meta only forwards Page and Instagram messages to apps the Page is subscribed to.');

        if ($pageId === '') {
            return ReadinessItem::make('webhooks', $label, 'blocked', $why, [__('Link a Facebook Page first.')], null, ['receive']);
        }

        $response = $this->safe(fn (): Response => $this->client->pageSubscriptions($pageId, $token));
        $subscribed = $response?->successful() && collect($response->json('data', []))->isNotEmpty();

        return ReadinessItem::make(
            'webhooks',
            $label,
            $subscribed ? 'done' : ($response?->successful() ? 'action' : 'unknown'),
            $why,
            $subscribed ? [] : [__('Reconnect with Meta. The app subscribes the Page automatically when it connects.'), __('Make sure you are an admin of the Page, or have full control in Business Settings.')],
            null,
            ['receive'],
        );
    }

    /**
     * Meta has no API that says whether "Allow access to messages" is on, so it counts as done
     * once a DM arrived through the API.
     */
    protected function instagramMessageAccess(ChannelAccount $account): array
    {
        $received = Message::query()
            ->where('channel_account_id', $account->id)
            ->where('direction', 'inbound')
            ->exists();

        return ReadinessItem::make(
            'instagram_message_access',
            __('"Allow access to messages" turned on in Instagram'),
            $received ? 'done' : 'action',
            __('Instagram keeps DMs away from connected apps until the account owner allows it.'),
            $received ? [] : [
                __('Open the Instagram app on the phone with this account.'),
                __('Go to Settings and activity → Messages and story replies → Message controls.'),
                __('Under Connected tools, turn on "Allow access to messages".'),
                __('Send a test DM to the account from another Instagram account. This item turns green when it arrives.'),
            ],
            null,
            ['receive', 'send'],
            $received ? null : __('Meta does not report this setting, so it is confirmed when the first DM arrives.'),
        );
    }

    protected function unreachable(?Response $response, bool $isInstagram): array
    {
        $error = $this->errors->translate($response?->json(), __('Meta could not be reached.'));

        return ReadinessItem::make(
            'connection',
            $isInstagram ? __('Meta access to this Instagram account') : __('Meta access to this Page'),
            'blocked',
            $error['message'],
            array_filter([$error['action']]),
            $error['link'],
            ['receive', 'send'],
            $error['trace_id'] ? __('Support code: :code', ['code' => $error['trace_id']]) : null,
        );
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
