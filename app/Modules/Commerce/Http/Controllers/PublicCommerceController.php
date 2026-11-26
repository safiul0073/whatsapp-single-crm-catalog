<?php

namespace App\Modules\Commerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Catalog;
use App\Modules\Commerce\Services\CatalogFeedService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCommerceController extends Controller
{
    public function feed(string $token, CatalogFeedService $feeds): StreamedResponse
    {
        $catalog = Catalog::query()->where('feed_token', $token)->where('is_active', true)->firstOrFail();

        return $feeds->response($catalog);
    }
}
