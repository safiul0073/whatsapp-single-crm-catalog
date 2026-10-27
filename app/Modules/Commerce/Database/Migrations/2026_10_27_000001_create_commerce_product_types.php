<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_product_types')) {
            Schema::create('commerce_product_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('slug', 140);
                $table->string('layout', 30)->default('generic');
                $table->json('variant_axes')->nullable();
                $table->json('attributes')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->unique(['workspace_id', 'slug']);
            });
        }

        if (Schema::hasTable('commerce_categories') && ! Schema::hasColumn('commerce_categories', 'product_type_id')) {
            Schema::table('commerce_categories', function (Blueprint $table) {
                $table->foreignId('product_type_id')->nullable()->after('parent_id')->constrained('commerce_product_types')->nullOnDelete();
            });
        }

        if (Schema::hasTable('commerce_products') && ! Schema::hasColumn('commerce_products', 'attribute_values')) {
            Schema::table('commerce_products', function (Blueprint $table) {
                $table->json('attribute_values')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('commerce_products') && Schema::hasColumn('commerce_products', 'attribute_values')) {
            Schema::table('commerce_products', function (Blueprint $table) {
                $table->dropColumn('attribute_values');
            });
        }

        if (Schema::hasTable('commerce_categories') && Schema::hasColumn('commerce_categories', 'product_type_id')) {
            Schema::table('commerce_categories', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_type_id');
            });
        }

        Schema::dropIfExists('commerce_product_types');
    }
};
