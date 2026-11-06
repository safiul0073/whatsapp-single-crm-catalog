<?php

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_page_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('page_id');
            $table->string('instagram_account_id')->nullable();
            $table->json('targets');
            $table->text('message');
            $table->string('link', 2048)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->string('status', 20)->default('scheduled')->index();
            $table->timestamp('published_at')->nullable();
            $table->string('facebook_post_id')->nullable();
            $table->string('instagram_media_id')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_page_posts');
    }
};
