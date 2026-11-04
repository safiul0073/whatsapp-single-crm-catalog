<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Jobs\ResolveVisitorGeo;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\StorefrontPageView;
use App\Modules\Commerce\Storefront\Models\StorefrontVisitor;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class VisitorTrackingService
{
    /**
     * @param  array{visitor_key: string, path: string, referrer?: string|null}  $data
     */
    public function record(Request $request, int $workspaceId, array $data): StorefrontVisitor
    {
        $ip = $this->clientIp($request);
        $agent = Str::limit((string) ($request->header('X-Client-User-Agent') ?: $request->userAgent()), 495, '');
        $country = strtoupper((string) $request->header('X-Client-Country'));
        $country = preg_match('/^[A-Z]{2}$/', $country) && ! in_array($country, ['XX', 'T1'], true) ? $country : null;
        $customerId = $this->customerId($request, $workspaceId);
        $now = now();

        $visitor = StorefrontVisitor::query()->firstOrNew(['workspace_id' => $workspaceId, 'visitor_key' => $data['visitor_key']]);
        $ipChanged = $visitor->ip !== $ip;
        $visitor->fill([
            'ip' => $ip,
            'user_agent' => $agent ?: null,
            'device' => $this->device($agent),
            'last_seen_at' => $now,
        ]);
        if (! $visitor->exists) {
            $visitor->first_seen_at = $now;
        }
        if ($customerId) {
            $visitor->customer_id = $customerId;
        }
        if ($country) {
            $visitor->country_code = $country;
        }
        $visitor->save();

        StorefrontPageView::query()->create([
            'workspace_id' => $workspaceId,
            'visitor_id' => $visitor->id,
            'customer_id' => $customerId ?? $visitor->customer_id,
            'path' => Str::limit($data['path'], 495, ''),
            'referrer' => isset($data['referrer']) ? Str::limit($data['referrer'], 495, '') : null,
            'ip' => $ip,
            'country_code' => $visitor->country_code,
            'viewed_at' => $now,
        ]);

        if ($ip && $ipChanged) {
            ResolveVisitorGeo::dispatch($visitor->id, $ip);
        }

        return $visitor;
    }

    private function clientIp(Request $request): ?string
    {
        $ip = trim((string) $request->header('X-Client-Ip'));

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : $request->ip();
    }

    /** Resolves the customer without rejecting the request when the token is missing or invalid. */
    private function customerId(Request $request, int $workspaceId): ?int
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }
        $token = PersonalAccessToken::findToken($bearer);
        if (! $token || ! $token->can('storefront') || ($token->expires_at && $token->expires_at->isPast())) {
            return null;
        }
        $customer = $token->tokenable;

        return $customer instanceof Customer && $customer->workspace_id === $workspaceId ? $customer->id : null;
    }

    private function device(string $agent): ?string
    {
        if ($agent === '') {
            return null;
        }
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $agent)) {
            return 'bot';
        }
        if (preg_match('/ipad|tablet/i', $agent)) {
            return 'tablet';
        }

        return preg_match('/mobi|android|iphone/i', $agent) ? 'mobile' : 'desktop';
    }
}
