<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commerce_customers')) {
            Schema::table('commerce_customers', function (Blueprint $table): void {
                if (! Schema::hasColumn('commerce_customers', 'source')) {
                    $table->string('source', 30)->default('storefront')->after('registered_at')->index();
                }
                if (! Schema::hasColumn('commerce_customers', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable()->after('source');
                }
                if (! Schema::hasColumn('commerce_customers', 'last_login_channel')) {
                    $table->string('last_login_channel', 30)->nullable()->after('last_login_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commerce_customers')) {
            Schema::table('commerce_customers', function (Blueprint $table): void {
                $columns = array_filter(['source', 'last_login_at', 'last_login_channel'], fn ($col) => Schema::hasColumn('commerce_customers', $col));
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
