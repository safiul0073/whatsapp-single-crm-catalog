<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('author_name');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('featured_image')->nullable();
            $table->unsignedBigInteger('featured_image_media_id')->nullable();
            $table->unsignedSmallInteger('read_time_minutes')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->string('status')->default('draft');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['active', 'status', 'published_at']);
            $table->index('blog_category_id');
            $table->index('featured_image_media_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
