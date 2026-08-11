<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commerce_catalogs', function (Blueprint $table): void {
            $table->string('currency', 3)->default('USD')->after('sync_mode');
        });

        Schema::table('message_templates', function (Blueprint $table): void {
            $table->string('template_kind', 30)->default('standard')->after('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table): void {
            $table->dropColumn('template_kind');
        });

        Schema::table('commerce_catalogs', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
