<?php

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('type')->default('dynamic');
            $table->json('rules')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
