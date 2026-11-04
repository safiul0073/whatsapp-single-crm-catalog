<?php

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_business_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->unique()->constrained()->cascadeOnDelete();
            $table->string('business_id')->nullable();
            $table->string('business_name')->nullable();
            $table->string('status')->default('connected')->index();
            $table->text('credentials')->nullable();
            $table->json('scopes')->nullable();
            $table->json('assets')->nullable();
            $table->json('selected')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_business_connections');
    }
};
