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
        Schema::create('telegram_opt_in_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'contact_id', 'channel_account_id'], 'telegram_opt_in_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_opt_in_tokens');
    }
};
