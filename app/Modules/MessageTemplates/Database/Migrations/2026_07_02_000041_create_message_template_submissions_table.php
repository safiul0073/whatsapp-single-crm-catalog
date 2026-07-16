<?php

use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_template_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(MessageTemplate::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('whatsapp')->index();
            $table->string('provider_account_id')->index();
            $table->string('whatsapp_template_id')->nullable();
            $table->enum('status', MessageTemplateStatus::values())->default(MessageTemplateStatus::Submitted->value)->index();
            $table->json('submission_payload')->nullable();
            $table->json('meta_response')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'message_template_id', 'provider_account_id'], 'template_submission_waba_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_template_submissions');
    }
};
