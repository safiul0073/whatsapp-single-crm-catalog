<?php

use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commerce_brands')) {
            Schema::create('commerce_brands', function (Blueprint $table): void {
                $table->id();
                $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['workspace_id', 'slug']);
            });
        }

        if (! Schema::hasTable('commerce_audiences')) {
            Schema::create('commerce_audiences', function (Blueprint $table): void {
                $table->id();
                $table->foreignIdFor(Workspace::class)->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['workspace_id', 'slug']);
            });
        }

        if (! Schema::hasColumn('commerce_products', 'brand_id')) {
            Schema::table('commerce_products', function (Blueprint $table): void {
                $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('commerce_brands')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('commerce_products', 'audience_id')) {
            Schema::table('commerce_products', function (Blueprint $table): void {
                $table->foreignId('audience_id')->nullable()->after('brand_id')->constrained('commerce_audiences')->nullOnDelete();
            });
        }

        $this->backfill('brand', 'commerce_brands', 'brand_id');
        $this->backfill('audience', 'commerce_audiences', 'audience_id');
    }

    public function down(): void
    {
        if (Schema::hasColumn('commerce_products', 'audience_id')) {
            Schema::table('commerce_products', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('audience_id');
            });
        }
        if (Schema::hasColumn('commerce_products', 'brand_id')) {
            Schema::table('commerce_products', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('brand_id');
            });
        }

        Schema::dropIfExists('commerce_audiences');
        Schema::dropIfExists('commerce_brands');
    }

    protected function backfill(string $sourceColumn, string $table, string $foreignKey): void
    {
        DB::table('commerce_products')
            ->select(['workspace_id', $sourceColumn])
            ->whereNotNull($sourceColumn)
            ->where($sourceColumn, '!=', '')
            ->distinct()
            ->get()
            ->each(function (object $value) use ($sourceColumn, $table, $foreignKey): void {
                $name = (string) $value->{$sourceColumn};
                $record = DB::table($table)->where('workspace_id', $value->workspace_id)->where('name', $name)->first();
                $id = $record?->id ?? DB::table($table)->insertGetId([
                    'workspace_id' => $value->workspace_id,
                    'name' => $name,
                    'slug' => $this->uniqueSlug($table, (int) $value->workspace_id, $name),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('commerce_products')
                    ->where('workspace_id', $value->workspace_id)
                    ->where($sourceColumn, $name)
                    ->update([$foreignKey => $id]);
            });
    }

    protected function uniqueSlug(string $table, int $workspaceId, string $name): string
    {
        $base = Str::slug($name) ?: Str::random(12);
        $slug = $base;
        $suffix = 2;
        while (DB::table($table)->where('workspace_id', $workspaceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
};
