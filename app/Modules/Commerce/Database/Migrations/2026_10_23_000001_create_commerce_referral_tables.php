<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront referral program: per-store settings, customer codes, referrals with per-order
 * reward transactions, an append-only reward wallet ledger (minor units), target slab bonuses,
 * per-customer reward overrides and cash-in requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_referral_settings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
            $t->boolean('enabled')->default(false);
            $t->string('reward_trigger', 30)->default('delivered_order');
            $t->string('reward_scope', 20)->default('first_order');
            $t->string('reward_type', 20)->default('fixed');
            $t->decimal('reward_value', 14, 4)->default(0);
            $t->decimal('reward_cap', 14, 4)->nullable();
            $t->decimal('min_order_amount', 14, 4)->default(0);
            $t->string('referee_discount_type', 20)->default('none');
            $t->decimal('referee_discount_value', 14, 4)->default(0);
            $t->decimal('referee_min_order', 14, 4)->default(0);
            $t->unsignedTinyInteger('max_redeem_percent')->default(100);
            $t->decimal('min_spend_balance', 14, 4)->default(0);
            $t->boolean('cashin_enabled')->default(false);
            $t->decimal('min_cashin_balance', 14, 4)->default(0);
            $t->json('cashin_methods')->nullable();
            $t->unsignedInteger('max_referrals_per_customer')->nullable();
            $t->text('terms')->nullable();
            $t->timestamps();
        });

        Schema::create('commerce_referral_codes', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->string('code', 32);
            $t->timestamps();
            $t->unique(['workspace_id', 'code']);
            $t->unique(['workspace_id', 'customer_id']);
        });

        Schema::create('commerce_referrals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('referrer_customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->foreignId('referee_customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->string('code', 32);
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->unique(['workspace_id', 'referee_customer_id']);
            $t->index(['workspace_id', 'referrer_customer_id']);
        });

        Schema::create('commerce_referral_transactions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('referral_id')->constrained('commerce_referrals')->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('commerce_orders')->nullOnDelete();
            $t->bigInteger('order_amount_minor')->default(0);
            $t->bigInteger('reward_minor')->default(0);
            $t->string('currency', 3);
            $t->string('status', 20)->default('pending');
            $t->timestamp('credited_at')->nullable();
            $t->timestamps();
            $t->unique(['referral_id', 'order_id']);
            $t->index(['workspace_id', 'status']);
        });

        Schema::create('commerce_reward_ledger', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->bigInteger('amount_minor');
            $t->bigInteger('balance_after_minor');
            $t->string('currency', 3);
            $t->string('type', 30);
            $t->string('idempotency_key', 120)->nullable();
            $t->nullableMorphs('source');
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['workspace_id', 'idempotency_key']);
            $t->index(['workspace_id', 'customer_id']);
        });

        Schema::create('commerce_referral_target_slabs', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->string('name');
            $t->string('period', 10);
            $t->unsignedInteger('min_referrals');
            $t->decimal('reward_amount', 14, 4);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('commerce_referral_customer_rules', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->string('reward_type', 20);
            $t->decimal('reward_value', 14, 4);
            $t->decimal('reward_cap', 14, 4)->nullable();
            $t->decimal('min_order_amount', 14, 4)->default(0);
            $t->string('notes')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['workspace_id', 'customer_id']);
        });

        Schema::create('commerce_cashin_requests', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('commerce_customers')->cascadeOnDelete();
            $t->bigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('method', 60);
            $t->text('account_details');
            $t->string('status', 20)->default('pending');
            $t->string('admin_note')->nullable();
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['commerce_cashin_requests', 'commerce_referral_customer_rules', 'commerce_referral_target_slabs', 'commerce_reward_ledger', 'commerce_referral_transactions', 'commerce_referrals', 'commerce_referral_codes', 'commerce_referral_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
