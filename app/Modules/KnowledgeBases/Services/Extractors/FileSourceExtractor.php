<?php

namespace App\Modules\KnowledgeBases\Services\Extractors;

use App\Modules\KnowledgeBases\Models\KnowledgeBaseSource;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class FileSourceExtractor implements KnowledgeBaseExtractor
{
    public function extract(KnowledgeBaseSource $source): KnowledgeBaseExtractionResult
    {
        $path = (string) $source->file_path;

        if (blank($path) || ! Storage::disk('local')->exists($path)) {
            throw new \RuntimeException('Uploaded source file is missing.');
        }

        $absolutePath = Storage::disk('local')->path($path);
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        $text = match ($extension) {
            'pdf' => $this->extractPdf($absolutePath),
            'docx' => $this->extractDocx($absolutePath),
            default => (string) Storage::disk('local')->get($path),
        };

        return new KnowledgeBaseExtractionResult($text, [
            'file_path' => $path,
            'extension' => $extension,
            ...((array) $source->metadata),
        ]);
    }

    protected function extractPdf(string $path): string
    {
        return (new Parser)->parseFile($path)->getText();
    }

    protected function extractDocx(string $path): string
    {
        $document = IOFactory::load($path);
        $text = [];

        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $value = $element->getText();
                    $text[] = is_array($value) ? implode(' ', $value) : (string) $value;
                }
            }
        }

        return trim(implode("\n", $text));
    }
}
