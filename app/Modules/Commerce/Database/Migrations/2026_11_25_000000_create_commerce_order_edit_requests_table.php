<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer requests to change an order after payment proof. The owner approves one edit at a time;
 * the request then records the total before and after so the owner can collect or refund the difference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_order_edit_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('commerce_customers')->nullOnDelete();
            $t->string('reference', 40);
            $t->uuid('submission_reference');
            $t->string('status', 20)->default('pending');
            $t->text('customer_note')->nullable();
            $t->text('owner_note')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->string('currency', 3)->nullable();
            $t->decimal('previous_total', 19, 4)->nullable();
            $t->decimal('new_total', 19, 4)->nullable();
            $t->decimal('difference', 19, 4)->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'reference'], 'commerce_order_edit_reference_unique');
            $t->unique(['workspace_id', 'submission_reference'], 'commerce_order_edit_submission_unique');
            $t->index(['order_id', 'status'], 'commerce_order_edit_order_status_index');
            $t->index(['workspace_id', 'status'], 'commerce_order_edit_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_order_edit_requests');
    }
};
