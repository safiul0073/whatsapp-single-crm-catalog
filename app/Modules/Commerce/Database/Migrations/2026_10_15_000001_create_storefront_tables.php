<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_storefront_contents', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('type');
            $t->string('slug');
            $t->json('data');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'type', 'slug'], 'storefront_content_slug_unique');
        });
        Schema::create('commerce_store_domains', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('host')->unique();
            $t->string('handle')->nullable()->unique();
            $t->string('type')->default('custom');
            $t->boolean('is_primary')->default(false);
            $t->string('status')->default('pending_dns');
            $t->string('verification_token');
            $t->string('verification_error')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->string('ssl_status')->default('unprovisioned');
            $t->timestamps();
        });
        Schema::create('commerce_storefront_settings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->json('config')->nullable();
            $t->unique('workspace_id');
            $t->timestamps();
        });
        Schema::create('commerce_storefront_pages', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('slug');
            $t->string('title');
            $t->json('seo')->nullable();
            $t->string('status')->default('draft');
            $t->json('published_content')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->unique(['workspace_id', 'slug']);
            $t->timestamps();
        });
        Schema::create('commerce_storefront_sections', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('page_id')->constrained('commerce_storefront_pages')->cascadeOnDelete();
            $t->string('type');
            $t->json('config');
            $t->unsignedInteger('position')->default(0);
            $t->boolean('active')->default(true);
            $t->string('visibility')->default('all');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });
        Schema::create('commerce_storefront_menus', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('slot');
            $t->json('items');
            $t->unique(['workspace_id', 'slot']);
            $t->timestamps();
        });
        Schema::create('commerce_storefront_sliders', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('name');
            $t->json('slides');
            $t->timestamps();
        });
        Schema::create('commerce_customers', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $t->string('password')->nullable();
            $t->string('verified_phone', 30)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('registered_at')->nullable();
            $t->unique(['workspace_id', 'contact_id']);
            $t->unique(['workspace_id', 'verified_phone']);
            $t->timestamps();
        });
        Schema::create('commerce_customer_addresses', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->string('label')->default('Home');
            $t->json('address');
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });
        Schema::create('commerce_carts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('commerce_customers')->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->string('currency', 3);
            $t->string('coupon_code')->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('commerce_pack_templates', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->boolean('published')->default(false);
            $t->unsignedInteger('position')->default(0);
            $t->json('product_ids');
            foreach (['min_pieces', 'min_products', 'min_colors', 'min_sizes', 'min_variant_quantity'] as $field) {
                $t->unsignedInteger($field)->default(1);
            }
            $t->unsignedInteger('max_pieces')->nullable();
            $t->timestamps();
        });
        Schema::create('commerce_cart_groups', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('cart_id')->constrained('commerce_carts')->cascadeOnDelete();
            $t->json('selection');
            $t->string('request_key', 36)->nullable();
            $t->string('request_hash', 64)->nullable();
            $t->timestamp('removed_at')->nullable();
            $t->unique(['cart_id', 'request_key']);
            $t->timestamps();
        });
        Schema::create('commerce_cart_items', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('cart_id')->constrained('commerce_carts')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('commerce_products');
            $t->foreignId('variant_id')->constrained('commerce_product_variants');
            $t->unsignedInteger('quantity');
            $t->decimal('price_snapshot', 18, 4)->default(0);
            $t->foreignId('group_id')->nullable()->constrained('commerce_cart_groups')->cascadeOnDelete();
            $t->unique(['cart_id', 'group_id', 'variant_id']);
            $t->timestamps();
        });
        Schema::create('commerce_coupons', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('code');
            $t->string('type');
            $t->decimal('value', 18, 4);
            $t->decimal('min_order', 18, 4)->default(0);
            $t->decimal('max_discount', 18, 4)->nullable();
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('per_customer_limit')->nullable();
            $t->json('product_ids')->nullable();
            $t->json('category_ids')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('active')->default(true);
            $t->unique(['workspace_id', 'code']);
            $t->timestamps();
        });
        Schema::create('commerce_coupon_usages', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('coupon_id')->constrained('commerce_coupons');
            $t->foreignId('customer_id')->constrained('commerce_customers');
            $t->foreignId('order_id')->constrained('commerce_orders');
            $t->unique(['coupon_id', 'order_id']);
            $t->timestamps();
        });
        Schema::create('commerce_wishlists', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $t->unique(['customer_id', 'product_id']);
            $t->timestamps();
        });
        Schema::create('commerce_product_reviews', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers');
            $t->foreignId('product_id')->constrained('commerce_products');
            $t->unsignedTinyInteger('rating');
            $t->text('body');
            $t->string('status')->default('pending');
            $t->unique(['customer_id', 'product_id']);
            $t->timestamps();
        });
        Schema::create('commerce_checkout_submissions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers');
            $t->string('key', 64);
            $t->string('request_hash', 64);
            $t->json('payload');
            $t->foreignId('order_id')->nullable()->constrained('commerce_orders');
            $t->unique(['workspace_id', 'customer_id', 'key'], 'storefront_checkout_key_unique');
            $t->timestamps();
        });
        Schema::create('commerce_legacy_mappings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('source', 80);
            $t->string('entity', 80);
            $t->string('legacy_id', 80);
            $t->unsignedBigInteger('target_id')->nullable();
            $t->string('status')->default('pending');
            $t->json('snapshot')->nullable();
            $t->unique(['workspace_id', 'source', 'entity', 'legacy_id'], 'commerce_legacy_source_unique');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_storefront_contents');
        Schema::dropIfExists('commerce_legacy_mappings');
        Schema::dropIfExists('commerce_checkout_submissions');
        Schema::dropIfExists('commerce_product_reviews');
        Schema::dropIfExists('commerce_wishlists');
        Schema::dropIfExists('commerce_coupon_usages');
        Schema::dropIfExists('commerce_coupons');
        Schema::dropIfExists('commerce_cart_items');
        Schema::dropIfExists('commerce_cart_groups');
        Schema::dropIfExists('commerce_pack_templates');
        Schema::dropIfExists('commerce_carts');
        Schema::dropIfExists('commerce_customer_addresses');
        Schema::dropIfExists('commerce_customers');
        Schema::dropIfExists('commerce_storefront_sliders');
        Schema::dropIfExists('commerce_storefront_menus');
        Schema::dropIfExists('commerce_storefront_sections');
        Schema::dropIfExists('commerce_storefront_pages');
        Schema::dropIfExists('commerce_storefront_settings');
        Schema::dropIfExists('commerce_store_domains');
    }
};
