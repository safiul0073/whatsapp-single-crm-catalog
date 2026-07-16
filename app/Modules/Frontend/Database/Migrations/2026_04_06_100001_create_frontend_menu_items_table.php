<?php

use App\Modules\Frontend\Models\FrontendMenu;
use App\Modules\Frontend\Models\FrontendMenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(FrontendMenu::class)->constrained('frontend_menus')->cascadeOnDelete();
            $table->foreignIdFor(FrontendMenuItem::class, 'parent_id')->nullable()->constrained('frontend_menu_items')->nullOnDelete();
            $table->string('item_type');
            $table->string('label');
            $table->string('linkable_type')->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();
            $table->string('url', 2000)->nullable();
            $table->string('target')->default('_self');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_menu_items');
    }
};
