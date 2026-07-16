<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\ContactGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_group_contact', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(ContactGroup::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['contact_group_id', 'contact_id'], 'contact_group_contact_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_group_contact');
    }
};
