<?php

use App\Modules\Media\Models\Media;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commerce_products', 'wizard_step')) {
            Schema::table('commerce_products', function (Blueprint $table): void {
                $table->unsignedTinyInteger('wizard_step')->default(1)->after('status');
                $table->timestamp('published_at')->nullable()->after('wizard_step');
            });
        }

        if (! Schema::hasTable('commerce_product_media')) {
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
        }

        if (! Schema::hasColumn('commerce_catalogs', 'sync_mode')) {
            Schema::table('commerce_catalogs', function (Blueprint $table): void {
                $table->string('sync_mode', 20)->default('feed')->after('is_active');
                $table->string('readiness_state', 30)->default('needs_setup')->after('sync_mode');
                $table->boolean('cart_enabled')->default(false)->after('readiness_state');
                $table->boolean('catalog_visible')->default(false)->after('cart_enabled');
                $table->string('last_sync_status', 30)->nullable()->after('catalog_visible');
                $table->json('last_sync_summary')->nullable()->after('last_sync_status');
                $table->timestamp('last_reconciled_at')->nullable()->after('last_successful_at');
            });
        }

        if (! Schema::hasTable('commerce_catalog_item_syncs')) {
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
        }

        if (! Schema::hasTable('commerce_catalog_sync_runs')) {
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
        }

        if (! Schema::hasTable('commerce_message_attempts')) {
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
        }

        DB::table('commerce_products')
            ->whereNotNull('primary_media_id')
            ->orderBy('id')
            ->each(function (object $product): void {
                $mediaType = DB::table('media')->where('id', $product->primary_media_id)->value('type');
                if (! $mediaType) {
                    return;
                }

                DB::table('commerce_product_media')->updateOrInsert(
                    ['product_id' => $product->id, 'media_id' => $product->primary_media_id],
                    ['workspace_id' => $product->workspace_id, 'media_type' => $mediaType, 'role' => 'primary', 'position' => 0, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_catalog_sync_runs');
        Schema::dropIfExists('commerce_catalog_item_syncs');
        Schema::dropIfExists('commerce_message_attempts');
        Schema::dropIfExists('commerce_product_media');
    }
};
