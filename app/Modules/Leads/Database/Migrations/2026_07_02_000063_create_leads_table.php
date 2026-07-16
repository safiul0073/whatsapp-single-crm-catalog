<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('place')->nullable();
            $table->string('category')->nullable();
            $table->string('stage')->default('new');
            $table->string('source')->nullable();
            $table->string('external_source')->nullable();
            $table->string('external_id')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('contact_status')->default('draft');
            $table->string('verification_status')->default('unverified');
            $table->text('ai_prompt')->nullable();
            $table->json('criteria')->nullable();
            $table->decimal('value', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'external_source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
