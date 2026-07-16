<?php

use App\Modules\Chatbots\Models\Chatbot;
use App\Modules\KnowledgeBases\Models\KnowledgeBase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatbots', function (Blueprint $table): void {
            $table->string('greeting')->nullable()->after('persona');
            $table->decimal('temperature', 3, 2)->default(0.40)->after('greeting');
            $table->unsignedSmallInteger('max_tokens')->default(512)->after('temperature');
            $table->boolean('fallback_only_knowledge_base')->default(true)->after('max_tokens');
        });

        Schema::create('chatbot_knowledge_base', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Chatbot::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(KnowledgeBase::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['chatbot_id', 'knowledge_base_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_knowledge_base');

        Schema::table('chatbots', function (Blueprint $table): void {
            $table->dropColumn([
                'greeting',
                'temperature',
                'max_tokens',
                'fallback_only_knowledge_base',
            ]);
        });
    }
};
