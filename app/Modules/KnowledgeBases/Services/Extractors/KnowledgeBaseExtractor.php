<?php

namespace App\Modules\KnowledgeBases\Services\Extractors;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;

interface KnowledgeBaseExtractor
{
    public function extract(KnowledgeBaseSource $source): KnowledgeBaseExtractionResult;
}
