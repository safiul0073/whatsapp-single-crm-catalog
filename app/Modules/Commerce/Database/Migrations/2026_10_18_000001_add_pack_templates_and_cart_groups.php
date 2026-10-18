<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upgrade path for databases that already ran the storefront/fulfillment create migrations
 * before custom pack templates and explicit cart groups existed. Every step is guarded so
 * fresh installs (which already create these structures) are unaffected. Existing cart rows
 * keep their quantities with a null group_id, which the cart reports as "unconfirmed" until
 * the customer confirms retail or rebuilds a wholesale pack.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_pack_templates')) {
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
        }

        if (! Schema::hasTable('commerce_cart_groups')) {
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
        }

        if (Schema::hasTable('commerce_cart_items')) {
            if (! Schema::hasColumn('commerce_cart_items', 'group_id')) {
                Schema::table('commerce_cart_items', function (Blueprint $t): void {
                    $t->foreignId('group_id')->nullable()->after('price_snapshot')->constrained('commerce_cart_groups')->cascadeOnDelete();
                });
            }
            // Each operation has its own guard so retrying a partially applied upgrade is safe.
            if (! Schema::hasIndex('commerce_cart_items', 'commerce_cart_items_cart_id_group_id_variant_id_unique')) {
                Schema::table('commerce_cart_items', function (Blueprint $t): void {
                    $t->unique(['cart_id', 'group_id', 'variant_id']);
                });
            }
            if (Schema::hasIndex('commerce_cart_items', 'commerce_cart_items_cart_id_variant_id_unique')) {
                Schema::table('commerce_cart_items', function (Blueprint $t): void {
                    $t->dropUnique(['cart_id', 'variant_id']);
                });
            }
        }

        if (Schema::hasTable('commerce_order_groups') && ! Schema::hasColumn('commerce_order_groups', 'pack_snapshot')) {
            Schema::table('commerce_order_groups', function (Blueprint $t): void {
                $t->json('pack_snapshot')->nullable()->after('pieces_per_box');
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: pack data and cart group membership are retained.
    }
};
