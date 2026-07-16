<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email');
            $table->string('company', 160);
            $table->string('interest', 80);
            $table->text('message');
            $table->string('status', 32)->default('new');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
