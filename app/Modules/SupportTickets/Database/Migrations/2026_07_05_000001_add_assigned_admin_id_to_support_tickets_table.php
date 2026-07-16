<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('assigned_admin_id')->nullable()->after('user_id')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['assigned_admin_id']);
            $table->dropColumn('assigned_admin_id');
        });
    }
};
