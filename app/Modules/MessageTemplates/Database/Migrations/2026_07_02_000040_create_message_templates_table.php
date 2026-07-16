<?php

use App\Modules\MessageTemplates\Enums\MessageTemplateStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('provider')->default('whatsapp')->index();
            $table->string('name');
            $table->string('language', 16)->default('en_US');
            $table->string('category')->default('marketing');
            $table->enum('status', MessageTemplateStatus::values())->default(MessageTemplateStatus::Draft->value);
            $table->json('components')->nullable();
            $table->json('submission_payload')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'provider', 'name', 'language'], 'message_templates_workspace_provider_name_language_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
