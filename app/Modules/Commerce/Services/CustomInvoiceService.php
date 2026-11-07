<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Commerce\Models\CustomInvoice;
use App\Modules\Commerce\Models\InvoiceRequest;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Builds owner-made custom invoices and handles customer invoice requests. */
class CustomInvoiceService
{
    public const NUMBER_PREFIX = 'CI-';

    /**
     * Allowed status moves; drafts become sent, sent ones can be paid, anything open can be cancelled.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        CustomInvoice::DRAFT => [CustomInvoice::SENT, CustomInvoice::CANCELLED],
        CustomInvoice::SENT => [CustomInvoice::PAID, CustomInvoice::CANCELLED],
        CustomInvoice::PAID => [CustomInvoice::SENT],
        CustomInvoice::CANCELLED => [],
    ];

    /**
     * Create a new invoice or replace a draft's contents.
     *
     * @param  array{customer_id?: ?int, invoice_request_id?: ?int, bill_to_name?: ?string, bill_to_phone?: ?string, bill_to_email?: ?string, bill_to_address?: ?string, items: list<array{product_id?: ?int, variant_id?: ?int, name?: ?string, description?: ?string, quantity: int, unit_price?: string|int|float|null}>, discount_amount?: string|int|float|null, tax_amount?: string|int|float|null, shipping_amount?: string|int|float|null, notes?: ?string, due_date?: ?string, issue?: bool}  $data
     */
    public function save(Workspace $workspace, array $data, ?CustomInvoice $invoice = null): CustomInvoice
    {
        if ($invoice && ! $invoice->isDraft()) {
            throw ValidationException::withMessages(['status' => __('Only draft invoices can be edited.')]);
        }

        $currency = $invoice?->currency ?? StoreOrderSetting::catalogCurrency($workspace->id);
        $precision = (new StoreOrderSetting(['currency' => $currency]))->precision();
        $customer = $this->customer($workspace, $data['customer_id'] ?? null);
        $request = $this->request($workspace, $data['invoice_request_id'] ?? null, $invoice);
        $lines = $this->lines($workspace, $data['items'] ?? [], $precision);

        $subtotal = (int) $lines->sum('line_minor');
        $discount = $this->amount($data['discount_amount'] ?? 0, $precision, 'discount_amount');
        $tax = $this->amount($data['tax_amount'] ?? 0, $precision, 'tax_amount');
        $shipping = $this->amount($data['shipping_amount'] ?? 0, $precision, 'shipping_amount');
        if ($discount > $subtotal + $tax + $shipping) {
            throw ValidationException::withMessages(['discount_amount' => __('The discount cannot be larger than the invoice amount.')]);
        }

        $billToName = trim((string) ($data['bill_to_name'] ?? '')) ?: ($customer?->contact?->name ?? '');
        if ($billToName === '') {
            throw ValidationException::withMessages(['bill_to_name' => __('Enter who the invoice is for or pick a customer.')]);
        }

        $attributes = [
            'customer_id' => $customer?->id,
            'invoice_request_id' => $request?->id,
            'bill_to_name' => $billToName,
            'bill_to_phone' => $this->nullable($data['bill_to_phone'] ?? null) ?? ($customer?->contact?->phone ?? $customer?->verified_phone),
            'bill_to_email' => $this->nullable($data['bill_to_email'] ?? null) ?? $customer?->contact?->email,
            'bill_to_address' => $this->nullable($data['bill_to_address'] ?? null),
            'currency' => $currency,
            'subtotal' => OrderMoney::decimal($subtotal, $precision),
            'discount_amount' => OrderMoney::decimal($discount, $precision),
            'tax_amount' => OrderMoney::decimal($tax, $precision),
            'shipping_amount' => OrderMoney::decimal($shipping, $precision),
            'total' => OrderMoney::decimal($subtotal + $tax + $shipping - $discount, $precision),
            'notes' => $this->nullable($data['notes'] ?? null),
            'due_date' => $data['due_date'] ?? null,
        ];

        return DB::transaction(function () use ($workspace, $invoice, $attributes, $lines, $request, $data, $precision): CustomInvoice {
            if ($invoice) {
                $invoice->update($attributes);
                $invoice->items()->delete();
            } else {
                $sequence = (int) CustomInvoice::query()->where('workspace_id', $workspace->id)->lockForUpdate()->max('sequence') + 1;
                $invoice = CustomInvoice::query()->create($attributes + [
                    'workspace_id' => $workspace->id,
                    'sequence' => $sequence,
                    'number' => self::NUMBER_PREFIX.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
                    'token' => Str::random(40),
                    'status' => CustomInvoice::DRAFT,
                ]);
            }

            foreach ($lines->values() as $position => $line) {
                $invoice->items()->create([
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'name' => $line['name'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => OrderMoney::decimal($line['unit_minor'], $precision),
                    'line_total' => OrderMoney::decimal($line['line_minor'], $precision),
                    'position' => $position,
                ]);
            }

            if ($request && $request->isPending()) {
                $request->update(['status' => InvoiceRequest::INVOICED, 'resolved_at' => now()]);
            }

            if (! empty($data['issue'])) {
                $this->transition($invoice, CustomInvoice::SENT);
            }

            return $invoice->load('items');
        });
    }

    public function transition(CustomInvoice $invoice, string $status): CustomInvoice
    {
        if (! in_array($status, self::TRANSITIONS[$invoice->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('An invoice that is :from cannot be marked :to.', ['from' => $invoice->status, 'to' => $status])]);
        }

        $changes = ['status' => $status];
        if ($status === CustomInvoice::SENT) {
            $changes['issued_at'] = $invoice->issued_at ?? now();
            $changes['paid_at'] = null;
        }
        if ($status === CustomInvoice::PAID) {
            $changes['paid_at'] = now();
        }
        if ($status === CustomInvoice::CANCELLED) {
            $changes['cancelled_at'] = now();
        }
        $invoice->update($changes);

        return $invoice;
    }

    public function regenerateToken(CustomInvoice $invoice): CustomInvoice
    {
        $invoice->forceFill(['token' => Str::random(40)])->save();

        return $invoice;
    }

    /**
     * Form defaults for an invoice answering a customer request.
     *
     * @return array{customer_id: int, invoice_request_id: int, bill_to_name: string, bill_to_phone: ?string, bill_to_email: ?string, notes: string, items: list<array{product_id: ?int, variant_id: ?int, name: string, description: ?string, quantity: int, unit_price: string}>}
     */
    public function draftFromRequest(InvoiceRequest $request): array
    {
        $request->loadMissing('customer.contact');
        $workspace = Workspace::query()->findOrFail($request->workspace_id);
        $precision = (new StoreOrderSetting(['currency' => StoreOrderSetting::catalogCurrency($workspace->id)]))->precision();
        $items = collect($request->items ?? [])
            ->map(fn (array $item): array => ['product_id' => $item['product_id'] ?? null, 'variant_id' => $item['variant_id'] ?? null, 'quantity' => (int) ($item['quantity'] ?? 1), 'description' => $item['note'] ?? null])
            ->filter(fn (array $item): bool => (bool) $item['product_id']);

        return [
            'customer_id' => $request->customer_id,
            'invoice_request_id' => $request->id,
            'bill_to_name' => (string) $request->customer?->contact?->name,
            'bill_to_phone' => $request->customer?->contact?->phone ?? $request->customer?->verified_phone,
            'bill_to_email' => $request->customer?->contact?->email,
            'notes' => '',
            'items' => $this->lines($workspace, $items->all(), $precision, skipMissing: true)
                ->map(fn (array $line): array => [
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'name' => $line['name'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => OrderMoney::decimal($line['unit_minor'], $precision),
                ])->values()->all(),
        ];
    }

    /**
     * @param  array{message: string, items?: list<array{product_id: int, variant_id?: ?int, quantity: int, note?: ?string}>}  $data
     */
    public function createRequest(Customer $customer, array $data): InvoiceRequest
    {
        $workspaceId = (int) $customer->workspace_id;
        $items = collect($data['items'] ?? [])->map(function (array $item, int $index) use ($workspaceId): array {
            $product = Product::query()->where('workspace_id', $workspaceId)->whereKey($item['product_id'])->first();
            if (! $product) {
                throw ValidationException::withMessages(["items.$index.product_id" => __('This product is not available.')]);
            }
            $variantId = $item['variant_id'] ?? null;
            if ($variantId && ! ProductVariant::query()->where('product_id', $product->id)->whereKey($variantId)->exists()) {
                throw ValidationException::withMessages(["items.$index.variant_id" => __('This option is not available.')]);
            }

            return ['product_id' => $product->id, 'variant_id' => $variantId, 'name' => $product->name, 'quantity' => (int) $item['quantity'], 'note' => $this->nullable($item['note'] ?? null)];
        })->values()->all();

        return InvoiceRequest::query()->create([
            'workspace_id' => $workspaceId,
            'customer_id' => $customer->id,
            'message' => trim($data['message']),
            'items' => $items ?: null,
            'status' => InvoiceRequest::PENDING,
        ]);
    }

    public function rejectRequest(InvoiceRequest $request, ?string $note): InvoiceRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => __('This request has already been answered.')]);
        }
        $request->update(['status' => InvoiceRequest::REJECTED, 'owner_note' => $this->nullable($note), 'resolved_at' => now()]);

        return $request;
    }

    /**
     * Resolve each line: catalog lines take name/price from the product unless overridden; free-text lines need both.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, array{product_id: ?int, variant_id: ?int, name: string, description: ?string, quantity: int, unit_minor: int, line_minor: int}>
     */
    private function lines(Workspace $workspace, array $items, int $precision, bool $skipMissing = false): Collection
    {
        $productIds = collect($items)->pluck('product_id')->filter()->unique();
        $products = Product::query()->where('workspace_id', $workspace->id)->whereIn('id', $productIds)->with('variants')->get()->keyBy('id');

        $lines = collect($items)->map(function (array $item, int $index) use ($products, $precision, $skipMissing): ?array {
            $product = null;
            $variant = null;
            if (! empty($item['product_id'])) {
                $product = $products->get((int) $item['product_id']);
                if (! $product) {
                    if ($skipMissing) {
                        return null;
                    }
                    throw ValidationException::withMessages(["items.$index.product_id" => __('This product does not belong to your store.')]);
                }
                if (! empty($item['variant_id'])) {
                    $variant = $product->variants->firstWhere('id', (int) $item['variant_id']);
                    if (! $variant) {
                        throw ValidationException::withMessages(["items.$index.variant_id" => __('This option does not belong to the product.')]);
                    }
                }
            }

            $name = trim((string) ($item['name'] ?? '')) ?: ($product ? $this->productLabel($product, $variant) : '');
            if ($name === '') {
                throw ValidationException::withMessages(["items.$index.name" => __('Enter a description for this line.')]);
            }

            $price = $item['unit_price'] ?? null;
            if ($price === null || $price === '') {
                if (! $product) {
                    throw ValidationException::withMessages(["items.$index.unit_price" => __('Enter a price for this line.')]);
                }
                $price = $variant?->price ?? $product->single_piece_price ?? $product->variants->first()?->price ?? 0;
            }

            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $unitMinor = $this->amount($price, $precision, "items.$index.unit_price");

            return [
                'product_id' => $product?->id,
                'variant_id' => $variant?->id,
                'name' => Str::limit($name, 250, ''),
                'description' => $this->nullable($item['description'] ?? null),
                'quantity' => $quantity,
                'unit_minor' => $unitMinor,
                'line_minor' => $unitMinor * $quantity,
            ];
        })->filter()->values();

        if ($lines->isEmpty() && ! $skipMissing) {
            throw ValidationException::withMessages(['items' => __('Add at least one line to the invoice.')]);
        }

        return $lines;
    }

    private function productLabel(Product $product, ?ProductVariant $variant): string
    {
        $options = collect($variant?->attributes ?? [])->filter(fn ($value): bool => is_scalar($value) && $value !== '')->implode(' / ');

        return $options !== '' ? $product->name.' ('.$options.')' : (string) $product->name;
    }

    private function amount(string|int|float|null $value, int $precision, string $field): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        try {
            return OrderMoney::minor($value, $precision);
        } catch (ValidationException) {
            throw ValidationException::withMessages([$field => __('Enter a valid amount.')]);
        }
    }

    private function customer(Workspace $workspace, mixed $customerId): ?Customer
    {
        if (! $customerId) {
            return null;
        }

        return Customer::query()->with('contact')->where('workspace_id', $workspace->id)->whereKey($customerId)->first()
            ?? throw ValidationException::withMessages(['customer_id' => __('This customer does not belong to your store.')]);
    }

    private function request(Workspace $workspace, mixed $requestId, ?CustomInvoice $invoice): ?InvoiceRequest
    {
        if (! $requestId) {
            return $invoice?->request;
        }

        return InvoiceRequest::query()->where('workspace_id', $workspace->id)->whereKey($requestId)->first()
            ?? throw ValidationException::withMessages(['invoice_request_id' => __('This request does not belong to your store.')]);
    }

    private function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
