<?php

use App\Modules\PlansSubscriptions\Enums\SubscriptionStatus;
use App\Modules\PlansSubscriptions\Models\Plan;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('interval')->default('month');
            $table->json('limits')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_free')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->unique()->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Plan::class)->nullable()->constrained()->nullOnDelete();
            $table->enum('status', SubscriptionStatus::values())->default(SubscriptionStatus::Trialing->value);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->foreignId('pending_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('last_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('usage')->nullable();
            $table->timestamp('usage_period_start')->nullable();
            $table->timestamps();

            $table->index(['status', 'renews_at']);
        });

        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->foreignId('from_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('to_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->nullableMorphs('actor');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'type']);
            $table->unique(['payment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
