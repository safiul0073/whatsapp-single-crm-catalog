<?php

namespace App\Modules\Commerce\Storefront\Jobs;

use App\Modules\Commerce\Storefront\Models\StoreDomain;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class VerifyStoreDomainJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 12;

    public function __construct(public int $domainId) {}

    public function handle(): void
    {
        $d = StoreDomain::query()->find($this->domainId);
        if (! $d || $d->type === 'system' || $d->status === 'active') {
            return;
        }
        $d->update(['status' => 'verifying']);
        $target = strtolower(rtrim((string) config('storefront.dns_target'), '.'));

        $error = null;
        if ($target === '') {
            $error = 'target_unset';
        } elseif (! $this->ownershipProven($d)) {
            $error = 'txt_missing';
        } elseif (! $this->routesToTarget($d->host, $target)) {
            $error = 'routing_missing';
        }

        if ($error === null) {
            $d->update(['status' => 'active', 'verified_at' => now(), 'verification_error' => null]);
        } elseif ($this->attempts() < 12) {
            $d->update(['verification_error' => $error]);
            $this->release(300);
        } else {
            $d->update(['status' => 'failed', 'verification_error' => $error]);
        }
    }

    /**
     * Ownership is proven by the TXT token, or by an active www/apex sibling in the same workspace.
     */
    private function ownershipProven(StoreDomain $d): bool
    {
        $siblingHost = str_starts_with($d->host, 'www.') ? substr($d->host, 4) : 'www.'.$d->host;
        $siblingActive = StoreDomain::query()
            ->where('workspace_id', $d->workspace_id)
            ->where('host', $siblingHost)
            ->where('status', 'active')
            ->exists();
        if ($siblingActive) {
            return true;
        }

        return collect($this->lookup('_verify.'.$d->host, DNS_TXT))
            ->contains(fn ($r) => hash_equals($d->verification_token, $r['txt'] ?? ''));
    }

    /**
     * Passes on a CNAME chain reaching the target, or when the host's A records overlap the target's
     * (covers apex ALIAS/flattened and proxied DNS).
     */
    private function routesToTarget(string $host, string $target): bool
    {
        $current = strtolower($host);
        for ($hop = 0; $hop < 5; $hop++) {
            $next = $this->lookup($current, DNS_CNAME)[0]['target'] ?? null;
            if ($next === null) {
                break;
            }
            $current = strtolower(rtrim($next, '.'));
            if ($current === $target) {
                return true;
            }
        }

        $targetIps = collect($this->lookup($target, DNS_A))->pluck('ip');
        $hostIps = collect($this->lookup($host, DNS_A))->pluck('ip');

        return $targetIps->isNotEmpty() && $hostIps->intersect($targetIps)->isNotEmpty();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function lookup(string $host, int $type): array
    {
        return dns_get_record($host, $type) ?: [];
    }
}
