<?php

use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('whatsapp')->index();
            $table->string('type')->nullable();
            $table->foreignIdFor(MessageTemplate::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('automation_id')->nullable()->index();
            $table->string('audience_type')->nullable();
            $table->json('audience_ids')->nullable();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
            $table->string('name');
            $table->enum('status', CampaignStatus::values())->default(CampaignStatus::Draft->value);
            $table->string('message_type')->default('custom');
            $table->json('audience')->nullable();
            $table->string('message_subject')->nullable();
            $table->text('message_body')->nullable();
            $table->json('variables')->nullable();
            $table->json('settings')->nullable();
            $table->json('message_payload')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('send_rate_per_minute')->default(60);
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('sending_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('replied_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('skipped_opt_out_count')->default(0);
            $table->unsignedInteger('skipped_invalid_count')->default(0);
            $table->unsignedInteger('skipped_policy_count')->default(0);
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Campaign::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->constrained()->cascadeOnDelete();
            $table->foreignId('contact_channel_id')->nullable()->constrained('contact_provider_identities')->nullOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('whatsapp')->index();
            $table->string('to')->nullable();
            $table->string('recipient_address')->nullable();
            $table->string('status')->default(CampaignRecipientStatus::Queued->value)->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
