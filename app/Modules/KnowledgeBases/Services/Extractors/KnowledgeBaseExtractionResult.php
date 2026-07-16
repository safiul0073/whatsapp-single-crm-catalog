<?php

namespace App\Modules\KnowledgeBases\Services\Extractors;

class KnowledgeBaseExtractionResult
{
    public function __construct(
        public string $text,
        public array $metadata = [],
    ) {}
}
