<?php

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ad_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('message_id')->unique();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('channel_account_id')->nullable();
            $table->string('provider');
            $table->string('ad_id')->nullable()->index();
            $table->string('ctwa_clid')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('media_type')->nullable();
            $table->timestamp('referred_at')->index();
            $table->timestamps();
            $table->index(['workspace_id', 'referred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ad_referrals');
    }
};
