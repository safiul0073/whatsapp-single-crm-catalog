<?php

use App\Models\User;
use App\Modules\Contacts\Enums\ContactOptInStatus;
use App\Modules\Contacts\Enums\ContactSource;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->json('custom_fields')->nullable();
            $table->enum('source', ContactSource::values())->nullable();
            $table->enum('opt_in_status', ContactOptInStatus::values())->default(ContactOptInStatus::Unknown->value);
            $table->timestamp('opt_in_at')->nullable();
            $table->timestamp('opt_out_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'phone']);
            $table->unique(['workspace_id', 'email']);
            $table->index(['workspace_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
