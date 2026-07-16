<?php

use App\Models\User;
use App\Modules\Contacts\Enums\ContactImportStatus;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('source')->default('import');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('column_mapping')->nullable();
            $table->json('options')->nullable();
            $table->json('errors')->nullable();
            $table->json('summary')->nullable();
            $table->enum('status', ContactImportStatus::values())->default(ContactImportStatus::Pending->value);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_imports');
    }
};
