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
        Schema::create('messenger_marketing_optins', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(ChannelAccount::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Contact::class)->constrained()->cascadeOnDelete();
            $table->string('psid');
            $table->string('topic', 100)->default('offers');
            $table->text('token')->nullable();
            $table->string('frequency', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_account_id', 'psid', 'topic'], 'messenger_optins_account_psid_topic_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_marketing_optins');
    }
};
