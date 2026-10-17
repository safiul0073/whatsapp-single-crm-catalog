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
        $txt = dns_get_record('_verify.'.$d->host, DNS_TXT) ?: [];
        $cname = dns_get_record($d->host, DNS_CNAME) ?: [];
        $target = strtolower(rtrim((string) config('storefront.dns_target'), '.'));
        $verified = collect($txt)->contains(fn ($r) => hash_equals($d->verification_token, $r['txt'] ?? '')) && $target !== '' && collect($cname)->contains(fn ($r) => strtolower(rtrim($r['target'] ?? '', '.')) === $target);
        if ($verified) {
            $d->update(['status' => 'active', 'verified_at' => now()]);
        } elseif ($this->attempts() < 12) {
            $this->release(300);
        } else {
            $d->update(['status' => 'failed']);
        }
    }
}
