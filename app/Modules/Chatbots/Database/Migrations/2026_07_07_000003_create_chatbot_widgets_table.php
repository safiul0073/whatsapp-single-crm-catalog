<?php

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Chatbot::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('public_token', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->json('allowed_domains')->nullable();
            $table->json('lead_fields')->nullable();
            $table->json('settings')->nullable();
            $table->string('greeting')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'is_active']);
        });

        Schema::create('chatbot_widget_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('widget_id')->constrained('chatbot_widgets')->cascadeOnDelete();
            $table->foreignIdFor(Chatbot::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Conversation::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Contact::class)->nullable()->constrained()->nullOnDelete();
            $table->string('session_token', 100)->unique();
            $table->string('visitor_uid', 100)->nullable()->index();
            $table->json('visitor_metadata')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['widget_id', 'visitor_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_widget_sessions');
        Schema::dropIfExists('chatbot_widgets');
    }
};
