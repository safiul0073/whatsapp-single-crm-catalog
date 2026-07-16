<?php

namespace App\Modules\KnowledgeBases\Services;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use App\Modules\KnowledgeBases\Services\Extractors\FileSourceExtractor;
use App\Modules\KnowledgeBases\Services\Extractors\KnowledgeBaseExtractionResult;
use App\Modules\KnowledgeBases\Services\Extractors\SitemapSourceExtractor;
use App\Modules\KnowledgeBases\Services\Extractors\TextSourceExtractor;
use App\Modules\KnowledgeBases\Services\Extractors\UrlSourceExtractor;

class KnowledgeBaseExtractionService
{
    public function __construct(
        protected FileSourceExtractor $files,
        protected SitemapSourceExtractor $sitemaps,
        protected TextSourceExtractor $text,
        protected UrlSourceExtractor $urls,
    ) {}

    public function extract(KnowledgeBaseSource $source): KnowledgeBaseExtractionResult
    {
        return match ($source->type) {
            'url' => $this->urls->extract($source),
            'sitemap' => $this->sitemaps->extract($source),
            'file' => $this->files->extract($source),
            default => $this->text->extract($source),
        };
    }
}
