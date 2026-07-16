<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignIdFor(Admin::class)->nullable()->after('user_id')->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_ticket_replies', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
            $table->dropColumn('admin_id');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
