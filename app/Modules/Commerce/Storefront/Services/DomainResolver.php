<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Storefront\Models\StoreDomain;
use App\Modules\Workspaces\Models\Workspace;

class DomainResolver
{
    public static function normalize(string $host): string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        abort_unless(strlen($host) <= 253 && preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D', $host), 422, 'Invalid hostname.');

        return $host;
    }

    public function resolve(string $host): array
    {
        $host = self::normalize($host);
        // A verified apex also serves its www sibling (and the reverse); the proxy then redirects to the primary host.
        $domain = StoreDomain::query()->where('host', $host)->where('status', 'active')->first()
            ?? StoreDomain::query()->where('host', self::sibling($host))->where('status', 'active')->firstOrFail();
        $workspace = Workspace::query()->where('status', 'active')->findOrFail($domain->workspace_id);
        $primary = StoreDomain::query()->where('workspace_id', $workspace->id)->where('status', 'active')->where('is_primary', true)->firstOrFail();

        return ['store_id' => $workspace->id, 'name' => $workspace->name, 'primary_host' => $primary->host, 'host' => $host, 'status' => 'active'];
    }

    public static function sibling(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host;
    }
}
