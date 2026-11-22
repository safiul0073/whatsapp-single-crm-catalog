<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner app tokens belong to one store's app, which sends through that store's Firebase project.
     */
    public function up(): void
    {
        if (Schema::hasColumn('device_tokens', 'workspace_id')) {
            return;
        }
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('tokenable_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropColumn('workspace_id');
        });
    }
};
