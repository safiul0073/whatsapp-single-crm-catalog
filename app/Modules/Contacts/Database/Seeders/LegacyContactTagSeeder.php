<?php

namespace App\Modules\Contacts\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacyContactTagSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasColumn('contacts', 'tags')) {
            return;
        }

        DB::table('contacts')
            ->select(['id', 'workspace_id', 'tags'])
            ->whereNotNull('tags')
            ->orderBy('id')
            ->chunkById(100, function ($contacts): void {
                foreach ($contacts as $contact) {
                    $tags = json_decode($contact->tags, true);

                    if (! is_array($tags)) {
                        continue;
                    }

                    foreach ($tags as $name) {
                        $this->attachTag($contact, trim((string) $name));
                    }
                }
            });
    }

    protected function attachTag(object $contact, string $name): void
    {
        if ($name === '') {
            return;
        }

        $slug = Str::slug($name);
        $tag = DB::table('contact_tags')
            ->where('workspace_id', $contact->workspace_id)
            ->where('slug', $slug)
            ->first();

        $tagId = $tag?->id ?? DB::table('contact_tags')->insertGetId([
            'workspace_id' => $contact->workspace_id,
            'name' => $name,
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contact_tag_contact')->updateOrInsert(
            ['contact_tag_id' => $tagId, 'contact_id' => $contact->id],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
}
