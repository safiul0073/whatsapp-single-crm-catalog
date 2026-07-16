<?php

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
            $table->unique(['workspace_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tags');
    }
};
