<?php

use App\Modules\Frontend\Models\FrontendSection;
use App\Modules\Frontend\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Page::class)->constrained('pages')->cascadeOnDelete();
            $table->foreignIdFor(FrontendSection::class)->constrained('frontend_sections')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('visibility_rules')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'frontend_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
