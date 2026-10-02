<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addMissing('commerce_orders', [
            'source' => fn (Blueprint $table) => $table->string('source')->default('native_whatsapp'),
            'submission_reference' => fn (Blueprint $table) => $table->string('submission_reference')->nullable(),
            'payload_hash' => fn (Blueprint $table) => $table->string('payload_hash', 64)->nullable(),
            'customer_reference' => fn (Blueprint $table) => $table->string('customer_reference')->nullable()->index(),
            'customer_snapshot' => fn (Blueprint $table) => $table->json('customer_snapshot')->nullable(),
            'tracking_code' => fn (Blueprint $table) => $table->string('tracking_code', 64)->nullable()->unique(),
            'payment_state' => fn (Blueprint $table) => $table->string('payment_state')->default('unpaid'),
            'payment_instructions' => fn (Blueprint $table) => $table->text('payment_instructions')->nullable(),
            'payment_evidence' => fn (Blueprint $table) => $table->json('payment_evidence')->nullable(),
            'discount_amount' => fn (Blueprint $table) => $table->decimal('discount_amount', 19, 4)->default(0),
            'adjustments' => fn (Blueprint $table) => $table->json('adjustments')->nullable(),
            'shipping_quote_required' => fn (Blueprint $table) => $table->boolean('shipping_quote_required')->default(false),
            'packed_at' => fn (Blueprint $table) => $table->timestamp('packed_at')->nullable(),
            'delivered_at' => fn (Blueprint $table) => $table->timestamp('delivered_at')->nullable(),
        ]);
        if (! Schema::hasIndex('commerce_orders', 'commerce_orders_workspace_id_submission_reference_unique')) {
            Schema::table('commerce_orders', fn (Blueprint $table) => $table->unique(['workspace_id', 'submission_reference']));
        }
        Schema::table('commerce_orders', fn (Blueprint $table) => $table->string('provider_message_id')->nullable()->change());
        $this->addMissing('commerce_order_items', ['group_id' => fn (Blueprint $table) => $table->unsignedBigInteger('group_id')->nullable()->index()]);
        $this->addMissing('commerce_inventory_movements', ['metadata' => fn (Blueprint $table) => $table->json('metadata')->nullable()]);
        foreach ([
            'commerce_variant_presets' => ['price_delta'],
            'commerce_products' => ['single_piece_price', 'wholesale_price'],
            'commerce_product_variants' => ['price', 'compare_at_price', 'cost_price'],
            'commerce_product_tier_prices' => ['unit_price'],
            'commerce_orders' => ['subtotal', 'shipping_amount', 'shipping_subtotal', 'shipping_discount', 'total'],
            'commerce_order_items' => ['unit_price', 'line_total', 'provider_unit_price'],
            'shipping_rates' => ['price', 'price_per_kg'],
        ] as $table => $columns) {
            $this->widenMoney($table, $columns);
        }
    }

    private function addMissing(string $table, array $definitions): void
    {
        foreach ($definitions as $name => $definition) {
            if (! Schema::hasColumn($table, $name)) {
                Schema::table($table, $definition);
            }
        }
    }

    private function widenMoney(string $table, array $names): void
    {
        foreach (Schema::getColumns($table) as $column) {
            if (! in_array($column['name'], $names, true) || strtolower($column['type']) === 'decimal(19,4)') {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $money = $blueprint->decimal($column['name'], 19, 4)->nullable($column['nullable']);
                $default = $column['default'] === null ? null : trim((string) $column['default'], "'\"");
                if ($default !== null && strtoupper($default) !== 'NULL') {
                    $money->default($default);
                }
                $money->change();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This data-preserving upgrade cannot be rolled back by dropping fields or reducing precision. Restore the pre-upgrade backup if required.');
    }
};
