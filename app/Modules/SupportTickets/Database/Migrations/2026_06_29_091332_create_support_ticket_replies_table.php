<?php

use App\Models\User;
use App\Modules\SupportTickets\Models\SupportTicket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SupportTicket::class, 'ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_staff')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
    }
};
