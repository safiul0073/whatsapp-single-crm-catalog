<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3);
            $table->string('method', 100);
            $table->string('reference', 150)->nullable();
            $table->decimal('tendered_amount', 19, 4)->nullable();
            $table->decimal('change_amount', 19, 4)->default(0);
            $table->uuid('submission_reference');
            $table->string('payload_hash', 64);
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['workspace_id', 'submission_reference'], 'commerce_payments_workspace_submission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_order_payments');
    }
};
