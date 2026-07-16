<?php

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Enums\ConversationStatus;
use App\Modules\Inbox\Enums\MessageStatus;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('whatsapp')->index();
            $table->string('provider_conversation_id')->nullable()->index();
            $table->foreignIdFor(Contact::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class, 'assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ConversationStatus::values())->default(ConversationStatus::Open->value);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->json('labels')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('whatsapp')->index();
            $table->foreignIdFor(Conversation::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Contact::class)->nullable()->constrained()->nullOnDelete();
            $table->string('direction');
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', MessageStatus::values())->default(MessageStatus::Received->value);
            $table->string('provider_message_id')->nullable()->index();
            $table->foreignIdFor(Campaign::class)->nullable()->constrained()->nullOnDelete();
            $table->string('whatsapp_message_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
