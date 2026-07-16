<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_provider_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->index();
            $table->string('provider_contact_id')->index();
            $table->string('address')->nullable();
            $table->string('username')->nullable();
            $table->string('status')->default('active');
            $table->string('identity_type')->default('phone');
            $table->json('metadata')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'provider', 'provider_contact_id'], 'contact_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_provider_identities');
    }
};
