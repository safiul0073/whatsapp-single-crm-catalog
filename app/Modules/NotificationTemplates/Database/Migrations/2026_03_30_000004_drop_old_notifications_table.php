<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('notifications');
    }

    public function down(): void
    {
        // The old notifications table is no longer needed.
        // If you need to restore it, re-run the original migration.
    }
};
