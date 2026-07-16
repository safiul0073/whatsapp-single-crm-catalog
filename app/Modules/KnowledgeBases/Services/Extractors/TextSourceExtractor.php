<?php

namespace App\Modules\KnowledgeBases\Services\Extractors;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;

class TextSourceExtractor implements KnowledgeBaseExtractor
{
    public function extract(KnowledgeBaseSource $source): KnowledgeBaseExtractionResult
    {
        return new KnowledgeBaseExtractionResult((string) $source->content);
    }
}
