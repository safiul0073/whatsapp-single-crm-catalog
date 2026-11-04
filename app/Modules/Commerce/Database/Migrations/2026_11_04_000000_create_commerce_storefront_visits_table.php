<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_storefront_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->index();
            $table->uuid('visitor_key');
            $table->foreignId('customer_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('country_name', 100)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('device', 20)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
            $table->unique(['workspace_id', 'visitor_key']);
        });

        Schema::create('commerce_storefront_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id');
            $table->foreignId('visitor_id')->index();
            $table->foreignId('customer_id')->nullable();
            $table->string('path', 500);
            $table->string('referrer', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->timestamp('viewed_at');
            $table->index(['workspace_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_storefront_page_views');
        Schema::dropIfExists('commerce_storefront_visitors');
    }
};
