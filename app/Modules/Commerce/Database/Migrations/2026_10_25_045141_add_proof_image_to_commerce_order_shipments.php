<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commerce_order_shipments', function (Blueprint $table): void {
            $table->string('proof_image')->nullable()->after('tracking_url');
        });
    }

    public function down(): void
    {
        Schema::table('commerce_order_shipments', function (Blueprint $table): void {
            $table->dropColumn('proof_image');
        });
    }
};
