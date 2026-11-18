<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_carrier_accounts', function (Blueprint $table) {
            $table->string('origin_name', 120)->nullable()->after('environment');
            $table->string('origin_address_line1')->nullable()->after('origin_name');
            $table->string('origin_city', 80)->nullable()->after('origin_address_line1');
            $table->string('origin_state', 80)->nullable()->after('origin_city');
            $table->string('origin_postal_code', 20)->nullable()->after('origin_state');
            $table->string('origin_country_code', 2)->nullable()->after('origin_postal_code');
            $table->decimal('exchange_rate', 20, 8)->default(1)->after('origin_country_code');
            $table->json('options')->nullable()->after('exchange_rate');
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_error_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_carrier_accounts', function (Blueprint $table) {
            $table->dropColumn(['origin_name', 'origin_address_line1', 'origin_city', 'origin_state', 'origin_postal_code', 'origin_country_code', 'exchange_rate', 'options', 'last_success_at', 'last_error', 'last_error_at']);
        });
    }
};
