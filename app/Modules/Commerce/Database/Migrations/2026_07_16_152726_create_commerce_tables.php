<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('commerce_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('commerce_brands', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('commerce_audiences', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('commerce_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('commerce_categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('commerce_brands')->nullOnDelete();
            $table->foreignId('audience_id')->nullable()->constrained('commerce_audiences')->nullOnDelete();
            $table->foreignIdFor(Media::class, 'primary_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->text('care_information')->nullable();
            $table->string('condition')->default('new');
            $table->string('audience')->nullable();
            $table->string('country_of_origin', 2)->default('BD');
            $table->string('status')->default('draft')->index();
            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('commerce_product_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->foreignIdFor(Media::class, 'media_id')->constrained('media')->cascadeOnDelete();
            $table->string('media_type', 20);
            $table->string('role', 20)->default('gallery');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'media_id']);
            $table->index(['product_id', 'position']);
        });

        Schema::create('commerce_product_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'code']);
        });

        Schema::create('commerce_product_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('commerce_product_options')->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['option_id', 'value']);
        });

        Schema::create('commerce_product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $table->foreignIdFor(Media::class, 'media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('sku');
            $table->string('meta_retailer_id');
            $table->json('attributes')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->json('package_dimensions')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['workspace_id', 'sku']);
            $table->unique(['workspace_id', 'meta_retailer_id']);
        });

        Schema::create('commerce_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->constrained()->cascadeOnDelete();
            $table->string('meta_catalog_id')->nullable();
            $table->string('feed_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->string('sync_mode', 20)->default('feed');
            $table->string('readiness_state', 30)->default('needs_setup');
            $table->boolean('cart_enabled')->default(false);
            $table->boolean('catalog_visible')->default(false);
            $table->string('last_sync_status', 30)->nullable();
            $table->json('last_sync_summary')->nullable();
            $table->unsignedInteger('last_item_count')->default(0);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_successful_at')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'channel_account_id']);
        });

        Schema::create('commerce_catalog_item_syncs', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained('commerce_catalogs')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('commerce_product_variants')->cascadeOnDelete();
            $table->string('retailer_id');
            $table->string('provider_item_id')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('provider_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['catalog_id', 'variant_id']);
            $table->unique(['catalog_id', 'retailer_id']);
        });

        Schema::create('commerce_catalog_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_id')->constrained('commerce_catalogs')->cascadeOnDelete();
            $table->string('mode', 20);
            $table->string('status', 30)->default('queued')->index();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('successful_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->json('summary')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commerce_message_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('idempotency_key', 64)->unique();
            $table->string('message_type', 30);
            $table->string('status', 30)->default('processing')->index();
            $table->json('request_payload');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('commerce_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Conversation::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_id')->nullable()->constrained('commerce_catalogs')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('provider_message_id');
            $table->string('provider_catalog_id')->nullable();
            $table->string('status')->default('requested')->index();
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->json('shipping_address')->nullable();
            $table->string('delivery_method')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->text('duties_disclosure')->nullable();
            $table->string('payment_url', 2048)->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url', 2048)->nullable();
            $table->timestamp('inventory_adjusted_at')->nullable();
            $table->timestamp('inventory_restored_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->json('issues')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
            $table->unique(['channel_account_id', 'provider_message_id']);
            $table->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('commerce_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('commerce_product_variants')->nullOnDelete();
            $table->string('retailer_id');
            $table->string('sku')->nullable();
            $table->string('product_name');
            $table->json('attributes')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->decimal('provider_unit_price', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('commerce_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('commerce_product_variants')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('commerce_orders')->nullOnDelete();
            $table->integer('quantity_delta');
            $table->string('reason');
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_inventory_movements');
        Schema::dropIfExists('commerce_order_items');
        Schema::dropIfExists('commerce_orders');
        Schema::dropIfExists('commerce_message_attempts');
        Schema::dropIfExists('commerce_catalog_sync_runs');
        Schema::dropIfExists('commerce_catalog_item_syncs');
        Schema::dropIfExists('commerce_catalogs');
        Schema::dropIfExists('commerce_product_variants');
        Schema::dropIfExists('commerce_product_option_values');
        Schema::dropIfExists('commerce_product_options');
        Schema::dropIfExists('commerce_product_media');
        Schema::dropIfExists('commerce_products');
        Schema::dropIfExists('commerce_audiences');
        Schema::dropIfExists('commerce_brands');
        Schema::dropIfExists('commerce_categories');
    }
};
