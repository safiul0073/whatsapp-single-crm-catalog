<?php

namespace App\Modules\KnowledgeBases\Services\Extractors;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use Illuminate\Support\Facades\Http;

class SitemapSourceExtractor implements KnowledgeBaseExtractor
{
    public function extract(KnowledgeBaseSource $source): KnowledgeBaseExtractionResult
    {
        $response = Http::timeout(15)->get((string) $source->url);

        if (! $response->successful()) {
            throw new \RuntimeException('Sitemap returned HTTP '.$response->status().'.');
        }

        $xml = simplexml_load_string((string) $response->body());

        if (! $xml) {
            throw new \RuntimeException('Sitemap XML could not be parsed.');
        }

        $limit = (int) data_get($source->metadata, 'crawl_limit', 10);
        $urls = collect($xml->url ?? [])
            ->map(fn ($url): string => trim((string) $url->loc))
            ->filter()
            ->take(max(1, min($limit, 50)))
            ->values();

        if ($urls->isEmpty()) {
            throw new \RuntimeException('No URLs were found in this sitemap.');
        }

        $documents = $urls->map(function (string $url): string {
            $page = Http::timeout(15)->get($url);

            if (! $page->successful()) {
                return '';
            }

            return trim($url."\n".html_entity_decode(strip_tags((string) $page->body())));
        })->filter();

        return new KnowledgeBaseExtractionResult($documents->implode("\n\n"), [
            'url' => $source->url,
            'sitemap_urls' => $urls->all(),
            'indexed_pages' => $documents->count(),
        ]);
    }
}
