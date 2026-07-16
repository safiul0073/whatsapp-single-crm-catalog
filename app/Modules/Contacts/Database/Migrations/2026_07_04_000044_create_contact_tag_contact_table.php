<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactTag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_tag_contact', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(ContactTag::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['contact_tag_id', 'contact_id'], 'contact_tag_contact_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_tag_contact');
    }
};
