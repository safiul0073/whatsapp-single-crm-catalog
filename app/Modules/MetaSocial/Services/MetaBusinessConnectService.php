<?php

namespace App\Modules\MetaSocial\Services;

use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MetaSocial\Models\MetaBusinessConnection;
use App\Modules\WhatsAppCloud\Services\ChannelSetupService;
use Illuminate\Http\Client\Response;
use Illuminate\Validation\ValidationException;

/**
 * "Connect your Meta Business": one Facebook Login for Business that discovers every asset the
 * user granted, then turns the chosen ones into channels through the existing per-product services.
 */
class MetaBusinessConnectService
{
    public const ASSET_TYPES = ['whatsapp', 'messenger', 'instagram', 'ad_accounts', 'catalogs'];

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected MetaBusinessClient $client,
        protected MetaSocialSettingsService $settings,
        protected MetaAppSettingsService $metaApp,
        protected MetaSocialSetupService $social,
        protected ChannelSetupService $whatsapp,
    ) {}

    public function ready(): bool
    {
        return filled($this->settings->get('meta_social_app_id'))
            && filled($this->settings->get('meta_social_app_secret'))
            && $this->metaApp->filled('meta_business_config_id') !== null;
    }

    /**
     * @return array{enabled: bool, app_id: string, config_id: string, graph_api_version: string}
     */
    public function signupConfig(): array
    {
        return [
            'enabled' => $this->ready(),
            'app_id' => (string) $this->settings->get('meta_social_app_id', ''),
            'config_id' => (string) $this->metaApp->get('meta_business_config_id', ''),
            'graph_api_version' => $this->settings->graphApiVersion(),
        ];
    }

    public function connection(?User $user): ?MetaBusinessConnection
    {
        return MetaBusinessConnection::query()
            ->where('workspace_id', $this->workspaces->current($user)->id)
            ->first();
    }

    public function connect(?User $user, string $code): MetaBusinessConnection
    {
        if (! $this->ready()) {
            throw ValidationException::withMessages([
                'meta_business' => __('Connecting a Meta Business is not configured by the administrator.'),
            ]);
        }

        $response = $this->client->exchangeCode($code);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw ValidationException::withMessages([
                'meta_business' => __('Meta code exchange failed. Please try connecting again.'),
            ]);
        }

        $token = $this->longLivedToken((string) $response->json('access_token'));

        return $this->store($user, $token);
    }

    /**
     * Re-reads the assets with the stored token, e.g. after the user added a Page in Meta.
     */
    public function refresh(?User $user): MetaBusinessConnection
    {
        $connection = $this->connection($user);
        $token = $connection?->accessToken();

        if (! $token) {
            throw ValidationException::withMessages([
                'meta_business' => __('Connect your Meta Business first.'),
            ]);
        }

        return $this->store($user, $token);
    }

    /**
     * Turns the chosen assets into channels. One product failing does not stop the others.
     *
     * @param  array<string, mixed>  $selection
     * @return array{connected: array<int, string>, channels: array<int, ChannelAccount>, errors: array<int, string>}
     */
    public function activate(?User $user, array $selection, bool $canManageWhatsApp): array
    {
        $connection = $this->connection($user);
        $token = $connection?->accessToken();

        if (! $connection || ! $token) {
            throw ValidationException::withMessages([
                'meta_business' => __('Connect your Meta Business first.'),
            ]);
        }

        $connected = [];
        $channels = [];
        $errors = [];
        $whatsapp = collect($connection->assetsOfType('whatsapp'))->firstWhere('id', (string) ($selection['whatsapp'] ?? ''));

        if ($whatsapp && $canManageWhatsApp) {
            $this->attempt($errors, function () use ($user, $token, $whatsapp, $connection, &$connected, &$channels): void {
                $result = $this->whatsapp->connectWithToken($user, $token, [
                    'waba_id' => $whatsapp['waba_id'],
                    'phone_number_id' => $whatsapp['phone_number_id'],
                    'business_id' => $connection->business_id,
                    'display_name' => $whatsapp['verified_name'] ?? null,
                ], source: 'meta_business');
                $connected[] = $result['channel']->name;
                $channels[] = $result['channel'];
            });
        }

        foreach (['messenger', 'instagram'] as $provider) {
            $ids = $this->grantedIds($connection, $provider, $selection[$provider] ?? []);

            if ($ids === []) {
                continue;
            }

            $this->attempt($errors, function () use ($user, $provider, $token, $ids, &$connected, &$channels): void {
                $accounts = $this->social->connectGrantedAccounts($user, $provider, $token, $ids, 'meta_business');
                array_push($connected, ...$accounts->pluck('name')->all());
                array_push($channels, ...$accounts->all());
            });
        }

        $connection->update([
            'selected' => [
                'whatsapp' => $whatsapp ? [$whatsapp['id']] : $connection->selectedOfType('whatsapp'),
                'messenger' => $this->grantedIds($connection, 'messenger', $selection['messenger'] ?? []),
                'instagram' => $this->grantedIds($connection, 'instagram', $selection['instagram'] ?? []),
                'ad_accounts' => $this->grantedIds($connection, 'ad_accounts', $selection['ad_accounts'] ?? []),
                'catalogs' => $this->grantedIds($connection, 'catalogs', $selection['catalogs'] ?? []),
            ],
        ]);

        return ['connected' => $connected, 'channels' => $channels, 'errors' => $errors];
    }

    /**
     * Removes the business login. Channels already connected keep their own tokens and stay connected.
     */
    public function disconnect(?User $user): void
    {
        $this->connection($user)?->delete();
    }

    /**
     * The IDs of channels already connected in this workspace, so the picker can pre-tick them.
     *
     * @return array<string, array<int, string>>
     */
    public function activeIds(?User $user): array
    {
        $accounts = ChannelAccount::query()
            ->where('workspace_id', $this->workspaces->current($user)->id)
            ->whereIn('provider', ['whatsapp', 'messenger', 'instagram'])
            ->where('status', 'connected')
            ->get(['provider', 'provider_account_id', 'provider_phone_id']);

        return [
            'whatsapp' => $accounts->where('provider', 'whatsapp')->map(fn (ChannelAccount $account): string => $account->provider_account_id.'|'.$account->provider_phone_id)->values()->all(),
            'messenger' => $accounts->where('provider', 'messenger')->pluck('provider_account_id')->map(fn ($id): string => (string) $id)->values()->all(),
            'instagram' => $accounts->where('provider', 'instagram')->pluck('provider_account_id')->map(fn ($id): string => (string) $id)->values()->all(),
        ];
    }

    protected function store(?User $user, string $token): MetaBusinessConnection
    {
        $workspace = $this->workspaces->current($user);
        $discovery = $this->discover($token);
        $business = $discovery['businesses'][0] ?? null;

        return MetaBusinessConnection::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'business_id' => $business['id'] ?? null,
                'business_name' => $business['name'] ?? null,
                'status' => 'connected',
                'credentials' => ['access_token' => $token],
                'scopes' => $discovery['scopes'],
                'assets' => $discovery['assets'],
                'last_error' => null,
                'connected_at' => now(),
                'last_synced_at' => now(),
            ]
        );
    }

    /**
     * @return array{scopes: array<int, string>, businesses: array<int, array{id: string, name: string}>, assets: array<string, array<int, array<string, mixed>>>}
     */
    protected function discover(string $token): array
    {
        $debug = $this->json(fn (): Response => $this->client->debugToken($token), 'data') ?? [];
        $scopes = array_values(array_map('strval', $debug['scopes'] ?? []));
        $granular = collect($debug['granular_scopes'] ?? []);
        $targets = fn (string $scope): array => array_values(array_map('strval', $granular->firstWhere('scope', $scope)['target_ids'] ?? []));

        $businesses = $this->businesses($token, $targets('business_management'));
        $pages = collect($this->json(fn (): Response => $this->client->pageAccounts($token), 'data') ?? []);

        return [
            'scopes' => $scopes,
            'businesses' => $businesses,
            'assets' => [
                'whatsapp' => $this->whatsappNumbers($token, $targets('whatsapp_business_management'), $businesses),
                'messenger' => $pages
                    ->filter(fn (array $page): bool => filled($page['id'] ?? null))
                    ->map(fn (array $page): array => array_filter([
                        'id' => (string) $page['id'],
                        'name' => (string) ($page['name'] ?? $page['id']),
                        // Page roles: only people with the Messages (or full control) task can read and send messages.
                        'can_message' => is_array($page['tasks'] ?? null) ? (array_intersect(['MESSAGING', 'MODERATE', 'MANAGE'], $page['tasks']) !== [] ? '1' : '0') : null,
                    ], fn ($value): bool => $value !== null))
                    ->values()->all(),
                'instagram' => $pages
                    ->filter(fn (array $page): bool => filled(data_get($page, 'instagram_business_account.id')))
                    ->map(fn (array $page): array => [
                        'id' => (string) data_get($page, 'instagram_business_account.id'),
                        'name' => (string) (data_get($page, 'instagram_business_account.name') ?: data_get($page, 'instagram_business_account.username') ?: $page['name'] ?? ''),
                        'username' => (string) data_get($page, 'instagram_business_account.username', ''),
                        'page_name' => (string) ($page['name'] ?? ''),
                    ])
                    ->values()->all(),
                'ad_accounts' => array_intersect(['ads_read', 'ads_management'], $scopes) === [] ? [] : collect($this->json(fn (): Response => $this->client->adAccounts($token), 'data') ?? [])
                    ->filter(fn (array $account): bool => filled($account['id'] ?? null))
                    ->map(fn (array $account): array => [
                        'id' => (string) $account['id'],
                        'name' => (string) ($account['name'] ?? $account['id']),
                        'currency' => (string) ($account['currency'] ?? ''),
                    ])
                    ->values()->all(),
                'catalogs' => $this->catalogs($token, $targets('catalog_management'), $businesses, in_array('catalog_management', $scopes, true)),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $grantedIds
     * @return array<int, array{id: string, name: string}>
     */
    protected function businesses(string $token, array $grantedIds): array
    {
        $listed = collect($this->json(fn (): Response => $this->client->businesses($token), 'data') ?? [])
            ->map(fn (array $business): array => ['id' => (string) ($business['id'] ?? ''), 'name' => (string) ($business['name'] ?? '')])
            ->filter(fn (array $business): bool => $business['id'] !== '')
            ->keyBy('id');

        foreach ($grantedIds as $id) {
            if (! $listed->has($id)) {
                $node = $this->json(fn (): Response => $this->client->node($id, $token, 'id,name')) ?? [];
                $listed->put($id, ['id' => $id, 'name' => (string) ($node['name'] ?? $id)]);
            }
        }

        return $listed->values()->all();
    }

    /**
     * @param  array<int, string>  $wabaIds
     * @param  array<int, array{id: string, name: string}>  $businesses
     * @return array<int, array<string, string>>
     */
    protected function whatsappNumbers(string $token, array $wabaIds, array $businesses): array
    {
        $wabas = collect($wabaIds)->map(fn (string $id): array => ['id' => $id, 'name' => (string) data_get($this->json(fn (): Response => $this->client->node($id, $token, 'id,name')), 'name', $id)]);

        if ($wabas->isEmpty()) {
            $wabas = collect($businesses)->flatMap(fn (array $business): array => $this->json(fn (): Response => $this->client->ownedWhatsAppAccounts($business['id'], $token), 'data') ?? []);
        }

        return $wabas
            ->filter(fn (array $waba): bool => filled($waba['id'] ?? null))
            ->unique('id')
            ->flatMap(fn (array $waba): array => collect($this->json(fn (): Response => $this->client->phoneNumbers((string) $waba['id'], $token), 'data') ?? [])
                ->filter(fn (array $number): bool => filled($number['id'] ?? null))
                ->map(fn (array $number): array => [
                    'id' => $waba['id'].'|'.$number['id'],
                    'waba_id' => (string) $waba['id'],
                    'waba_name' => (string) ($waba['name'] ?? $waba['id']),
                    'phone_number_id' => (string) $number['id'],
                    'display_phone_number' => (string) ($number['display_phone_number'] ?? ''),
                    'verified_name' => (string) ($number['verified_name'] ?? ''),
                    'name_status' => strtoupper((string) ($number['name_status'] ?? '')),
                    'status' => strtoupper((string) ($number['status'] ?? '')),
                ])
                ->all())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $catalogIds
     * @param  array<int, array{id: string, name: string}>  $businesses
     * @return array<int, array{id: string, name: string}>
     */
    protected function catalogs(string $token, array $catalogIds, array $businesses, bool $granted): array
    {
        if (! $granted) {
            return [];
        }

        $catalogs = collect($catalogIds)->map(fn (string $id): array => ['id' => $id, 'name' => (string) data_get($this->json(fn (): Response => $this->client->node($id, $token, 'id,name')), 'name', $id)]);

        if ($catalogs->isEmpty()) {
            $catalogs = collect($businesses)->flatMap(fn (array $business): array => $this->json(fn (): Response => $this->client->ownedCatalogs($business['id'], $token), 'data') ?? []);
        }

        return $catalogs
            ->filter(fn (array $catalog): bool => filled($catalog['id'] ?? null))
            ->map(fn (array $catalog): array => ['id' => (string) $catalog['id'], 'name' => (string) ($catalog['name'] ?? $catalog['id'])])
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    protected function grantedIds(MetaBusinessConnection $connection, string $type, array $ids): array
    {
        $granted = collect($connection->assetsOfType($type))->pluck('id')->map(fn ($id): string => (string) $id);

        return collect($ids)->map(fn ($id): string => (string) $id)->unique()->filter(fn (string $id): bool => $granted->contains($id))->values()->all();
    }

    /**
     * Discovery is best effort: a scope the user did not grant just yields no assets of that type.
     */
    protected function json(callable $request, ?string $key = null): mixed
    {
        try {
            $response = $request();
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $key ? $response->json($key) : $response->json();
    }

    /**
     * @param  array<int, string>  $errors
     */
    protected function attempt(array &$errors, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            array_push($errors, ...collect($exception->errors())->flatten()->all());
        }
    }

    protected function longLivedToken(string $token): string
    {
        $exchanged = $this->json(fn (): Response => $this->client->exchangeLongLivedToken($token), 'access_token');

        return filled($exchanged) ? (string) $exchanged : $token;
    }
}
