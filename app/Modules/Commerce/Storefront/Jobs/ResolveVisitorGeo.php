<?php

namespace App\Modules\Commerce\Storefront\Jobs;

use App\Modules\Commerce\Storefront\Models\StorefrontVisitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ResolveVisitorGeo implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $visitorId, public string $ip) {}

    public function handle(): void
    {
        if (! filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return;
        }
        $url = (string) config('commerce.geoip.url');
        if ($url === '') {
            return;
        }

        $geo = Cache::remember('commerce:geoip:'.$this->ip, now()->addDays(7), function () use ($url): array {
            try {
                $response = Http::timeout(5)->get(str_replace('{ip}', $this->ip, $url));
                $json = $response->successful() ? $response->json() : [];
            } catch (Throwable) {
                $json = [];
            }

            return [
                'country_code' => $json['countryCode'] ?? $json['country_code'] ?? null,
                'country_name' => $json['country'] ?? $json['country_name'] ?? null,
                'city' => $json['city'] ?? null,
            ];
        });

        $visitor = StorefrontVisitor::query()->find($this->visitorId);
        if (! $visitor || $visitor->ip !== $this->ip) {
            return;
        }
        $updates = array_filter([
            'country_name' => $geo['country_name'] ? mb_substr((string) $geo['country_name'], 0, 100) : null,
            'city' => $geo['city'] ? mb_substr((string) $geo['city'], 0, 120) : null,
        ]);
        if (! $visitor->country_code && is_string($geo['country_code']) && preg_match('/^[A-Z]{2}$/', $geo['country_code'])) {
            $updates['country_code'] = $geo['country_code'];
        }
        if ($updates) {
            $visitor->update($updates);
        }
    }
}
