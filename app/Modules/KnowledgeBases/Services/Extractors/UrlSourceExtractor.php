<?php

namespace App\Modules\KnowledgeBases\Services\Extractors;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use Illuminate\Support\Facades\Http;

class UrlSourceExtractor implements KnowledgeBaseExtractor
{
    public function extract(KnowledgeBaseSource $source): KnowledgeBaseExtractionResult
    {
        $response = Http::timeout(15)->get((string) $source->url);

        if (! $response->successful()) {
            throw new \RuntimeException('URL returned HTTP '.$response->status().'.');
        }

        $text = HtmlText::clean((string) $response->body());

        return new KnowledgeBaseExtractionResult($text, [
            'url' => $source->url,
            'http_status' => $response->status(),
        ]);
    }
}
