<?php

namespace App\Modules\Currencies\Services;

use App\Modules\Currencies\Models\Currency;
use App\Modules\PaymentGatewaySettings\Services\PaymentGatewaySettingsService;
use App\Modules\Shared\Traits\HasCrudOperations;
use Illuminate\Support\Facades\Http;

class CurrenciesService
{
    use HasCrudOperations;

    protected string $model = Currency::class;

    /** @var array<string> */
    protected array $searchable = ['code', 'name'];

    /** @var array<string> */
    protected array $filterable = ['is_active'];

    /** @var array<string> */
    protected array $sortable = ['code', 'name', 'exchange_rate', 'sort_order'];

    protected string $defaultSortBy = 'sort_order';

    protected string $defaultSortOrder = 'asc';

    public function syncExchangeRates(): array
    {
        $apiUrl = config('currencies.api.url');
        $apiKey = config('currencies.api.key');

        if (blank($apiKey)) {
            return ['success' => false, 'message' => 'CurrencyLayer API key is not configured.'];
        }

        $codes = Currency::query()->pluck('code')->filter(fn (string $code): bool => $code !== 'USD')->values();

        if ($codes->isEmpty()) {
            return ['success' => false, 'message' => 'No currencies to sync.'];
        }

        $response = Http::timeout(15)->get($apiUrl, [
            'access_key' => $apiKey,
            'currencies' => $codes->implode(','),
            'source' => 'USD',
            'format' => 1,
        ]);

        if ($response->failed() || ! ($response->json('success') ?? false)) {
            $error = $response->json('error.info', 'Unknown API error');

            return ['success' => false, 'message' => "CurrencyLayer API error: {$error}"];
        }

        $quotes = $response->json('quotes', []);
        $updated = 0;

        foreach ($quotes as $key => $rate) {
            $code = str_replace('USD', '', $key);

            $updated += Currency::query()->where('code', $code)->update([
                'exchange_rate' => $rate,
                'rate_synced_at' => now(),
            ]);
        }

        Currency::query()->where('code', 'USD')->update([
            'exchange_rate' => 1,
            'rate_synced_at' => now(),
        ]);

        return ['success' => true, 'message' => "{$updated} currencies updated successfully."];
    }

    public function setDefault(Currency $currency): void
    {
        $currency->update(['is_active' => true]);

        app(PaymentGatewaySettingsService::class)->set('payment_currency', strtoupper($currency->code));
    }
}
