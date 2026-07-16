<?php

use App\Models\User;
use App\Modules\Campaigns\Models\Campaign;
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
        Schema::create('crm_pipelines', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
            $table->index(['workspace_id', 'is_default']);
        });

        Schema::create('crm_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->string('color', 7)->nullable();
            $table->timestamps();
            $table->unique(['pipeline_id', 'name']);
            $table->index(['workspace_id', 'pipeline_id', 'position']);
        });

        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Conversation::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Campaign::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('crm_stages')->restrictOnDelete();
            $table->string('title');
            $table->decimal('value', 12, 2)->nullable();
            $table->string('source')->default('manual');
            $table->string('status')->default('open');
            $table->foreignIdFor(User::class, 'assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->text('lost_reason')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'pipeline_id', 'stage_id', 'status'], 'crm_leads_board_index');
            $table->index(['workspace_id', 'contact_id', 'pipeline_id', 'status'], 'crm_leads_contact_index');
            $table->index(['workspace_id', 'assigned_to', 'status']);
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(Conversation::class)->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'lead_id', 'created_at']);
            $table->index(['workspace_id', 'contact_id', 'created_at']);
        });

        Schema::create('crm_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class, 'assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('priority')->default('normal');
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status', 'due_at']);
            $table->index(['workspace_id', 'assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
