<?php

use App\Modules\Automations\Models\Automation;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Automation::class)->constrained()->cascadeOnDelete();
            $table->string('status')->default('running')->index();
            $table->string('trigger_type')->nullable()->index();
            $table->string('trigger_node_id')->nullable();
            $table->string('event_key')->nullable()->index();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('campaign_recipient_id')->nullable()->constrained('campaign_recipients')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['automation_id', 'trigger_node_id', 'event_key'], 'automation_runs_event_unique');
        });

        Schema::create('automation_step_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_run_id')->constrained('automation_runs')->cascadeOnDelete();
            $table->foreignIdFor(Automation::class)->constrained()->cascadeOnDelete();
            $table->string('node_id')->index();
            $table->string('node_type')->nullable();
            $table->string('node_kind')->nullable();
            $table->string('status')->default('running')->index();
            $table->string('selected_port')->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('scheduled_until')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_step_logs');
        Schema::dropIfExists('automation_runs');
    }
};
