<?php

namespace App\Modules\Commerce\Providers;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Storefront\Console\ImportLegacy;
use App\Modules\Commerce\Storefront\Console\ProvisionStore;
use App\Modules\Commerce\Storefront\Jobs\RevalidateStorefront;
use App\Modules\Commerce\Storefront\Services\CustomerService;
use App\Modules\Commerce\Storefront\Services\ProvisionStoreService;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Shared\Support\BasePanelModuleProvider;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class CommerceServiceProvider extends BasePanelModuleProvider
{
    protected function bootModule(array $module): void
    {
        Route::middleware('api')->group(__DIR__.'/../Routes/storefront.php');
        RateLimiter::for('storefront', fn ($r) => Limit::perMinute(240)->by(hash('sha256', $r->header('X-Store-Host', '').':'.$r->header('X-Storefront-Client-IP', $r->ip()))));
        RateLimiter::for('storefront-auth', fn ($r) => Limit::perMinute(20)->by(hash('sha256', $r->header('X-Store-Host', '').':'.$r->header('X-Storefront-Client-IP', $r->ip()))));
        Workspace::created(function ($workspace): void {
            if (Schema::hasTable('commerce_store_domains')) {
                app(ProvisionStoreService::class)->provision($workspace);
            }
        });
        Order::created(function ($order): void {
            if ($order->contact_id && Schema::hasTable('commerce_customers')) {
                $contact = Contact::query()->where('workspace_id', $order->workspace_id)->find($order->contact_id);
                if ($contact) {
                    app(CustomerService::class)->enroll($contact);
                }
            }
        });
        Product::saved(fn ($product) => RevalidateStorefront::dispatch($product->workspace_id, ['all'])->afterCommit());
        ProductVariant::saved(fn ($variant) => RevalidateStorefront::dispatch($variant->workspace_id, ['all'])->afterCommit());
        $this->commands([ProvisionStore::class, ImportLegacy::class]);
    }

    public function register(): void
    {
        //
    }
}
