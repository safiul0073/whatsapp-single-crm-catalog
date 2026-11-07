<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_invoice_requests')) {
            Schema::create('commerce_invoice_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('commerce_customers')->cascadeOnDelete();
                $table->text('message');
                $table->json('items')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('owner_note')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status']);
                $table->index(['customer_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('commerce_custom_invoices')) {
            Schema::create('commerce_custom_invoices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('commerce_customers')->nullOnDelete();
                $table->foreignId('invoice_request_id')->nullable()->constrained('commerce_invoice_requests')->nullOnDelete();
                $table->unsignedInteger('sequence');
                $table->string('number', 40);
                $table->string('token', 64)->unique();
                $table->string('status', 20)->default('draft');
                $table->string('bill_to_name');
                $table->string('bill_to_phone', 40)->nullable();
                $table->string('bill_to_email')->nullable();
                $table->text('bill_to_address')->nullable();
                $table->string('currency', 3);
                $table->decimal('subtotal', 19, 4)->default(0);
                $table->decimal('discount_amount', 19, 4)->default(0);
                $table->decimal('tax_amount', 19, 4)->default(0);
                $table->decimal('shipping_amount', 19, 4)->default(0);
                $table->decimal('total', 19, 4)->default(0);
                $table->text('notes')->nullable();
                $table->date('due_date')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'sequence']);
                $table->unique(['workspace_id', 'number']);
                $table->index(['workspace_id', 'status']);
                $table->index(['customer_id', 'status']);
            });
        }

        if (! Schema::hasTable('commerce_custom_invoice_items')) {
            Schema::create('commerce_custom_invoice_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('custom_invoice_id')->constrained('commerce_custom_invoices')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('commerce_products')->nullOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('commerce_product_variants')->nullOnDelete();
                $table->string('name');
                $table->string('description', 500)->nullable();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_price', 19, 4);
                $table->decimal('line_total', 19, 4);
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();
                $table->index(['custom_invoice_id', 'position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_custom_invoice_items');
        Schema::dropIfExists('commerce_custom_invoices');
        Schema::dropIfExists('commerce_invoice_requests');
    }
};
