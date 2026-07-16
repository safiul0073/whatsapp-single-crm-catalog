<?php

namespace App\Modules\KnowledgeBases\Services;

use Illuminate\Support\Str;

class KnowledgeBaseChunker
{
    /**
     * @return array<int, string>
     */
    public function split(string $text): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text)) ?: '';

        if ($normalized === '') {
            return [];
        }

        return collect(Str::of($normalized)->split('/(?<=\.|\?|!)\s+/'))
            ->reduce(function (array $chunks, string $sentence): array {
                $sentence = trim($sentence);

                if ($sentence === '') {
                    return $chunks;
                }

                $last = array_key_last($chunks);

                if ($last !== null && mb_strlen($chunks[$last].' '.$sentence) <= 1200) {
                    $chunks[$last] .= ' '.$sentence;

                    return $chunks;
                }

                $chunks[] = Str::limit($sentence, 2000, '');

                return $chunks;
            }, []);
    }
}
