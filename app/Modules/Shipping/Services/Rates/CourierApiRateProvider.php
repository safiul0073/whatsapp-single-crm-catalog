<?php

namespace App\Modules\Shipping\Services\Rates;

use App\Modules\Shipping\Models\CarrierAccount;
use App\Modules\Shipping\Models\RemoteArea;
use App\Modules\Shipping\Models\Shipment;
use App\Modules\Shipping\Services\Carriers\CarrierApiException;
use App\Modules\Shipping\Services\Carriers\CarrierClientManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks every connected courier account for live prices. One courier failing never
 * blocks the others: the reason is kept on the account and reported back.
 */
class CourierApiRateProvider implements RateProvider
{
    /** @var list<array{account: string, message: string}> */
    private array $failures = [];

    public function __construct(private CarrierClientManager $clients) {}

    public function quotes(Shipment $shipment): array
    {
        $this->failures = [];
        $shipment->loadMissing('cartons');
        $quotes = [];

        $accounts = CarrierAccount::query()->where('workspace_id', $shipment->workspace_id)->active()->orderBy('id')->get();
        foreach ($accounts as $account) {
            if (! $account->isReadyForLiveRates()) {
                continue;
            }

            try {
                $found = $this->clients->for($account)->rates($account, $shipment);
                $account->forceFill(['last_success_at' => now(), 'last_error' => null, 'last_error_at' => null])->saveQuietly();
                if ($found === []) {
                    $this->failures[] = ['account' => $account->name, 'message' => 'No service is available for this destination.'];
                }
                $this->rememberRemoteStatus($account, $shipment, $found);
                array_push($quotes, ...$found);
            } catch (CarrierApiException $exception) {
                $this->fail($account, $exception->summary());
            } catch (Throwable $exception) {
                Log::error('Shipping courier rate request failed', ['account_id' => $account->id, 'carrier' => $account->carrier, 'error' => $exception->getMessage()]);
                $this->fail($account, 'Unexpected error while reading the courier response.');
            }
        }

        return $quotes;
    }

    /**
     * @return list<array{account: string, message: string}>
     */
    public function failures(): array
    {
        return $this->failures;
    }

    private function fail(CarrierAccount $account, string $message): void
    {
        $account->forceFill(['last_error' => mb_substr($message, 0, 500), 'last_error_at' => now()])->saveQuietly();
        $this->failures[] = ['account' => $account->name, 'message' => $message];
    }

    /**
     * A courier's own itemised answer is the confirmation the remote-area checker needs,
     * so it is saved for this exact postal code.
     *
     * @param  list<array<string, mixed>>  $quotes
     */
    private function rememberRemoteStatus(CarrierAccount $account, Shipment $shipment, array $quotes): void
    {
        $statuses = array_unique(array_column($quotes, 'remote_status'));
        if ($quotes === [] || blank($shipment->postal_code) || in_array('unknown', $statuses, true)) {
            return;
        }

        $remoteQuote = collect($quotes)->firstWhere('remote_status', 'remote');
        $fee = $remoteQuote ? collect($remoteQuote['charges'])->where('reason', 'remote_area')->sum(fn (array $charge): float => (float) $charge['amount']) : 0;

        RemoteArea::query()->updateOrCreate(
            [
                'workspace_id' => $shipment->workspace_id,
                'carrier' => $account->carrier,
                'shipping_agent_id' => null,
                'country_code' => strtoupper((string) $shipment->country_code),
                'postal_from' => $shipment->postal_code,
                'postal_to' => $shipment->postal_code,
            ],
            [
                'status' => $remoteQuote ? 'remote' : 'normal',
                'fee' => $fee,
                'currency' => $remoteQuote['currency'] ?? $quotes[0]['currency'],
                'verified_at' => now(),
                'notes' => 'Confirmed by the '.$account->carrierLabel().' rate API',
            ],
        );
    }
}
