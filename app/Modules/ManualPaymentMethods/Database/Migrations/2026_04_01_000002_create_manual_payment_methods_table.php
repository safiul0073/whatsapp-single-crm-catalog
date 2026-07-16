<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 50)->unique();
            $table->string('icon')->default('ph ph-hand-coins');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_methods');
    }
};
