<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_contact_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->index();
            $table->foreignId('contact_id')->index();
            $table->string('label', 60)->nullable();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('line1', 255);
            $table->string('line2', 255)->nullable();
            $table->string('city', 120);
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->string('country', 2)->default('US');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_contact_addresses');
    }
};
