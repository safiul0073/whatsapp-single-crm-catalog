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
        if (! Schema::hasTable('commerce_customer_auth_settings')) {
            Schema::create('commerce_customer_auth_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
                $table->boolean('enabled')->default(false);
                $table->unsignedBigInteger('channel_id')->nullable();
                $table->unsignedBigInteger('authentication_template_id')->nullable();
                $table->unsignedBigInteger('welcome_template_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('commerce_whatsapp_auth_challenges')) {
            Schema::create('commerce_whatsapp_auth_challenges', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->string('phone', 20);
                $table->string('session_hash', 64);
                $table->string('code_hash', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('send_status')->default('pending');
                $table->string('provider_message_id')->nullable()->index();
                $table->timestamp('expires_at');
                $table->timestamp('invalidated_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->string('grant_hash', 64)->nullable();
                $table->timestamp('grant_expires_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamp('consented_at');
                $table->timestamps();
                $table->index(['workspace_id', 'phone', 'created_at'], 'commerce_auth_phone_sends');
            });
        }
        if (! Schema::hasTable('commerce_whatsapp_customer_registrations')) {
            Schema::create('commerce_whatsapp_customer_registrations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->uuid('challenge_id')->unique();
                $table->string('customer_reference', 100);
                $table->string('phone', 20);
                $table->unsignedBigInteger('contact_id');
                $table->string('payload_hash', 64);
                $table->timestamp('consented_at');
                $table->string('welcome_status')->default('pending');
                $table->unsignedTinyInteger('welcome_attempts')->default(0);
                $table->string('provider_message_id')->nullable()->index('commerce_customer_auth_message');
                $table->string('delivery_status')->nullable();
                $table->timestamp('welcome_sent_at')->nullable();
                $table->timestamps();
                $table->unique(['workspace_id', 'customer_reference'], 'commerce_auth_customer');
                $table->unique(['workspace_id', 'phone'], 'commerce_auth_customer_phone');
            });
        }
        foreach (['commerce_customer_auth_message' => ['provider_message_id'], 'commerce_auth_customer' => ['workspace_id', 'customer_reference'], 'commerce_auth_customer_phone' => ['workspace_id', 'phone']] as $name => $columns) {
            if (! Schema::hasIndex('commerce_whatsapp_customer_registrations', $name)) {
                Schema::table('commerce_whatsapp_customer_registrations', function (Blueprint $table) use ($name, $columns): void {
                    if ($name === 'commerce_customer_auth_message') {
                        $table->index($columns, $name);
                    } else {
                        $table->unique($columns, $name);
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commerce_whatsapp_customer_registrations');
        Schema::dropIfExists('commerce_whatsapp_auth_challenges');
        Schema::dropIfExists('commerce_customer_auth_settings');
    }
};
