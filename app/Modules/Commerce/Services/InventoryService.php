<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\InventoryMovement;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /** Why an owner changed stock by hand. */
    public const ADJUSTMENT_TYPES = ['restock', 'correction', 'damaged', 'returned', 'other'];

    /**
     * Sets a variant's stock to an exact level ("set") or moves it by a signed amount ("add"), and records the
     * change in the inventory ledger. A repeated idempotency key returns the variant without changing it again.
     */
    public function adjust(ProductVariant $variant, string $mode, int $quantity, string $type, ?string $note, ?int $userId, string $idempotencyKey): ProductVariant
    {
        return DB::transaction(function () use ($variant, $mode, $quantity, $type, $note, $userId, $idempotencyKey): ProductVariant {
            $locked = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $key = 'manual:'.$locked->workspace_id.':'.$idempotencyKey;
            if (InventoryMovement::query()->where('idempotency_key', $key)->exists()) {
                return $locked;
            }

            $before = (int) $locked->stock_quantity;
            $after = $mode === 'set' ? $quantity : $before + $quantity;
            if ($after < 0) {
                throw ValidationException::withMessages(['quantity' => __('Stock cannot go below zero. :sku has :count on hand.', ['sku' => $locked->sku, 'count' => $before])]);
            }
            if ($after === $before) {
                return $locked;
            }

            InventoryMovement::query()->create([
                'workspace_id' => $locked->workspace_id, 'variant_id' => $locked->id, 'quantity_delta' => $after - $before,
                'reason' => 'manual_adjustment', 'idempotency_key' => $key,
                'metadata' => ['type' => $type, 'before' => $before, 'after' => $after, 'note' => $note, 'user_id' => $userId],
            ]);
            $locked->update(['stock_quantity' => $after]);

            return $locked;
        });
    }

    /**
     * Records stock levels an owner changed from the product editor, so the variant history stays complete.
     *
     * @param  array<int, int>  $before  variant id => stock before the edit
     */
    public function recordProductEdits(int $workspaceId, array $before, ?int $userId): void
    {
        if ($before === []) {
            return;
        }
        $current = ProductVariant::query()->whereIn('id', array_keys($before))->pluck('stock_quantity', 'id');
        foreach ($before as $variantId => $previous) {
            $after = (int) ($current[$variantId] ?? $previous);
            if ($after === (int) $previous) {
                continue;
            }
            InventoryMovement::query()->create([
                'workspace_id' => $workspaceId, 'variant_id' => $variantId, 'quantity_delta' => $after - (int) $previous,
                'reason' => 'product_edit', 'idempotency_key' => 'edit:'.Str::uuid(),
                'metadata' => ['before' => (int) $previous, 'after' => $after, 'user_id' => $userId],
            ]);
        }
    }

    /**
     * Stock changes for one variant, newest first. Reservation bookkeeping rows (no stock change) are left out.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function movements(ProductVariant $variant, int $limit = 50): Collection
    {
        return InventoryMovement::query()
            ->where('workspace_id', $variant->workspace_id)
            ->where('variant_id', $variant->id)
            ->where('quantity_delta', '!=', 0)
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function threshold(int $workspaceId): int
    {
        return max(0, (int) StoreOrderSetting::forWorkspace($workspaceId)->low_stock_threshold);
    }

    /**
     * Narrows a variant query to one stock state: "out" (none left), "low" (at or under the threshold) or "in".
     *
     * @param  Builder<ProductVariant>  $query
     * @return Builder<ProductVariant>
     */
    public function whereStatus(Builder $query, string $status, int $threshold): Builder
    {
        return match ($status) {
            'out' => $query->where('stock_quantity', '<=', 0),
            'low' => $query->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', $threshold),
            'in' => $query->where('stock_quantity', '>', $threshold),
            default => $query,
        };
    }

    public function status(int $stock, int $threshold): string
    {
        return $stock <= 0 ? 'out' : ($stock <= $threshold ? 'low' : 'in');
    }

    /**
     * @return Builder<ProductVariant>
     */
    public function variants(int $workspaceId): Builder
    {
        return ProductVariant::query()->where('workspace_id', $workspaceId)->where('status', '!=', 'archived')->whereHas('product');
    }

    /**
     * Counts for the inventory page cards.
     *
     * @return array{total: int, out: int, low: int, in: int, units: int, threshold: int}
     */
    public function summary(int $workspaceId): array
    {
        $threshold = $this->threshold($workspaceId);
        $row = $this->variants($workspaceId)->toBase()->selectRaw(
            'COUNT(*) as total, SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_count, '
            .'SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= ? THEN 1 ELSE 0 END) as low_count, SUM(stock_quantity) as units',
            [$threshold],
        )->first();
        $total = (int) ($row->total ?? 0);
        $out = (int) ($row->out_count ?? 0);
        $low = (int) ($row->low_count ?? 0);

        return ['total' => $total, 'out' => $out, 'low' => $low, 'in' => $total - $out - $low, 'units' => (int) ($row->units ?? 0), 'threshold' => $threshold];
    }
}
