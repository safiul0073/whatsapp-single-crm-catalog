<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_store_order_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('reservation_hours')->default(24);
            $table->boolean('whatsapp_notifications')->default(false);
            $table->unsignedBigInteger('whatsapp_channel_id')->nullable();
            $table->unsignedBigInteger('whatsapp_template_id')->nullable();
            $table->text('payment_instructions')->nullable();
            $table->string('integration_token_hash', 64)->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('commerce_order_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('color_id')->nullable();
            $table->string('mode');
            $table->string('product_name');
            $table->string('color_name')->nullable();
            $table->unsignedInteger('box_count')->default(0);
            $table->json('ratio')->nullable();
            $table->unsignedInteger('multiplier')->default(1);
            $table->unsignedInteger('pieces_per_box')->default(0);
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
        Schema::create('commerce_order_boxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('commerce_order_groups')->nullOnDelete();
            $table->string('label');
            $table->string('kind');
            $table->timestamp('packed_at')->nullable();
            $table->unique(['order_id', 'label']);
            $table->timestamps();
        });
        Schema::create('commerce_order_box_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained('commerce_order_boxes')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('commerce_order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unique(['box_id', 'order_item_id']);
            $table->timestamps();
        });
        Schema::create('commerce_order_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('commerce_product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('state')->default('reserved');
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('generation')->default(1);
            $table->string('operation_key', 100)->unique();
            $table->unique(['order_id', 'variant_id']);
            $table->timestamps();
        });
        Schema::create('commerce_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->timestamp('occurred_at');
            $table->unsignedTinyInteger('notification_attempts')->default(0);
            $table->timestamp('customer_notified_at')->nullable();
            $table->timestamp('staff_notified_at')->nullable();
            $table->timestamp('whatsapp_notified_at')->nullable();
            $table->unique(['order_id', 'key']);
            $table->timestamps();
        });
        Schema::create('commerce_order_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('commerce_orders')->cascadeOnDelete();
            $table->string('carrier');
            $table->string('tracking_number', 150);
            $table->string('tracking_url', 2048)->nullable();
            $table->timestamp('shipped_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['commerce_order_shipments', 'commerce_order_events', 'commerce_order_reservations', 'commerce_order_box_items', 'commerce_order_boxes', 'commerce_order_groups', 'commerce_store_order_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
