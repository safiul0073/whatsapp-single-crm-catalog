<?php

use App\Modules\MarketingChannels\Enums\ChannelAccountStatus;
use App\Modules\MarketingChannels\Enums\ChannelWebhookEventStatus;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('name');
            $table->enum('status', ChannelAccountStatus::values())->default(ChannelAccountStatus::Draft->value)->index();
            $table->text('credentials')->nullable();
            $table->string('webhook_verify_token')->nullable();
            $table->string('webhook_code')->unique();
            $table->string('provider_account_id')->nullable()->index();
            $table->string('provider_phone_id')->nullable()->index();
            $table->string('provider_display_id')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'provider', 'status']);
        });

        Schema::create('channel_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(ChannelAccount::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Workspace::class)->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->index();
            $table->string('event_type')->default('unknown')->index();
            $table->string('provider_event_id')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->enum('status', ChannelWebhookEventStatus::values())->default(ChannelWebhookEventStatus::Pending->value)->index();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_webhook_events');
        Schema::dropIfExists('channel_accounts');
    }
};
