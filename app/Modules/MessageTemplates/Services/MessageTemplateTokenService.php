<?php

namespace App\Modules\MessageTemplates\Services;

class MessageTemplateTokenService
{
    /**
     * @return array<int, string>
     */
    public function extract(string $text): array
    {
        preg_match_all('/\{\{\s*([^}]+)\s*\}\}/', $text, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function hasTokens(string $text): bool
    {
        return $this->extract($text) !== [];
    }

    public function hasLeadingOrTrailingToken(string $text): bool
    {
        return preg_match('/^\s*\{\{\s*[^}]+\s*\}\}/', $text) === 1
            || preg_match('/\{\{\s*[^}]+\s*\}\}\s*$/', $text) === 1;
    }

    /**
     * @return array{payload: array<string, mixed>, variables: array<string, mixed>}
     */
    public function compilePayloadForMeta(array $payload): array
    {
        $variables = [];

        $payload['components'] = collect($payload['components'] ?? [])
            ->map(function (array $component) use (&$variables): array {
                $type = strtoupper((string) ($component['type'] ?? ''));

                if (in_array($type, ['HEADER', 'BODY'], true) && filled($component['text'] ?? null)) {
                    [$component['text'], $map] = $this->compileTextForMeta((string) $component['text']);

                    if ($map !== []) {
                        $variables[strtolower($type)] = $map;
                    }
                }

                if ($type === 'BUTTONS') {
                    $component['buttons'] = collect($component['buttons'] ?? [])
                        ->map(function (array $button, int $index) use (&$variables): array {
                            if (strtoupper((string) ($button['type'] ?? '')) === 'URL' && filled($button['url'] ?? null)) {
                                [$button['url'], $map] = $this->compileTextForMeta((string) $button['url']);

                                if ($map !== []) {
                                    $variables['buttons'][$index] = $map;
                                }
                            }

                            return $button;
                        })
                        ->values()
                        ->all();
                }

                return $component;
            })
            ->values()
            ->all();

        return ['payload' => $payload, 'variables' => $variables];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    public function compileTextForMeta(string $text): array
    {
        $tokens = $this->extract($text);
        $map = [];
        $next = 1;

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $map[$token] = (string) $token;

                continue;
            }

            $map[$token] = (string) $next;
            $next++;
        }

        $compiled = preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function (array $matches) use ($map): string {
            $token = trim($matches[1]);

            return '{{'.($map[$token] ?? $token).'}}';
        }, $text);

        return [(string) $compiled, $map];
    }
}
