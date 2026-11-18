<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_carrier_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('carrier', 20);
            $table->string('account_number', 60)->nullable();
            $table->string('environment', 20)->default('sandbox');
            $table->text('credentials')->nullable();
            $table->unsignedInteger('dim_divisor')->default(5000);
            $table->decimal('weight_rounding_step', 5, 3)->default(0.5);
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'carrier']);
        });

        Schema::create('shipping_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('company_name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('carrier', 20)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->decimal('fuel_surcharge_percent', 6, 3)->default(0);
            $table->decimal('other_fee', 19, 4)->default(0);
            $table->string('other_fee_label', 80)->nullable();
            $table->unsignedInteger('dim_divisor')->default(5000);
            $table->decimal('weight_rounding_step', 5, 3)->default(0.5);
            $table->string('payment_status', 20)->default('clear');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
        });

        Schema::create('shipping_agent_rate_charts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_agent_id')->constrained('shipping_agents')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('source_type', 10)->default('manual');
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->date('effective_from')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status', 20)->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'shipping_agent_id', 'status'], 'shipping_charts_agent_status_index');
        });

        Schema::create('shipping_agent_rate_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_agent_rate_chart_id')->constrained('shipping_agent_rate_charts')->cascadeOnDelete();
            $table->string('country_code', 2)->nullable();
            $table->string('zone', 40)->nullable();
            $table->decimal('min_weight_kg', 8, 3)->default(0);
            $table->decimal('max_weight_kg', 8, 3)->nullable();
            $table->decimal('price', 19, 4)->default(0);
            $table->decimal('price_per_kg', 19, 4)->nullable();
            $table->string('transit_days', 40)->nullable();
            $table->timestamps();

            $table->index(['shipping_agent_rate_chart_id', 'country_code'], 'shipping_rate_rows_chart_country_index');
        });

        Schema::create('shipping_remote_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('carrier', 20)->nullable();
            $table->foreignId('shipping_agent_id')->nullable()->constrained('shipping_agents')->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->string('postal_from', 20)->nullable();
            $table->string('postal_to', 20)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('status', 20)->default('remote');
            $table->decimal('fee', 19, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'country_code']);
        });

        Schema::create('shipping_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->string('reference', 40);
            $table->string('status', 20)->default('pending');
            $table->string('recipient_name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('state', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('address_type', 20)->nullable();
            $table->timestamp('address_validated_at')->nullable();
            $table->foreignId('shipping_carrier_account_id')->nullable()->constrained('shipping_carrier_accounts')->nullOnDelete();
            $table->foreignId('shipping_agent_id')->nullable()->constrained('shipping_agents')->nullOnDelete();
            $table->string('carrier', 20)->nullable();
            $table->string('service', 80)->nullable();
            $table->unsignedInteger('total_sets')->default(0);
            $table->unsignedInteger('total_pieces')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->decimal('estimated_cost', 19, 4)->nullable();
            $table->decimal('approved_cost', 19, 4)->nullable();
            $table->decimal('final_cost', 19, 4)->nullable();
            $table->decimal('billed_amount', 19, 4)->nullable();
            $table->string('billed_currency', 3)->nullable();
            $table->decimal('billed_exchange_rate', 20, 8)->nullable();
            $table->unsignedBigInteger('approved_quote_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('tracking_number', 80)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->string('review_reason', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'reference']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'tracking_number']);
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('shipping_shipment_cartons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_shipment_id')->constrained('shipping_shipments')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('actual_weight_kg', 8, 3);
            $table->decimal('length_cm', 8, 2);
            $table->decimal('width_cm', 8, 2);
            $table->decimal('height_cm', 8, 2);
            $table->string('packaging_type', 40)->default('carton');
            $table->timestamps();
        });

        Schema::create('shipping_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_shipment_id')->constrained('shipping_shipments')->cascadeOnDelete();
            $table->foreignId('shipping_carrier_account_id')->nullable()->constrained('shipping_carrier_accounts')->nullOnDelete();
            $table->foreignId('shipping_agent_id')->nullable()->constrained('shipping_agents')->nullOnDelete();
            $table->string('carrier', 20)->nullable();
            $table->string('service', 80)->nullable();
            $table->string('source', 20);
            $table->decimal('billable_weight_kg', 10, 3)->nullable();
            $table->decimal('base_rate', 19, 4)->default(0);
            $table->decimal('total', 19, 4)->default(0);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->decimal('total_store', 19, 4)->default(0);
            $table->string('transit_days', 40)->nullable();
            $table->string('remote_status', 20)->default('unknown');
            $table->string('status', 20)->default('unverified');
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['shipping_shipment_id', 'status']);
        });

        Schema::create('shipping_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->morphs('chargeable');
            $table->string('reason', 30);
            $table->string('label', 120)->nullable();
            $table->decimal('amount', 19, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_order_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('commerce_orders')->cascadeOnDelete();
            $table->decimal('product_cost', 19, 4)->nullable();
            $table->decimal('other_costs', 19, 4)->default(0);
            $table->boolean('is_complete')->default(false);
            $table->json('items')->nullable();
            $table->timestamps();
        });

        Schema::create('shipping_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_shipment_id')->nullable()->constrained('shipping_shipments')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('summary', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_audit_entries');
        Schema::dropIfExists('shipping_order_costs');
        Schema::dropIfExists('shipping_charges');
        Schema::dropIfExists('shipping_quotes');
        Schema::dropIfExists('shipping_shipment_cartons');
        Schema::dropIfExists('shipping_shipments');
        Schema::dropIfExists('shipping_remote_areas');
        Schema::dropIfExists('shipping_agent_rate_rows');
        Schema::dropIfExists('shipping_agent_rate_charts');
        Schema::dropIfExists('shipping_agents');
        Schema::dropIfExists('shipping_carrier_accounts');
    }
};
