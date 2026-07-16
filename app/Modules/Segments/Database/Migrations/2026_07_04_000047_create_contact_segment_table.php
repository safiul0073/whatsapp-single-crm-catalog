<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\Segments\Models\Segment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_segment', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Segment::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['segment_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_segment');
    }
};
