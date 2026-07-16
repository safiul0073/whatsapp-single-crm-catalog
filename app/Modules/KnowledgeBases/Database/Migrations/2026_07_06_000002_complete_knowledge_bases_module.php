<?php

use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_bases', function (Blueprint $table): void {
            $table->string('status')->default('ready')->index()->after('description');
            $table->string('visibility')->default('workspace')->after('status');
            $table->unsignedInteger('sources_count')->default(0)->after('settings');
            $table->unsignedInteger('chunks_count')->default(0)->after('sources_count');
            $table->timestamp('last_indexed_at')->nullable()->after('chunks_count');
        });

        Schema::create('knowledge_base_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(KnowledgeBase::class)->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('title');
            $table->text('url')->nullable();
            $table->string('file_path')->nullable();
            $table->text('content')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('token_count')->default(0);
            $table->unsignedInteger('chunks_count')->default(0);
            $table->string('checksum')->nullable()->index();
            $table->string('vector_status')->default('pending')->index();
            $table->text('vector_error')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_indexed_at')->nullable();
            $table->timestamps();
            $table->index(['knowledge_base_id', 'status']);
        });

        Schema::create('knowledge_base_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(KnowledgeBase::class)->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('knowledge_base_sources')->cascadeOnDelete();
            $table->longText('content');
            $table->json('embedding')->nullable();
            $table->string('vector_id')->nullable()->index();
            $table->unsignedInteger('token_count')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->float('score')->nullable();
            $table->timestamps();
            $table->fullText('content');
            $table->index(['knowledge_base_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_chunks');
        Schema::dropIfExists('knowledge_base_sources');

        Schema::table('knowledge_bases', function (Blueprint $table): void {
            $table->dropColumn([
                'status',
                'visibility',
                'sources_count',
                'chunks_count',
                'last_indexed_at',
            ]);
        });
    }
};
