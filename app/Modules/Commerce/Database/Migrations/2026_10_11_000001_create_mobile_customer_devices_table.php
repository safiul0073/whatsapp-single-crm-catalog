<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_customer_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->index();
            $table->string('customer_reference', 150);
            $table->text('token');
            $table->string('platform', 20);
            $table->timestamps();
            $table->index(['workspace_id', 'customer_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_customer_devices');
    }
};
