<?php

use App\Models\User;
use App\Modules\Inbox\Models\Conversation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_conversation_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Conversation::class)->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversation_reads');
    }
};
