<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Storefront\Models\StoreDomain;

/**
 * Builds customer-facing storefront links (the Next.js store host, not the dashboard).
 */
class StorefrontUrl
{
    /** @var array<int, string> */
    protected array $bases = [];

    public function base(int $workspaceId): string
    {
        if (isset($this->bases[$workspaceId])) {
            return $this->bases[$workspaceId];
        }

        $configured = parse_url((string) config('storefront.url')) ?: [];
        $host = StoreDomain::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_primary', true)
            ->where('status', 'active')
            ->value('host');

        $base = $host
            ? ($configured['scheme'] ?? 'https').'://'.$host.(isset($configured['port']) ? ':'.$configured['port'] : '')
            : rtrim((string) config('storefront.url'), '/');

        return $this->bases[$workspaceId] = rtrim($base, '/');
    }

    public function product(int $workspaceId, string $slug): string
    {
        return $this->base($workspaceId).'/products/'.rawurlencode($slug);
    }

    public function path(int $workspaceId, string $path): string
    {
        return $this->base($workspaceId).'/'.ltrim($path, '/');
    }

    /**
     * Extracts the product slug from a storefront page URL such as https://shop.test/en/products/blue-shirt?color=4.
     */
    public static function productSlugFromUrl(?string $url): ?string
    {
        $path = (string) parse_url((string) $url, PHP_URL_PATH);

        if (preg_match('#/products/([^/?\#]+)/?$#', $path, $matches) !== 1) {
            return null;
        }

        $slug = rawurldecode($matches[1]);

        return preg_match('/^[\pL\pN][\pL\pN_-]{0,190}$/u', $slug) === 1 ? $slug : null;
    }
}
