<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_orders')) {
            return;
        }

        Schema::table('commerce_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('commerce_orders', 'invoice_number')) {
                $table->string('invoice_number', 60)->nullable();
            }
            if (! Schema::hasColumn('commerce_orders', 'invoice_token')) {
                $table->string('invoice_token', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('commerce_orders', 'invoice_sent_at')) {
                $table->timestamp('invoice_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('commerce_orders')) {
            return;
        }

        Schema::table('commerce_orders', function (Blueprint $table): void {
            foreach (['invoice_number', 'invoice_sent_at'] as $column) {
                if (Schema::hasColumn('commerce_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('commerce_orders', 'invoice_token')) {
                $table->dropUnique(['invoice_token']);
                $table->dropColumn('invoice_token');
            }
        });
    }
};
