<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Storefront\Models\PackTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackSelectionService
{
    public function physicalBoxCount(array $groups): int
    {
        $count = 0;
        $seen = [];
        foreach ($groups as $group) {
            $snapshot = $group['pack_snapshot'] ?? null;
            if ($snapshot !== null) {
                $instanceKey = $snapshot['cart_group_id']
                    ?? $snapshot['pack_instance_id']
                    ?? (($snapshot['template_id'] ?? 'custom').':'.json_encode($snapshot['selection'] ?? []));
                if (isset($seen[$instanceKey])) {
                    continue;
                }
                $seen[$instanceKey] = true;
                $count += max(1, (int) ($snapshot['selection']['pack_count'] ?? $group['box_count'] ?? 1));
            } else {
                $count += (int) ($group['box_count'] ?? 0);
            }
        }

        return $count;
    }

    public function validate(int $workspace, array $selection): array
    {
        $selection = Validator::make($selection, [
            'type' => ['required', 'in:retail,standard_wholesale,custom_pack'],
            'variant_id' => ['required_if:type,retail', 'integer', 'min:1'],
            'quantity' => ['required_if:type,retail', 'integer', 'min:1', 'max:10000'],
            'product_id' => ['required_if:type,standard_wholesale', 'integer'],
            'colors' => ['required_if:type,standard_wholesale', 'array', 'min:1', 'max:50'],
            'colors.*' => ['array:color_id,box_count'],
            'colors.*.color_id' => ['required', 'integer', 'distinct'],
            'colors.*.box_count' => ['required', 'integer', 'min:1', 'max:500'],
            'template_id' => ['required_if:type,custom_pack', 'integer'],
            'pack_count' => ['required_if:type,custom_pack', 'integer', 'min:1', 'max:500'],
            'items' => ['required_if:type,custom_pack', 'array', 'min:1', 'max:200'],
            'items.*' => ['array:variant_id,quantity'],
            'items.*.variant_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'price' => ['prohibited'], 'unit_price' => ['prohibited'], 'total' => ['prohibited'],
        ])->validate();
        $rows = [];
        $orders = [];
        $requirements = [];
        $title = '';
        if ($selection['type'] === 'retail') {
            $v = $this->variant($workspace, $selection['variant_id']);
            $this->check($v->product->isRetailEnabled(), 'This product is wholesale only.');
            $title = $v->product->name;
            $rows[] = ['variant_id' => $v->id, 'product_id' => $v->product_id, 'quantity' => $selection['quantity'], 'mode' => 'retail'];
            $orders[] = ['product_id' => $v->product_id, 'variant_id' => $v->id, 'quantity' => $selection['quantity'], 'mode' => 'retail'];
        } elseif ($selection['type'] === 'standard_wholesale') {
            $p = Product::query()->where('workspace_id', $workspace)->with('variants')->find($selection['product_id']);
            $this->check($p && $p->status === 'active' && $p->visibility === 'published' && $p->isWholesaleEnabled(), 'Wholesale product unavailable.');
            $title = $p->name;
            foreach ($selection['colors'] as $color) {
                $ratio = array_filter($p->getEffectiveSizeRatios()[$color['color_id']] ?? [], fn ($q) => (int) $q > 0);
                $this->check(count($ratio) > 0, 'This color has no configured pack.');
                $multiplier = max(1, (int) $p->ws_ratio_multiplier);
                $this->check(array_sum($ratio) * $multiplier * $color['box_count'] >= max(1, (int) $p->ws_color_moq), 'The selected color does not meet its minimum quantity.');
                foreach ($ratio as $size => $qty) {
                    $v = $p->variants->first(fn ($v) => $v->color_id === (int) $color['color_id'] && $v->size === (string) $size && $v->status === 'active');
                    $this->check((bool) $v, "The configured {$size} variant is unavailable.");
                    $rows[] = ['variant_id' => $v->id, 'product_id' => $p->id, 'quantity' => (int) $qty * $multiplier * $color['box_count'], 'mode' => 'wholesale'];
                }
                $orders[] = ['product_id' => $p->id, 'mode' => 'wholesale', ...$color];
            }
            $this->check(array_sum(array_column($rows, 'quantity')) >= max(1, (int) $p->ws_main_moq), "{$p->name} requires at least {$p->ws_main_moq} pieces.");
        } else {
            $template = PackTemplate::query()->where('workspace_id', $workspace)->where('published', true)->find($selection['template_id']);
            $this->check((bool) $template, 'This custom pack template is unavailable.');
            $requirements = $template->only(['min_pieces', 'max_pieces', 'min_products', 'min_colors', 'min_sizes', 'min_variant_quantity']);
            $title = $template->name;
            $products = [];
            $pieces = 0;
            foreach ($selection['items'] as $item) {
                $v = $this->variant($workspace, $item['variant_id']);
                $this->check(in_array($v->product_id, $template->product_ids) && $v->product->isWholesaleEnabled(), "{$v->product->name} is not eligible for this pack.");
                $this->check($v->color_id && $v->size, 'Custom packs require variants with a color and size.');
                $this->check($item['quantity'] >= $template->min_variant_quantity, "Each selected variant requires at least {$template->min_variant_quantity} pieces per pack.");
                $products[$v->product_id][$v->color_id][$v->size] = ($products[$v->product_id][$v->color_id][$v->size] ?? 0) + $item['quantity'];
                $pieces += $item['quantity'];
                $rows[] = ['variant_id' => $v->id, 'product_id' => $v->product_id, 'quantity' => $item['quantity'] * $selection['pack_count'], 'mode' => 'wholesale'];
            }
            $this->check($pieces >= $template->min_pieces, "This pack requires at least {$template->min_pieces} pieces; selected {$pieces}.");
            $this->check(! $template->max_pieces || $pieces <= $template->max_pieces, "This pack exceeds its maximum of {$template->max_pieces} pieces.");
            $this->check(count($products) >= $template->min_products, "This pack requires at least {$template->min_products} distinct products.");
            $packInstanceId = (string) Str::uuid();
            foreach ($products as $productId => $colors) {
                $this->check(count($colors) >= $template->min_colors, "Each product requires at least {$template->min_colors} distinct colors.");
                foreach ($colors as $colorId => $sizes) {
                    $this->check(count($sizes) >= $template->min_sizes, "Each product/color requires at least {$template->min_sizes} distinct sizes.");
                    $orders[] = ['product_id' => $productId, 'mode' => 'wholesale', 'color_id' => $colorId, 'box_count' => $selection['pack_count'], 'size_quantities' => $sizes,
                        'pack_snapshot' => ['pack_instance_id' => $packInstanceId, 'template_id' => $template->id, 'name' => $template->name, 'requirements' => $requirements, 'selection' => $selection]];
                }
            }
        }
        $this->check(array_sum(array_column($rows, 'quantity')) <= 100000, 'A selection may contain at most 100000 pieces.');

        return ['selection' => $selection, 'rows' => $rows, 'orders' => $orders, 'requirements' => $requirements, 'name' => $title];
    }

    private function variant(int $workspace, int $id): ProductVariant
    {
        $v = ProductVariant::query()->where('workspace_id', $workspace)->with('product')->find($id);
        $this->check($v && $v->status === 'active' && $v->product && $v->product->workspace_id === $workspace && $v->product->status === 'active' && $v->product->visibility === 'published', 'A selected variant is unavailable.');

        return $v;
    }

    private function check(bool $valid, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['selection' => $message]);
        }
    }
}
