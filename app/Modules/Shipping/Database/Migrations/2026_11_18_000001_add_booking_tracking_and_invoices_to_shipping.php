<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_carrier_accounts', function (Blueprint $table) {
            $table->string('origin_phone', 30)->nullable()->after('origin_country_code');
            $table->boolean('booking_enabled')->default(false)->after('is_active');
        });

        Schema::table('shipping_quotes', function (Blueprint $table) {
            $table->string('service_code', 40)->nullable()->after('service');
        });

        Schema::table('shipping_shipments', function (Blueprint $table) {
            $table->string('service_code', 40)->nullable()->after('service');
            $table->string('booking_source', 10)->nullable()->after('tracking_url');
            $table->string('booking_reference', 80)->nullable()->after('booking_source');
            $table->json('package_tracking_numbers')->nullable()->after('booking_reference');
            $table->foreignId('booked_by')->nullable()->after('package_tracking_numbers')->constrained('users')->nullOnDelete();
            $table->timestamp('booked_at')->nullable()->after('booked_by');
            $table->foreignId('void_requested_by')->nullable()->after('booked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('void_requested_at')->nullable()->after('void_requested_by');
            $table->string('void_reason', 255)->nullable()->after('void_requested_at');
            $table->foreignId('voided_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->string('tracking_status', 160)->nullable()->after('voided_at');
            $table->timestamp('tracking_synced_at')->nullable()->after('tracking_status');
            $table->string('tracking_error', 255)->nullable()->after('tracking_synced_at');
            $table->string('exception_reason', 255)->nullable()->after('tracking_error');
            $table->timestamp('exception_at')->nullable()->after('exception_reason');

            $table->index(['status', 'tracking_synced_at'], 'shipping_shipments_tracking_due_index');
        });

        Schema::create('shipping_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_shipment_id')->constrained('shipping_shipments')->cascadeOnDelete();
            $table->string('tracking_number', 80);
            $table->string('status', 20);
            $table->string('code', 20)->nullable();
            $table->string('description', 255);
            $table->string('location', 160)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('fingerprint', 40);
            $table->timestamps();

            $table->unique(['shipping_shipment_id', 'fingerprint'], 'shipping_tracking_events_fingerprint_unique');
            $table->index(['shipping_shipment_id', 'occurred_at'], 'shipping_tracking_events_time_index');
        });

        Schema::create('shipping_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_shipment_id')->constrained('shipping_shipments')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('source', 10)->default('upload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shipping_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_agent_id')->nullable()->constrained('shipping_agents')->nullOnDelete();
            $table->foreignId('shipping_carrier_account_id')->nullable()->constrained('shipping_carrier_accounts')->nullOnDelete();
            $table->string('carrier', 20)->nullable();
            $table->string('invoice_number', 80);
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 20, 8)->default(1);
            $table->decimal('stated_total', 19, 4)->nullable();
            $table->decimal('lines_total', 19, 4)->default(0);
            $table->decimal('paid_amount', 19, 4)->default(0);
            $table->string('status', 20)->default('draft');
            $table->string('payment_status', 20)->default('unpaid');
            $table->string('source_type', 10)->default('manual');
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'invoice_number']);
        });

        Schema::create('shipping_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_invoice_id')->constrained('shipping_invoices')->cascadeOnDelete();
            $table->foreignId('shipping_shipment_id')->nullable()->constrained('shipping_shipments')->nullOnDelete();
            $table->string('tracking_number', 80)->nullable();
            $table->string('reference', 80)->nullable();
            $table->date('ship_date')->nullable();
            $table->decimal('billed_weight_kg', 10, 3)->nullable();
            $table->decimal('base_amount', 19, 4)->default(0);
            $table->decimal('total_amount', 19, 4)->default(0);
            $table->string('match_status', 20)->default('unmatched');
            $table->boolean('manually_linked')->default(false);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'tracking_number']);
            $table->index(['shipping_shipment_id', 'match_status']);
        });

        Schema::create('shipping_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('shipping_agent_id')->nullable()->constrained('shipping_agents')->nullOnDelete();
            $table->foreignId('shipping_carrier_account_id')->nullable()->constrained('shipping_carrier_accounts')->nullOnDelete();
            $table->foreignId('shipping_invoice_id')->nullable()->constrained('shipping_invoices')->nullOnDelete();
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3);
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('reference', 120)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'shipping_agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_payments');
        Schema::dropIfExists('shipping_invoice_lines');
        Schema::dropIfExists('shipping_invoices');
        Schema::dropIfExists('shipping_documents');
        Schema::dropIfExists('shipping_tracking_events');

        Schema::table('shipping_shipments', function (Blueprint $table) {
            $table->dropIndex('shipping_shipments_tracking_due_index');
            $table->dropConstrainedForeignId('booked_by');
            $table->dropConstrainedForeignId('void_requested_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['service_code', 'booking_source', 'booking_reference', 'package_tracking_numbers', 'booked_at', 'void_requested_at', 'void_reason', 'voided_at', 'tracking_status', 'tracking_synced_at', 'tracking_error', 'exception_reason', 'exception_at']);
        });

        Schema::table('shipping_quotes', function (Blueprint $table) {
            $table->dropColumn('service_code');
        });

        Schema::table('shipping_carrier_accounts', function (Blueprint $table) {
            $table->dropColumn(['origin_phone', 'booking_enabled']);
        });
    }
};
