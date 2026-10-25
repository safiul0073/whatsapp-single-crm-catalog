<?php

namespace App\Modules\KnowledgeBases\Services\Extractors;

class HtmlText
{
    /**
     * Readable text from HTML, without script/style/head content.
     */
    public static function clean(string $html): string
    {
        $html = preg_replace('#<(script|style|noscript|head|svg)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#</(p|div|li|h[1-6]|tr|br)>|<br\s*/?>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;

        return trim(preg_replace("/\n\s*\n+/", "\n\n", $text) ?? $text);
    }
}
