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
        Schema::create('meta_ads', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ad_account_id');
            $table->string('page_id');
            $table->string('instagram_account_id')->nullable();
            $table->string('kind', 30);
            $table->string('name');
            $table->string('objective', 40);
            $table->string('destination', 20)->nullable();
            $table->unsignedBigInteger('daily_budget_minor');
            $table->string('currency', 10);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('targeting')->nullable();
            $table->json('source')->nullable();
            $table->string('meta_campaign_id')->nullable();
            $table->string('meta_adset_id')->nullable();
            $table->string('meta_creative_id')->nullable();
            $table->string('meta_ad_id')->nullable()->index();
            $table->string('status', 20)->default('creating')->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads');
    }
};
