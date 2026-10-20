<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Storefront\Http\Requests\EngagementRequest;
use App\Modules\Commerce\Storefront\Http\Resources\ProductResource;
use App\Modules\Commerce\Storefront\Models\ProductReview;
use App\Modules\Commerce\Storefront\Models\StorefrontContent;
use App\Modules\Commerce\Storefront\Models\Wishlist;
use App\Modules\Commerce\Storefront\Services\CatalogService;
use App\Modules\SystemNotifications\Models\SystemNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EngagementController extends Controller
{
    public function wishlist(Request $r, CatalogService $catalog): JsonResponse
    {
        $c = $r->attributes->get('store_customer');
        $ids = Wishlist::query()->where('workspace_id', $c->workspace_id)->where('customer_id', $c->id)->pluck('product_id');

        return response()->json(['data' => ProductResource::collection($catalog->query($c->workspace_id)->whereIn('id', $ids)->get())]);
    }

    public function saveWishlist(Request $r, int $id, CatalogService $catalog): JsonResponse
    {
        $c = $r->attributes->get('store_customer');
        $catalog->query($c->workspace_id)->findOrFail($id);
        Wishlist::query()->firstOrCreate(['workspace_id' => $c->workspace_id, 'customer_id' => $c->id, 'product_id' => $id]);

        return $this->wishlist($r, $catalog);
    }

    public function deleteWishlist(Request $r, int $id, CatalogService $catalog): JsonResponse
    {
        $c = $r->attributes->get('store_customer');
        Wishlist::query()->where('workspace_id', $c->workspace_id)->where('customer_id', $c->id)->where('product_id', $id)->delete();

        return $this->wishlist($r, $catalog);
    }

    public function reviews(Request $r, string $slug, CatalogService $catalog): JsonResponse
    {
        $w = $r->attributes->get('store_workspace')->id;
        $p = $catalog->query($w)->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => ProductReview::query()->where('workspace_id', $w)->where('product_id', $p->id)->where('status', 'approved')->latest()->paginate(20, ['id', 'rating', 'body', 'created_at'])]);
    }

    public function review(EngagementRequest $r, string $slug, CatalogService $catalog): JsonResponse
    {
        $c = $r->attributes->get('store_customer');
        $p = $catalog->query($c->workspace_id)->where('slug', $slug)->firstOrFail();
        abort_unless(Order::query()->where('workspace_id', $c->workspace_id)->where('contact_id', $c->contact_id)->where('status', 'completed')->whereHas('items', fn ($q) => $q->where('product_id', $p->id))->exists(), 403, 'Only verified buyers can review this product.');
        $review = ProductReview::query()->updateOrCreate(['workspace_id' => $c->workspace_id, 'customer_id' => $c->id, 'product_id' => $p->id], [...$r->validated(), 'status' => 'pending']);

        return response()->json(['data' => $review], 201);
    }

    public function blogs(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->content($r, 'blog')->paginate(12)]);
    }

    public function blog(Request $r, string $slug): JsonResponse
    {
        return response()->json(['data' => $this->content($r, 'blog')->where('slug', $slug)->firstOrFail()]);
    }

    public function faqs(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->content($r, 'faq')->get()]);
    }

    private function content(Request $r, string $type): Builder
    {
        return StorefrontContent::query()->where('workspace_id', $r->attributes->get('store_workspace')->id)->where('type', $type)->where('published_at', '<=', now())->latest();
    }

    public function contact(EngagementRequest $r): JsonResponse
    {
        $workspace = $r->attributes->get('store_workspace');
        $payload = $r->validated();

        StorefrontContent::query()->create([
            'workspace_id' => $workspace->id,
            'type' => 'contact',
            'slug' => (string) Str::uuid(),
            'data' => $payload,
        ]);

        rescue(function () use ($workspace, $payload): void {
            $owner = $workspace->owner;
            if ($owner) {
                SystemNotification::query()->create([
                    'type' => 'storefront.contact_inquiry',
                    'notifiable_type' => get_class($owner),
                    'notifiable_id' => $owner->id,
                    'data' => [
                        'title' => 'New Storefront Inquiry: '.($payload['subject'] ?? 'General Inquiry'),
                        'message' => ($payload['name'] ?? 'Customer').' sent a message: '.Str::limit($payload['message'] ?? '', 100),
                        'customer_name' => $payload['name'] ?? '',
                        'customer_email' => $payload['email'] ?? '',
                        'subject' => $payload['subject'] ?? 'General Inquiry',
                        'action_url' => route('user.commerce.storefront.index').'?tab=inquiries#inquiries',
                        'icon' => 'ph ph-chats-circle',
                    ],
                ]);
            }
        }, report: false);

        return response()->json(['data' => ['success' => true]], 201);
    }

    public function newsletter(EngagementRequest $r): JsonResponse
    {
        StorefrontContent::query()->updateOrCreate(['workspace_id' => $r->attributes->get('store_workspace')->id, 'type' => 'subscriber', 'slug' => hash('sha256', strtolower($r->validated('email')))], ['data' => ['email' => strtolower($r->validated('email')), 'consented_at' => now()->toIso8601String()]]);

        return response()->json(['data' => ['success' => true]]);
    }
}
