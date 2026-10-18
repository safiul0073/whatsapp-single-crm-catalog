<?php

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Http\Resources\ProductDetailResource;
use App\Modules\Commerce\Storefront\Http\Requests\PackRequest;
use App\Modules\Commerce\Storefront\Models\CartGroup;
use App\Modules\Commerce\Storefront\Models\CartItem;
use App\Modules\Commerce\Storefront\Models\PackTemplate;
use App\Modules\Commerce\Storefront\Services\CartService;
use App\Modules\Commerce\Storefront\Services\CatalogService;
use App\Modules\Commerce\Storefront\Services\PackSelectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackController extends Controller
{
    public function __construct(private CartService $carts, private PackSelectionService $packs) {}

    public function templates(Request $r): JsonResponse
    {
        $workspace = $r->attributes->get('store_workspace')->id;
        $templates = PackTemplate::query()->where('workspace_id', $workspace)->where('published', true)->orderBy('position')->get();

        return response()->json(['data' => $templates->map(function ($template) use ($workspace) {
            $products = app(CatalogService::class)->query($workspace, ['selling_mode' => 'wholesale'])->where('ws_enabled', true)->whereIn('id', $template->product_ids)->get();

            return [...$template->toArray(), 'products' => ProductDetailResource::collection($products)];
        })]);
    }

    public function store(PackRequest $r): JsonResponse
    {
        return $this->mutate($r);
    }

    public function update(PackRequest $r, int $id): JsonResponse
    {
        return $this->mutate($r, $id);
    }

    public function preview(PackRequest $r): JsonResponse
    {
        return $this->mutate($r, $r->integer('replace_group_id') ?: null, true);
    }

    private function mutate(PackRequest $r, ?int $id = null, bool $preview = false): JsonResponse
    {
        $connection = DB::connection();
        $connection->beginTransaction();
        try {
            $cart = $this->carts->resolve($r, true);
            $selection = $r->validated('selection');
            $key = (string) $r->header('Idempotency-Key');
            $hash = hash('sha256', json_encode($selection, JSON_THROW_ON_ERROR));
            if (! $preview && ! $id) {
                abort_unless(Str::isUuid($key), 422, 'Idempotency-Key must be a UUID.');
                $existing = CartGroup::query()->where('cart_id', $cart->id)->where('request_key', $key)->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This idempotency key has different contents.');
                    $summary = $this->carts->summary($cart);
                    $connection->commit();

                    return response()->json(['data' => $summary]);
                }
            }
            $expanded = $this->packs->validate($cart->workspace_id, $selection);
            if ($id) {
                $group = CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->find($id);
                abort_unless($group, 422, 'The specified group does not exist in your cart.');
            } else {
                abort_if(CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->count() >= 50, 422, 'Cart is full.');
                $group = new CartGroup(['workspace_id' => $cart->workspace_id, 'cart_id' => $cart->id]);
            }
            $group->selection = $expanded['selection'];
            if (! $id && ! $preview) {
                $group->request_key = $key;
                $group->request_hash = $hash;
            }
            $group->save();
            CartItem::query()->where('cart_id', $cart->id)->where('group_id', $group->id)->delete();
            foreach ($expanded['rows'] as $row) {
                CartItem::query()->create(['workspace_id' => $cart->workspace_id, 'cart_id' => $cart->id, 'group_id' => $group->id, ...collect($row)->except('mode')->all()]);
            }
            $summary = $this->carts->summary($cart);
            $newIds = CartItem::query()->where('group_id', $group->id)->pluck('id')->all();
            $errors = collect($summary['warnings'])->filter(fn ($w) => ($w['item_id'] ?? null) === 'cart' || ($w['group_id'] ?? null) === $group->id || in_array($w['item_id'], $newIds, true))->pluck('message')->all();
            if ($errors) {
                throw ValidationException::withMessages(['selection' => $errors]);
            }
            if ($preview) {
                $previewGroup = collect($summary['groups'])->firstWhere('id', $group->id);
                $summary['preview_group'] = $previewGroup;
                $summary['group'] = $previewGroup ? [
                    'price' => $previewGroup['total'] ?? $previewGroup['price'] ?? '0.00',
                    'total_pieces' => $previewGroup['total_pieces'] ?? 0,
                    'name' => $previewGroup['name'] ?? '',
                    'requirements' => $previewGroup['requirements'] ?? [],
                    'violations' => $previewGroup['violations'] ?? [],
                    'items' => $previewGroup['items'] ?? [],
                ] : null;
                $connection->rollBack();
            } else {
                $connection->commit();
            }

            return response()->json(['data' => $summary]);
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function destroy(Request $r, int $id): JsonResponse
    {
        return DB::transaction(function () use ($r, $id) {
            $cart = $this->carts->resolve($r, true);
            $group = CartGroup::query()->where('cart_id', $cart->id)->whereNull('removed_at')->findOrFail($id);
            CartItem::query()->where('cart_id', $cart->id)->where('group_id', $id)->delete();
            $group->update(['removed_at' => now()]);

            return response()->json(['data' => $this->carts->summary($cart)]);
        });
    }
}
