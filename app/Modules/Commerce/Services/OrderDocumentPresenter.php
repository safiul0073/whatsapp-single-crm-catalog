<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Workspaces\Models\Workspace;
use Closure;
use Illuminate\Support\Collection;

/** One data source for the printable order documents: invoice, POS receipt and packing slip. */
class OrderDocumentPresenter
{
    public const SIZE_ORDER = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', 'XXXL', '4XL', '5XL'];

    public function load(Order $order): Order
    {
        return $order->load([
            'contact', 'groups', 'payments.staff',
            'items.variant.color.swatchMedia', 'items.variant.media', 'items.variant.product.primaryMedia', 'items.variant.product.gallery.media',
        ]);
    }

    /**
     * @return array{brand: array<string, string>, customer: array<string, ?string>, shipTo: Collection<int, string>, groups: Collection, money: Closure, order: Order}
     */
    public function present(Order $order): array
    {
        return [
            'order' => $order,
            'brand' => $this->brand($order),
            'customer' => $this->customer($order),
            'shipTo' => $this->shipTo($order),
            'groups' => $this->lineGroups($order),
            'money' => fn ($amount): string => $order->currency.' '.number_format((float) $amount, $order->moneyPrecision()),
        ];
    }

    /**
     * @return array{name: string, address: string, phone: string, email: string, tax_id: string, footer: string, logo: ?string}
     */
    public function brand(Order $order): array
    {
        $config = StorefrontSetting::query()->where('workspace_id', $order->workspace_id)->first()?->config ?? [];

        return [
            'name' => (string) ($config['name'] ?? Workspace::query()->find($order->workspace_id)?->name ?? ''),
            'address' => trim((string) ($config['address'] ?? '')),
            'phone' => trim((string) ($config['contact_phone'] ?? '')),
            'email' => trim((string) ($config['contact_email'] ?? '')),
            'tax_id' => trim((string) ($config['tax_id'] ?? '')),
            'footer' => trim((string) ($config['invoice_footer'] ?? '')),
            'logo' => $this->logoUrl($config['logo'] ?? null),
        ];
    }

    /**
     * @return array{name: ?string, phone: ?string, email: ?string}
     */
    public function customer(Order $order): array
    {
        $address = $order->shipping_address ?? [];
        $snapshot = $order->customer_snapshot ?? [];

        return [
            'name' => $order->contact?->name ?? ($snapshot['name'] ?? ($address['name'] ?? null)),
            'phone' => $order->contact?->phone ?? ($snapshot['phone'] ?? ($address['phone'] ?? null)),
            'email' => $order->contact?->email ?? ($snapshot['email'] ?? null),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function shipTo(Order $order): Collection
    {
        $address = $order->shipping_address ?? [];

        return collect([
            $address['name'] ?? null,
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            collect([$address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null])->filter()->implode(', '),
            $address['country'] ?? null,
        ])->filter()->values();
    }

    /**
     * Items grouped by product and colour, sizes in garment order.
     *
     * @return Collection<int, array{first: mixed, items: Collection, pieces: int, total: float, has_sizes: bool, image: ?array<string, string>}>
     */
    public function lineGroups(Order $order): Collection
    {
        $sizeOrder = array_flip(self::SIZE_ORDER);

        return $order->items
            ->groupBy(fn ($item) => $item->product_name.'|'.($item->attributes['color'] ?? ''))
            ->map(function (Collection $items) use ($sizeOrder): array {
                $items = $items->sortBy(fn ($item) => $sizeOrder[strtoupper($item->attributes['size'] ?? '')] ?? 99)->values();

                return [
                    'first' => $items->first(),
                    'items' => $items,
                    'pieces' => (int) $items->sum('quantity'),
                    'total' => (float) $items->sum(fn ($item) => (float) $item->line_total),
                    'has_sizes' => $items->contains(fn ($item) => filled($item->attributes['size'] ?? null)),
                    'image' => $items->first()->productImages()[0] ?? null,
                ];
            })->values();
    }

    private function logoUrl(mixed $logo): ?string
    {
        $logo = is_string($logo) ? trim($logo) : '';
        if ($logo === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $logo) === 1) {
            return $logo;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.ltrim((string) preg_replace('#^/?(storage/)?#', '', $logo), '/');
    }
}
