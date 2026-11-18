<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Requests\TrackPageViewRequest;
use App\Modules\Commerce\Http\Requests\TrackViewContentRequest;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Services\MetaConversionsService;
use App\Modules\Commerce\Storefront\Services\CartService;
use App\Modules\Commerce\Storefront\Services\VisitorTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    public function __construct(private MetaConversionsService $conversions) {}

    public function pageView(TrackPageViewRequest $request, VisitorTrackingService $tracking): Response
    {
        $workspace = $request->attributes->get('store_workspace')->id;
        $tracking->record($request, $workspace, $request->validated());
        $this->conversions->queue($workspace, 'PageView', $this->conversions->clientContext($request));

        return response()->noContent();
    }

    /**
     * Server half of the product page's ViewContent; values come from the catalog, never the browser.
     */
    public function viewContent(TrackViewContentRequest $request): Response
    {
        $workspace = $request->attributes->get('store_workspace')->id;
        $product = Product::query()
            ->where('workspace_id', $workspace)
            ->where('status', 'active')
            ->where('visibility', 'published')
            ->with('variants')
            ->findOrFail($request->integer('product_id'));
        $price = (float) ($product->single_piece_price ?? $product->variants->where('status', 'active')->min('price') ?? 0);
        $contentId = 'product-'.$product->id;

        $this->conversions->queue($workspace, 'ViewContent', $this->conversions->clientContext($request), [
            'currency' => strtoupper(StoreOrderSetting::catalogCurrency($workspace)),
            'value' => $price,
            'content_type' => 'product_group',
            'content_name' => (string) $product->name,
            'content_ids' => [$contentId],
            'contents' => [['id' => $contentId, 'quantity' => 1, 'item_price' => $price]],
        ]);

        return response()->noContent();
    }

    /**
     * Server half of InitiateCheckout, built from the visitor's current cart.
     */
    public function initiateCheckout(Request $request, CartService $carts): Response
    {
        $summary = $carts->summary($carts->resolve($request));
        abort_if($summary['items'] === [], 422, 'The cart is empty.');

        $this->conversions->queue(
            $request->attributes->get('store_workspace')->id,
            'InitiateCheckout',
            $this->conversions->clientContext($request),
            $this->conversions->cartData($summary['items'], (string) $summary['currency'], (string) $summary['total']),
            $request->attributes->get('store_customer'),
        );

        return response()->noContent();
    }
}
