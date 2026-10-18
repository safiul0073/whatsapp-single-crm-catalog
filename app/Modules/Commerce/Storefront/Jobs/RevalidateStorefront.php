<?php

namespace App\Modules\Commerce\Storefront\Jobs;

use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
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
        if (app()->environment('testing')) {
            return;
        }

        $deployment = (array) (StorefrontSetting::query()
            ->where('workspace_id', $this->workspace)
            ->value('config')['deployment'] ?? []);

        $secret = (string) config('storefront.revalidation_secret');
        if (strlen($secret) < 32) {
            $secret = (string) ($deployment['revalidation_secret'] ?? '');
        }
        if (strlen($secret) < 32) {
            return;
        }
        $storefrontUrl = rtrim((string) (($deployment['storefront_url'] ?? '') ?: config('storefront.url')), '/');
        $body = json_encode(['store_id' => $this->workspace, 'tags' => $this->tags, 'timestamp' => time()], JSON_THROW_ON_ERROR);
        Http::timeout(10)->withHeaders(['X-Signature' => hash_hmac('sha256', $body, $secret)])->withBody($body, 'application/json')->post($storefrontUrl.'/api/revalidate')->throw();
    }
}
