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
            $table->string('interval')->default('month');
            $table->json('limits')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Plan::class)->nullable()->constrained()->nullOnDelete();
            $table->enum('status', SubscriptionStatus::values())->default(SubscriptionStatus::Trialing->value);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('usage')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
