<?php

namespace App\Modules\Commerce\Storefront\Services;

use App\Modules\Commerce\Models\Category;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Models\Product;
use App\Modules\Commerce\Models\ProductColor;
use App\Modules\Commerce\Models\ProductMedia;
use App\Modules\Commerce\Models\ProductVariant;
use App\Modules\Commerce\Models\StoreOrderSetting;
use App\Modules\Commerce\Storefront\Models\CartItem;
use App\Modules\Commerce\Storefront\Models\Coupon;
use App\Modules\Commerce\Storefront\Models\CouponUsage;
use App\Modules\Commerce\Storefront\Models\Customer;
use App\Modules\Commerce\Storefront\Models\CustomerAddress;
use App\Modules\Commerce\Storefront\Models\LegacyMapping;
use App\Modules\Commerce\Storefront\Models\ProductReview;
use App\Modules\Commerce\Storefront\Models\StorefrontMenu;
use App\Modules\Commerce\Storefront\Models\StorefrontPage;
use App\Modules\Commerce\Storefront\Models\StorefrontSection;
use App\Modules\Commerce\Storefront\Models\StorefrontSetting;
use App\Modules\Commerce\Storefront\Models\StorefrontSlider;
use App\Modules\Commerce\Storefront\Models\Wishlist;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Media\Models\Media;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LegacyImportService
{
    private ConnectionInterface $source;

    private int $workspace;

    private string $sourceName;

    private ?string $mediaRoot;

    private bool $dryRun;

    private array $report = [];

    public function run(string $connection, int $workspace, bool $dryRun = true, ?string $mediaRoot = null): array
    {
        abort_unless($connection !== config('database.default') && config('database.connections.'.$connection), 422, 'Configure a separate legacy source connection.');
        $this->source = DB::connection($connection);
        $this->workspace = $workspace;
        $this->sourceName = $connection;
        $this->mediaRoot = $mediaRoot ? realpath($mediaRoot) : null;
        $this->dryRun = $dryRun;
        $this->report = ['dry_run' => $dryRun, 'workspace' => $workspace, 'source' => $connection, 'tables' => [], 'issues' => [], 'deferred' => []];
        foreach (['users', 'categories', 'products', 'product_images', 'product_color_variants', 'product_color_variant_images', 'product_size_variants', 'addresses', 'coupons', 'orders', 'order_items', 'payments', 'coupon_usages', 'wishlists', 'product_reviews', 'carts', 'cart_items', 'hero_slides', 'cms_pages', 'home_page_sections', 'nav_menu_items', 'settings'] as $table) {
            if (! $this->source->getSchemaBuilder()->hasTable($table)) {
                $this->report['tables'][$table] = ['missing' => true];

                continue;
            }
            $stats = ['source' => $this->source->table($table)->count(), 'imported' => 0, 'already_mapped' => 0, 'unresolved' => 0, 'deleted' => 0];
            $this->source->table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, &$stats): void {
                foreach ($rows as $row) {
                    $row = (array) $row;
                    if (! empty($row['deleted_at'])) {
                        $stats['deleted']++;

                        continue;
                    }
                    if (LegacyMapping::query()->where($this->key($table, $row['id']))->where('status', 'imported')->exists()) {
                        $stats['already_mapped']++;

                        continue;
                    }
                    if ($this->dryRun) {
                        continue;
                    }
                    try {
                        Model::withoutEvents(fn () => DB::transaction(function () use ($table, $row): void {
                            $id = $this->importRow($table, $row);
                            LegacyMapping::query()->updateOrCreate($this->key($table, $row['id']), ['target_id' => $id, 'status' => 'imported', 'snapshot' => ['source_updated_at' => $row['updated_at'] ?? null]]);
                        }));
                        $stats['imported']++;
                    } catch (\Throwable $e) {
                        $stats['unresolved']++;
                        $message = $e instanceof RuntimeException && ! $e instanceof QueryException ? $e->getMessage() : 'Record could not be mapped; review its schema, constraints, and references.';
                        $this->report['issues'][] = ['table' => $table, 'id' => $row['id'], 'reason' => $message];
                        LegacyMapping::query()->updateOrCreate($this->key($table, $row['id']), ['status' => 'unresolved', 'snapshot' => ['reason' => $message]]);
                    }
                }
            });
            $this->report['tables'][$table] = $stats;
        }
        foreach (['referrals', 'referral_transactions', 'reward_transactions', 'reward_balances', 'cash_in_requests', 'shared_carts'] as $table) {
            if ($this->source->getSchemaBuilder()->hasTable($table)) {
                $this->report['deferred'][$table] = $this->source->table($table)->count();
            }
        }
        if (! $dryRun) {
            $this->assembleHome();
        }
        $this->report['ready_for_cutover'] = false;
        $this->report['cutover_note'] = 'Reconcile counts, media, deferred features, and unresolved records against a backup before approving retirement. Dry-run is an inventory and does not validate every transformation.';

        return $this->report;
    }

    private function key(string $entity, int|string $id): array
    {
        return ['workspace_id' => $this->workspace, 'source' => $this->sourceName, 'entity' => $entity, 'legacy_id' => (string) $id];
    }

    private function mapped(string $entity, int|string|null $id): int
    {
        $target = LegacyMapping::query()->where($this->key($entity, $id ?? ''))->where('status', 'imported')->value('target_id');
        if (! $target) {
            throw new RuntimeException('Unmapped '.$entity.' reference '.($id ?? 'null'));
        }

        return (int) $target;
    }

    private function importRow(string $table, array $r): int
    {
        return match ($table) {
            'users' => $this->customer($r),
            'categories' => $this->category($r),
            'products' => $this->product($r),
            'product_images' => $this->productImage($r),
            'product_color_variants' => $this->color($r),
            'product_color_variant_images' => $this->colorImage($r),
            'product_size_variants' => $this->variant($r),
            'addresses' => $this->address($r),
            'coupons' => $this->coupon($r),
            'orders' => $this->order($r),
            'order_items' => $this->orderItem($r),
            'payments' => $this->payment($r),
            'coupon_usages' => CouponUsage::query()->firstOrCreate(['workspace_id' => $this->workspace, 'coupon_id' => $this->mapped('coupons', $r['coupon_id']), 'customer_id' => $this->mapped('users', $r['user_id']), 'order_id' => $this->mapped('orders', $r['order_id'])])->id,
            'wishlists' => Wishlist::query()->firstOrCreate(['workspace_id' => $this->workspace, 'customer_id' => $this->mapped('users', $r['user_id']), 'product_id' => $this->mapped('products', $r['product_id'])])->id,
            'product_reviews' => $this->review($r),
            'carts' => $this->cart($r),
            'cart_items' => $this->cartItem($r),
            'hero_slides' => $this->slide($r),
            'cms_pages' => $this->page($r),
            'home_page_sections' => $this->section($r),
            'nav_menu_items' => $this->menu($r),
            'settings' => $this->setting($r),
            default => throw new RuntimeException('Unsupported legacy entity.'),
        };
    }

    private function customer(array $r): int
    {
        $identity = $this->source->getSchemaBuilder()->hasTable('whatsapp_customer_identities') ? $this->source->table('whatsapp_customer_identities')->where('user_id', $r['id'])->first() : null;
        $phone = $identity?->phone ?? $r['phone'] ?? '';
        if (! preg_match('/^\+[1-9]\d{7,14}$/D', $phone)) {
            throw new RuntimeException('Customer requires an unambiguous E.164 phone number.');
        }
        $contact = null;
        if ($identity?->marketing_contact_id) {
            $contact = Contact::query()->where('workspace_id', $this->workspace)->find($identity->marketing_contact_id);
            if (! $contact || $contact->phone !== $phone) {
                throw new RuntimeException('Verified marketing contact does not match this workspace/phone.');
            }
        }
        $contact ??= Contact::query()->where('workspace_id', $this->workspace)->where('phone', $phone)->first();
        $contact ??= Contact::query()->create(['workspace_id' => $this->workspace, 'name' => $r['name'] ?? trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')), 'phone' => $phone, 'email' => $r['email'] ?? null, 'source' => 'website', 'opt_in_status' => 'unknown']);
        $existing = Customer::query()->where('workspace_id', $this->workspace)->where('contact_id', $contact->id)->first();
        if ($existing) {
            return $existing->id;
        }
        $verified = $identity?->verified_at;
        $c = Customer::query()->create(['workspace_id' => $this->workspace, 'contact_id' => $contact->id, 'registered_at' => $r['created_at'] ?? now(), 'verified_at' => $verified, 'verified_phone' => $verified ? $phone : null]);
        if ($verified && ! empty($r['password']) && (password_get_info($r['password'])['algoName'] ?? 'unknown') !== 'unknown') {
            $c->setRawAttributes([...$c->getAttributes(), 'password' => $r['password']]);
            $c->save();
        }

        return $c->id;
    }

    private function category(array $r): int
    {
        return Category::query()->firstOrCreate(['workspace_id' => $this->workspace, 'slug' => $r['slug']], ['name' => $r['name'], 'is_active' => (bool) ($r['is_active'] ?? true), 'parent_id' => empty($r['parent_id']) ? null : $this->mapped('categories', $r['parent_id'])])->id;
    }

    private function product(array $r): int
    {
        $matches = Product::query()->where('workspace_id', $this->workspace)->where(function ($q) use ($r): void {
            $q->where('slug', $r['slug']);
            if (! empty($r['sku'])) {
                $q->orWhere('sku', $r['sku']);
            }
        })->get();
        if ($matches->count() > 1) {
            throw new RuntimeException('Ambiguous catalog SKU/slug match.');
        }if ($matches->count() === 1) {
            return $matches->first()->id;
        }
        $p = Product::query()->create(['workspace_id' => $this->workspace, 'name' => $r['name'], 'slug' => $r['slug'], 'sku' => $r['sku'] ?: 'legacy-'.$r['id'], 'description' => $r['description'] ?? null, 'short_description' => $r['short_description'] ?? null, 'single_piece_price' => $r['sale_price'] ?? $r['base_price'], 'selling_mode' => 'retail', 'status' => ! empty($r['is_active']) ? 'active' : 'draft', 'visibility' => 'published']);
        if (! empty($r['featured_image'])) {
            $media = $this->media($r['featured_image'], $p->name);
            $p->update(['primary_media_id' => $media->id]);
        }
        if (empty($r['has_variants'])) {
            ProductVariant::query()->create(['workspace_id' => $this->workspace, 'product_id' => $p->id, 'sku' => $p->sku, 'meta_retailer_id' => 'legacy-'.$this->workspace.'-'.$r['id'], 'price' => $p->single_piece_price, 'stock_quantity' => $r['stock_quantity'] ?? 0, 'status' => 'active']);
        }

        return $p->id;
    }

    private function color(array $r): int
    {
        $color = $this->source->table('colors')->where('id', $r['color_id'])->first();
        if (! $color) {
            throw new RuntimeException('Missing legacy color.');
        }

        return ProductColor::query()->firstOrCreate(['workspace_id' => $this->workspace, 'product_id' => $this->mapped('products', $r['product_id']), 'name' => $color->name], ['hex_code' => $color->color_code ?? '#000000', 'position' => 0])->id;
    }

    private function productImage(array $r): int
    {
        $product = Product::query()->where('workspace_id', $this->workspace)->findOrFail($this->mapped('products', $r['product_id']));
        $media = $this->media($r['image_path'], $product->name);

        return ProductMedia::query()->create(['workspace_id' => $this->workspace, 'product_id' => $product->id, 'media_id' => $media->id, 'media_type' => 'image', 'role' => 'gallery', 'position' => $r['order'] ?? 0, 'is_primary' => (bool) ($r['is_primary'] ?? false)])->id;
    }

    private function colorImage(array $r): int
    {
        $color = ProductColor::query()->where('workspace_id', $this->workspace)->findOrFail($this->mapped('product_color_variants', $r['product_color_variant_id']));
        $media = $this->media($r['image_path'], $color->name);

        return ProductMedia::query()->create(['workspace_id' => $this->workspace, 'product_id' => $color->product_id, 'color_id' => $color->id, 'media_id' => $media->id, 'media_type' => 'image', 'role' => 'gallery', 'position' => $r['order'] ?? 0, 'is_primary' => false])->id;
    }

    private function media(string $source, string $name): Media
    {
        $path = $this->assetPath($source);

        return Media::query()->firstOrCreate(['disk' => 'public', 'path' => $path], ['name' => $name, 'file_name' => basename($path), 'original_name' => basename($source), 'mime_type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream', 'extension' => pathinfo($path, PATHINFO_EXTENSION), 'type' => 'image', 'size' => Storage::disk('public')->size($path)]);
    }

    private function variant(array $r): int
    {
        $p = Product::query()->findOrFail($this->mapped('products', $r['product_id']));
        $size = $this->source->table('sizes')->where('id', $r['size_id'])->first();
        $v = ProductVariant::query()->where('workspace_id', $this->workspace)->where('product_id', $p->id)->where('sku', $r['sku'])->first();
        if ($v) {
            return $v->id;
        }

        return ProductVariant::query()->create(['workspace_id' => $this->workspace, 'product_id' => $p->id, 'sku' => $r['sku'] ?: 'legacy-variant-'.$r['id'], 'meta_retailer_id' => 'legacy-size-'.$this->workspace.'-'.$r['id'], 'color_id' => empty($r['product_color_variant_id']) ? null : $this->mapped('product_color_variants', $r['product_color_variant_id']), 'size' => $size?->name ?? (string) $r['size_id'], 'price' => (float) $p->single_piece_price + (float) ($r['price_adjustment'] ?? 0), 'stock_quantity' => $r['stock_quantity'], 'status' => ! empty($r['is_available']) ? 'active' : 'out_of_stock'])->id;
    }

    private function address(array $r): int
    {
        return CustomerAddress::query()->create(['workspace_id' => $this->workspace, 'customer_id' => $this->mapped('users', $r['user_id']), 'label' => $r['label'] ?: 'Home', 'is_default' => (bool) $r['is_default'], 'address' => ['name' => $r['recipient_name'], 'phone' => $r['phone'], 'line1' => $r['address_line_1'], 'line2' => $r['address_line_2'] ?? null, 'city' => $r['city'], 'state' => $r['state'] ?? null, 'postal_code' => $r['postal_code'] ?? null, 'country' => strtoupper($r['country'])]])->id;
    }

    private function coupon(array $r): int
    {
        if (! empty($r['currency']) && $r['currency'] !== StoreOrderSetting::catalogCurrency($this->workspace)) {
            throw new RuntimeException('Coupon currency differs from the store.');
        }
        $products = json_decode($r['marketing_product_ids'] ?? '[]', true) ?: [];
        $categories = json_decode($r['marketing_category_ids'] ?? '[]', true) ?: [];
        if (($r['scope'] ?? 'all') !== 'all' && ! $products && ! $categories) {
            throw new RuntimeException('Coupon scope requires explicit product/category mapping.');
        }
        foreach ($products as $id) {
            if (! Product::query()->where('workspace_id', $this->workspace)->whereKey($id)->exists()) {
                throw new RuntimeException('Coupon product belongs to another workspace or is missing.');
            }
        }
        foreach ($categories as $id) {
            if (! Category::query()->where('workspace_id', $this->workspace)->whereKey($id)->exists()) {
                throw new RuntimeException('Coupon category belongs to another workspace or is missing.');
            }
        }
        if (Coupon::query()->where('workspace_id', $this->workspace)->where('code', strtoupper($r['code']))->exists()) {
            throw new RuntimeException('Coupon code already exists; review before merging usage history.');
        }

        return Coupon::query()->create(['workspace_id' => $this->workspace, 'code' => strtoupper($r['code']), 'type' => $r['type'], 'value' => $r['value'], 'min_order' => $r['min_order_amount'] ?? 0, 'max_discount' => $r['max_discount'], 'usage_limit' => $r['usage_limit'], 'per_customer_limit' => $r['usage_limit_per_user'], 'starts_at' => $r['starts_at'], 'expires_at' => $r['expires_at'], 'active' => (bool) $r['is_active'], 'product_ids' => $products, 'category_ids' => $categories])->id;
    }

    private function order(array $r): int
    {
        $customer = Customer::query()->findOrFail($this->mapped('users', $r['user_id']));
        if (! empty($r['marketing_reference'])) {
            $existing = Order::query()->where('workspace_id', $this->workspace)->where('submission_reference', $r['marketing_reference'])->first();
            if ($existing) {
                if ($existing->contact_id && $existing->contact_id !== $customer->contact_id) {
                    throw new RuntimeException('Existing order belongs to a different contact.');
                }$existing->update(['contact_id' => $customer->contact_id]);

                return $existing->id;
            }
        }
        $status = match ($r['status']) {
            'pending' => 'awaiting_payment','confirmed' => 'processing','delivered' => 'completed','cancelled' => 'cancelled','shipped' => 'shipped','processing' => 'processing','refunded' => 'cancelled',default => null
        };
        if (! $status) {
            throw new RuntimeException('Legacy order status requires manual mapping.');
        }
        $address = [];
        foreach (['name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country'] as $field) {
            $address[$field] = match ($field) {
                'name' => trim(($r['shipping_first_name'] ?? '').' '.($r['shipping_last_name'] ?? '')),'line1' => $r['shipping_address_line1'] ?? '','line2' => $r['shipping_address_line2'] ?? '',default => $r['shipping_'.$field] ?? ''
            };
        }

        return Order::query()->create(['workspace_id' => $this->workspace, 'contact_id' => $customer->contact_id, 'source' => 'legacy_import', 'submission_reference' => $r['marketing_reference'] ?: (string) Str::uuid(), 'number' => $r['order_number'], 'status' => $status, 'payment_state' => in_array($r['payment_status'], ['paid', 'completed']) ? 'paid' : 'unpaid', 'currency' => $r['currency'] ?: StoreOrderSetting::catalogCurrency($this->workspace), 'subtotal' => $r['subtotal'], 'discount_amount' => $r['discount_amount'] ?? 0, 'shipping_amount' => $r['shipping_cost'] ?? 0, 'total' => $r['total_amount'], 'shipping_address' => $address, 'customer_snapshot' => $customer->contact->only(['name', 'phone', 'email']), 'tracking_number' => $r['tracking_number'] ?? null, 'tracking_url' => $r['tracking_url'] ?? null, 'inventory_adjusted_at' => now(), 'provider_payload' => ['legacy' => ['source' => $this->sourceName, 'order_id' => $r['id'], 'payment_method' => $r['payment_method'], 'tax_amount' => $r['tax_amount'] ?? 0]], 'created_at' => $r['created_at'], 'updated_at' => $r['updated_at']])->id;
    }

    private function variantFor(array $r): ProductVariant
    {
        if (! empty($r['product_size_variant_id'])) {
            return ProductVariant::query()->findOrFail($this->mapped('product_size_variants', $r['product_size_variant_id']));
        }
        $q = ProductVariant::query()->where('workspace_id', $this->workspace)->where('product_id', $this->mapped('products', $r['product_id']));
        if (! empty($r['product_color_variant_id'])) {
            $q->where('color_id', $this->mapped('product_color_variants', $r['product_color_variant_id']));
        }
        if ($q->count() !== 1) {
            throw new RuntimeException('Cannot unambiguously map product variant.');
        }

        return $q->firstOrFail();
    }

    private function orderItem(array $r): int
    {
        $order = Order::query()->findOrFail($this->mapped('orders', $r['order_id']));
        if ($order->source !== 'legacy_import') {
            return $order->id;
        }
        $v = $this->variantFor($r);

        return OrderItem::query()->create(['workspace_id' => $this->workspace, 'order_id' => $order->id, 'product_id' => $v->product_id, 'variant_id' => $v->id, 'product_name' => $r['product_name'], 'sku' => $r['product_sku'], 'quantity' => $r['quantity'], 'unit_price' => $r['unit_price'], 'line_total' => $r['total']])->id;
    }

    private function payment(array $r): int
    {
        $order = Order::query()->findOrFail($this->mapped('orders', $r['order_id']));
        if ($order->source !== 'legacy_import') {
            return $order->id;
        }
        if (! in_array($r['status'] ?? '', ['paid', 'completed', 'successful', 'success', 'verified'])) {
            throw new RuntimeException('Unsettled historical payment retained in source for review.');
        }

        return OrderPayment::query()->create(['workspace_id' => $this->workspace, 'order_id' => $order->id, 'amount' => $r['amount'], 'currency' => $r['currency'] ?? $order->currency, 'method' => $r['payment_method_id'] ?? 'manual', 'reference' => $r['transaction_reference'] ?? null, 'recorded_at' => $r['verified_at'] ?? $r['created_at'], 'submission_reference' => (string) Str::uuid()])->id;
    }

    private function review(array $r): int
    {
        $customer = Customer::query()->where('workspace_id', $this->workspace)->findOrFail($this->mapped('users', $r['user_id']));
        $product = Product::query()->where('workspace_id', $this->workspace)->findOrFail($this->mapped('products', $r['product_id']));
        $order = Order::query()->where('workspace_id', $this->workspace)->findOrFail($this->mapped('orders', $r['order_id']));
        if ($order->contact_id !== $customer->contact_id || $order->status !== 'completed') {
            throw new RuntimeException('Review is not linked to a completed purchase by this customer.');
        }
        if (! OrderItem::query()->where('order_id', $order->id)->where('product_id', $product->id)->exists()) {
            throw new RuntimeException('Reviewed product is absent from the historical order.');
        }

        return ProductReview::query()->firstOrCreate(['workspace_id' => $this->workspace, 'customer_id' => $customer->id, 'product_id' => $product->id], ['rating' => $r['rating'], 'body' => $r['review_text'], 'status' => ! empty($r['is_published']) ? 'approved' : 'pending'])->id;
    }

    private function cart(array $r): int
    {
        if (empty($r['user_id'])) {
            throw new RuntimeException('Guest cookie sessions cannot be transferred across hosts.');
        }

        return app(CartService::class)->create($this->workspace, $this->mapped('users', $r['user_id']))[0]->id;
    }

    private function cartItem(array $r): int
    {
        $v = $this->variantFor($r);

        return CartItem::query()->firstOrCreate(['workspace_id' => $this->workspace, 'cart_id' => $this->mapped('carts', $r['cart_id']), 'variant_id' => $v->id], ['product_id' => $v->product_id, 'quantity' => $r['quantity'], 'price_snapshot' => $r['price']])->id;
    }

    private function slide(array $r): int
    {
        $slider = StorefrontSlider::query()->firstOrCreate(['workspace_id' => $this->workspace, 'name' => 'Legacy home'], ['slides' => []]);
        $slides = $slider->slides;
        $slides[] = ['title' => $r['title'], 'subtitle' => $r['subtitle'], 'image' => $this->asset($r['media_path']), 'mobile_image' => empty($r['mobile_media_path']) ? null : $this->asset($r['mobile_media_path']), 'video' => $r['content_type'] === 'video' ? $this->asset($r['media_path']) : null, 'button_text' => $r['button_text'], 'button_link' => $r['button_link'] ?: '/products', 'active' => (bool) $r['is_active'], 'position' => $r['order']];
        usort($slides, fn ($a, $b) => $a['position'] <=> $b['position']);
        $slider->update(['slides' => $slides]);

        return $slider->id;
    }

    private function page(array $r): int
    {
        if (StorefrontPage::query()->where('workspace_id', $this->workspace)->where('slug', $r['slug'])->exists()) {
            throw new RuntimeException('CMS slug already exists.');
        }$p = StorefrontPage::query()->create(['workspace_id' => $this->workspace, 'slug' => $r['slug'], 'title' => $r['title'], 'seo' => ['title' => $r['meta_title'], 'description' => $r['meta_description']], 'status' => 'draft']);
        StorefrontSection::query()->create(['workspace_id' => $this->workspace, 'page_id' => $p->id, 'type' => 'rich_text', 'config' => ['text' => $r['content']], 'active' => true]);

        return $p->id;
    }

    private function section(array $r): int
    {
        $p = StorefrontPage::query()->firstOrCreate(['workspace_id' => $this->workspace, 'slug' => 'home'], ['title' => 'Home']);
        $type = match ($r['section_type']) {
            'product_section' => 'product_carousel','cta_section' => 'cta',default => $r['section_type']
        };
        $config = json_decode($r['config'], true) ?: [];
        if ($type === 'product_carousel') {
            throw new RuntimeException('Dynamic product rule requires owner review before publication.');
        }$s = app(CmsService::class)->validateSection(['type' => $type, 'config' => ['title' => $r['name'], ...$config]]);

        return StorefrontSection::query()->create(['workspace_id' => $this->workspace, 'page_id' => $p->id, ...$s, 'position' => $r['order'], 'active' => (bool) $r['is_active']])->id;
    }

    private function menu(array $r): int
    {
        if (empty($r['is_active'])) {
            throw new RuntimeException('Inactive menu entry retained in source.');
        }
        $href = $r['url'] ?? '';
        if ($r['type'] === 'category') {
            $cat = Category::query()->findOrFail($this->mapped('categories', $r['target_id']));
            $href = '/category/'.$cat->slug;
        }
        if (! preg_match('#^(?:/(?!/)|https://)#', $href)) {
            throw new RuntimeException('Menu target requires mapping.');
        }
        $menu = StorefrontMenu::query()->firstOrCreate(['workspace_id' => $this->workspace, 'slot' => 'header'], ['items' => []]);
        $items = $menu->items;
        $items[] = ['label' => $r['name'], 'href' => $href];
        $menu->update(['items' => $items]);

        return $menu->id;
    }

    private function setting(array $r): int
    {
        $keys = ['site_name' => 'name', 'store_name' => 'name', 'site_logo' => 'logo', 'contact_email' => 'contact_email', 'contact_phone' => 'contact_phone', 'footer_text' => 'footer_text'];
        if (! isset($keys[$r['key']])) {
            throw new RuntimeException('Setting has no automatic storefront mapping.');
        }$s = StorefrontSetting::query()->firstOrCreate(['workspace_id' => $this->workspace], ['config' => []]);
        $s->update(['config' => [...$s->config, $keys[$r['key']] => $r['key'] === 'site_logo' ? $this->asset($r['value']) : $r['value']]]);

        return $s->id;
    }

    private function assembleHome(): void
    {
        $slider = StorefrontSlider::query()->where('workspace_id', $this->workspace)->where('name', 'Legacy home')->first();
        if (! $slider) {
            return;
        }$page = StorefrontPage::query()->firstOrCreate(['workspace_id' => $this->workspace, 'slug' => 'home'], ['title' => 'Home']);
        StorefrontSection::query()->firstOrCreate(['workspace_id' => $this->workspace, 'page_id' => $page->id, 'type' => 'hero_slider'], ['config' => ['slider_id' => $slider->id], 'position' => 0, 'active' => true]);
    }

    private function asset(string $path): string
    {
        return Storage::disk('public')->url($this->assetPath($path));
    }

    private function assetPath(string $path): string
    {
        if (str_starts_with($path, 'https://')) {
            throw new RuntimeException('Remote media must be copied to managed storage before retirement.');
        }
        if (! $this->mediaRoot) {
            throw new RuntimeException('Specify --media-root for local media migration.');
        }
        $file = realpath($this->mediaRoot.'/'.ltrim($path, '/'));
        if (! $file || ! str_starts_with($file, $this->mediaRoot.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            throw new RuntimeException('Missing or out-of-root legacy media.');
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif', 'mp4', 'webm'])) {
            throw new RuntimeException('Unsupported media type.');
        }
        $target = 'storefront-import/'.$this->workspace.'/'.hash_file('sha256', $file).'.'.$ext;
        if (! Storage::disk('public')->exists($target)) {
            $stream = fopen($file, 'rb');
            try {
                Storage::disk('public')->put($target, $stream);
            } finally {
                fclose($stream);
            }
        }

        return $target;
    }
}
