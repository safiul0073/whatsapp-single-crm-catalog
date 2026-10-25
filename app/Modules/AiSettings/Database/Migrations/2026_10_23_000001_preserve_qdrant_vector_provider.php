<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The default vector provider changed from Qdrant to the JSON file store.
 * Installs that already enabled vector search without explicitly saving a
 * provider were relying on the old Qdrant default, so pin it for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('ai_settings')
            ->whereIn('key', ['vector_database_enabled', 'vector_database_provider'])
            ->pluck('value', 'key');

        if ($settings->has('vector_database_provider') || ! $settings->has('vector_database_enabled')) {
            return;
        }

        DB::table('ai_settings')->insert([
            'key' => 'vector_database_provider',
            'value' => 'qdrant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget('ai_settings_cache');
    }

    public function down(): void {}
};
