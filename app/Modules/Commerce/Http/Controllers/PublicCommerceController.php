<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Services\CatalogFeedService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCommerceController extends Controller
{
    public function feed(string $token, CatalogFeedService $feeds): StreamedResponse
    {
        $catalog = Catalog::query()->where('feed_token', $token)->where('is_active', true)->firstOrFail();

        return $feeds->response($catalog);
    }

    public function product(string $product): View
    {
        $record = Product::query()->with(['primaryMedia', 'gallery.media', 'variants.media'])->where('slug', $product)->where('status', 'active')->firstOrFail();
        $catalog = Catalog::query()->with('channelAccount')->where('workspace_id', $record->workspace_id)->where('is_active', true)->first();
        $phone = preg_replace('/\D+/', '', (string) $catalog?->channelAccount?->provider_display_id);

        return view('commerce::public.product', ['product' => $record, 'whatsappPhone' => $phone]);
    }
}
