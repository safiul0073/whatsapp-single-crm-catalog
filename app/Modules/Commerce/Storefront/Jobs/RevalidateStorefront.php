<?php

namespace App\Modules\Commerce\Storefront\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class RevalidateStorefront implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $workspace, public array $tags) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $secret = (string) config('storefront.revalidation_secret');
        if (strlen($secret) < 32) {
            return;
        }
        $body = json_encode(['store_id' => $this->workspace, 'tags' => $this->tags, 'timestamp' => time()], JSON_THROW_ON_ERROR);
        Http::timeout(10)->withHeaders(['X-Signature' => hash_hmac('sha256', $body, $secret)])->withBody($body, 'application/json')->post(rtrim(config('storefront.url'), '/').'/api/revalidate')->throw();
    }
}
