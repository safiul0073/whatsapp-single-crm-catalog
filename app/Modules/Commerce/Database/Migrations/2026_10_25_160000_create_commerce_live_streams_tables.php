<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_live_streams', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('host_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title', 200);
            $t->string('slug', 220);
            $t->text('description')->nullable();
            $t->string('status', 32)->default('scheduled');
            $t->json('broadcast_platforms')->nullable();
            $t->string('stream_key', 64)->unique();
            $t->string('facebook_live_id', 120)->nullable();
            $t->text('facebook_stream_url')->nullable();
            $t->string('facebook_permalink_url', 500)->nullable();
            $t->string('facebook_status', 32)->default('not_selected');
            $t->text('facebook_error')->nullable();
            $t->string('thumbnail_path')->nullable();
            $t->string('video_path')->nullable();
            $t->unsignedBigInteger('video_size_bytes')->default(0);
            $t->unsignedInteger('duration_seconds')->default(0);
            $t->foreignId('pinned_product_id')->nullable()->constrained('commerce_products')->nullOnDelete();
            $t->boolean('is_in_slider')->default(false);
            $t->foreignId('slider_id')->nullable()->constrained('commerce_storefront_sliders')->nullOnDelete();
            $t->boolean('chat_enabled')->default(true);
            $t->unsignedInteger('viewer_count')->default(0);
            $t->unsignedInteger('facebook_viewer_count')->default(0);
            $t->unsignedInteger('peak_viewers')->default(0);
            $t->unsignedInteger('total_views')->default(0);
            $t->unsignedInteger('likes_count')->default(0);
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();

            $t->unique(['workspace_id', 'slug'], 'commerce_live_streams_workspace_slug_unique');
            $t->index(['workspace_id', 'status']);
        });

        Schema::create('commerce_live_stream_products', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('live_stream_id')->constrained('commerce_live_streams')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('commerce_products')->cascadeOnDelete();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_pinned')->default(false);
            $t->timestamp('pinned_at')->nullable();
            $t->timestamps();

            $t->unique(['live_stream_id', 'product_id'], 'commerce_live_stream_products_unique');
        });

        Schema::create('commerce_live_stream_comments', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('live_stream_id')->constrained('commerce_live_streams')->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('commerce_customers')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('external_comment_id', 160)->nullable();
            $t->string('author_name', 120);
            $t->string('author_role', 32)->default('customer');
            $t->string('body', 500);
            $t->boolean('is_pinned')->default(false);
            $t->boolean('is_hidden')->default(false);
            $t->unsignedInteger('offset_seconds')->default(0);
            $t->timestamps();

            $t->index(['live_stream_id', 'is_hidden']);
            $t->unique(['live_stream_id', 'external_comment_id'], 'commerce_live_comments_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_live_stream_comments');
        Schema::dropIfExists('commerce_live_stream_products');
        Schema::dropIfExists('commerce_live_streams');
    }
};
