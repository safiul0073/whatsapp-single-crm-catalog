<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral commerce: referrer-reviewed order refund requests with an append-only history,
 * and an owner-controlled withdrawal schedule for the existing reward wallet cash-ins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_referral_settings', function (Blueprint $t): void {
            $t->boolean('refunds_enabled')->default(false);
            $t->string('withdrawal_frequency', 10)->default('monthly');
            $t->unsignedTinyInteger('withdrawal_weekly_day')->default(5);
            $t->unsignedTinyInteger('withdrawal_monthly_day')->default(1);
            $t->string('withdrawal_opens_at', 5)->default('09:00');
            $t->string('withdrawal_closes_at', 5)->default('18:00');
            $t->decimal('max_cashin_amount', 14, 4)->nullable();
        });

        Schema::table('commerce_cashin_requests', function (Blueprint $t): void {
            $t->uuid('submission_reference')->nullable();
            $t->timestamp('window_starts_at')->nullable();
            $t->timestamp('window_ends_at')->nullable();
            $t->string('payout_reference', 120)->nullable();
            $t->unique(['workspace_id', 'submission_reference'], 'commerce_cashin_submission_unique');
            $t->index(['workspace_id', 'customer_id'], 'commerce_cashin_customer_index');
        });

        Schema::table('commerce_orders', function (Blueprint $t): void {
            $t->decimal('refunded_amount', 19, 4)->default(0);
            $t->timestamp('refunded_at')->nullable();
        });

        Schema::create('commerce_refund_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->foreignId('referrer_customer_id')->nullable()->constrained('commerce_customers')->nullOnDelete();
            $t->string('reference', 40);
            $t->uuid('submission_reference');
            $t->bigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('reason', 60);
            $t->text('details')->nullable();
            $t->string('status', 30)->default('pending_referrer');
            $t->string('referrer_decision', 20)->nullable();
            $t->text('referrer_comment')->nullable();
            $t->timestamp('referrer_decided_at')->nullable();
            $t->text('admin_note')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('payout_method', 20)->nullable();
            $t->string('payout_channel', 60)->nullable();
            $t->string('payout_reference', 120)->nullable();
            $t->bigInteger('commission_reversed_minor')->default(0);
            $t->bigInteger('commission_shortfall_minor')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'reference'], 'commerce_refund_reference_unique');
            $t->unique(['workspace_id', 'submission_reference'], 'commerce_refund_submission_unique');
            $t->index(['workspace_id', 'status'], 'commerce_refund_status_index');
            $t->index(['workspace_id', 'customer_id'], 'commerce_refund_customer_index');
            $t->index(['workspace_id', 'referrer_customer_id'], 'commerce_refund_referrer_index');
        });

        Schema::create('commerce_refund_request_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('refund_request_id')->constrained('commerce_refund_requests')->cascadeOnDelete();
            $t->string('actor_type', 20);
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('from_status', 30)->nullable();
            $t->string('to_status', 30);
            $t->text('comment')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['refund_request_id', 'id'], 'commerce_refund_events_request_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_refund_request_events');
        Schema::dropIfExists('commerce_refund_requests');
        Schema::table('commerce_orders', function (Blueprint $t): void {
            $t->dropColumn(['refunded_amount', 'refunded_at']);
        });
        Schema::table('commerce_cashin_requests', function (Blueprint $t): void {
            $t->dropUnique('commerce_cashin_submission_unique');
            $t->dropIndex('commerce_cashin_customer_index');
            $t->dropColumn(['submission_reference', 'window_starts_at', 'window_ends_at', 'payout_reference']);
        });
        Schema::table('commerce_referral_settings', function (Blueprint $t): void {
            $t->dropColumn(['refunds_enabled', 'withdrawal_frequency', 'withdrawal_weekly_day', 'withdrawal_monthly_day', 'withdrawal_opens_at', 'withdrawal_closes_at', 'max_cashin_amount']);
        });
    }
};
