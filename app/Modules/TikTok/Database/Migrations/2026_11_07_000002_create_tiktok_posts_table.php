<?php

use App\Models\User;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiktok_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->constrained()->cascadeOnDelete();
            $table->string('mode', 10);
            $table->text('title')->nullable();
            $table->string('privacy_level', 40)->nullable();
            $table->boolean('allow_comment')->default(false);
            $table->boolean('allow_duet')->default(false);
            $table->boolean('allow_stitch')->default(false);
            $table->boolean('brand_organic')->default(false);
            $table->boolean('brand_content')->default(false);
            $table->boolean('is_aigc')->default(false);
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('video_path')->nullable();
            $table->string('video_mime', 40)->nullable();
            $table->unsignedBigInteger('video_size')->default(0);
            $table->string('publish_id', 64)->nullable()->index();
            $table->string('status', 20)->default('queued')->index();
            $table->text('fail_reason')->nullable();
            $table->string('tiktok_post_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_posts');
    }
};
