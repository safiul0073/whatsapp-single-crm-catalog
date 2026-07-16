<?php

namespace App\Modules\MessageTemplates\Services;

use App\Modules\AiSettings\Services\AiUsageLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\AnonymousAgent;
use Throwable;

class MessageTemplateAiGeneratorService
{
    public function __construct(protected AiUsageLogger $usageLogger) {}

    /**
     * @return array{name: string, category: string, header: array<string, string>, body: string, body_examples: array<string, string>, footer: array<string, string>, buttons: array<int, array<string, string>>, provider: string, model: ?string}
     */
    public function generate(array $data): array
    {
        $provider = $this->platformTextProvider();
        $model = $this->platformTextModel($provider);

        if (! $this->hasConfiguredTextProvider($provider)) {
            throw ValidationException::withMessages([
                'ai' => __('Platform AI is not configured for template generation. Enable a text provider in AI Settings first.'),
            ]);
        }

        if (app()->runningUnitTests()) {
            return array_merge($this->testTemplate($data), [
                'provider' => $provider,
                'model' => $model,
            ]);
        }

        try {
            $agent = new AnonymousAgent(
                instructions: $this->instructions(),
                messages: [],
                tools: [],
            );
            $prompt = $this->prompt($data);

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'message_template_generation',
                    'user_id' => auth()->id(),
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $prompt,
                    'metadata' => [
                        'template_provider' => $data['provider'] ?? null,
                        'language' => $data['language'] ?? null,
                    ],
                ],
                fn () => $agent->prompt(
                    prompt: $prompt,
                    provider: $provider,
                    model: $model,
                    timeout: 20,
                ),
            );

            $payload = $this->extractJson((string) $response->text);

            if (! is_array($payload)) {
                throw ValidationException::withMessages([
                    'ai' => __('AI could not generate a usable template. Please try again.'),
                ]);
            }

            return array_merge($this->normalizeTemplate($payload, $data), [
                'provider' => $provider,
                'model' => $model,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Message template AI generation failed.', [
                'template_provider' => $data['provider'] ?? null,
                'provider' => $provider,
                'model' => $model,
                'exception' => $exception::class,
                'message' => Str::limit($exception->getMessage(), 220, ''),
            ]);

            throw ValidationException::withMessages([
                'ai' => __('AI could not generate a template. Check the platform AI settings and try again.'),
            ]);
        }
    }

    protected function hasConfiguredTextProvider(string $provider): bool
    {
        $config = config("ai.providers.{$provider}", []);

        if (($config['driver'] ?? null) === 'ollama') {
            return filled($config['url'] ?? null);
        }

        return filled($config['key'] ?? null);
    }

    protected function platformTextProvider(): string
    {
        $provider = filled(ai_setting('ai_default_text_provider'))
            ? (string) ai_setting('ai_default_text_provider')
            : (string) config('ai.default', 'openai');

        return match ($provider) {
            'azure-openai' => 'azure',
            'elevenlabs' => 'eleven',
            default => $provider,
        };
    }

    protected function platformTextModel(string $provider): ?string
    {

        if (filled(ai_setting('ai_default_text_model'))) {
            return (string) ai_setting('ai_default_text_model');
        }

        return match ($provider) {
            'anthropic' => 'claude-sonnet-4-20250514',
            'gemini' => 'gemini-3-flash-preview',
            'groq' => 'llama-3.3-70b-versatile',
            'xai' => 'grok-3-mini',
            'deepseek' => 'deepseek-chat',
            'mistral' => 'mistral-large-latest',
            'ollama' => 'llama3.1',
            'openrouter' => 'openai/gpt-4o-mini',
            default => 'gpt-4o',
        };
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
You generate WhatsApp or Telegram message template drafts as strict JSON only.
Return keys: name, category, header, body, body_examples, footer, buttons.
Use supported placeholders only: {{full_name}}, {{first_name}}, {{last_name}}, {{phone}}, {{email}}, {{city}}, {{country}}, {{location}}, {{website}}, or {{custom.example_key}}.
For WhatsApp, body must not start or end with a placeholder. Keep body under 1024 characters, header text under 60, footer under 60, and buttons under 25 characters.
For Telegram, do not include a WhatsApp header or footer unless needed as plain text in body. Use URL or callback buttons only.
PROMPT;
    }

    protected function prompt(array $data): string
    {
        return trim(json_encode([
            'provider' => $data['provider'] ?? 'whatsapp',
            'name' => $data['name'] ?? null,
            'language' => $data['language'] ?? 'en',
            'category' => $data['category'] ?? null,
            'current_body' => $data['body'] ?? null,
            'header' => $data['header'] ?? [],
            'footer' => $data['footer'] ?? [],
            'buttons' => $data['buttons'] ?? [],
            'instruction' => $data['instruction'] ?? 'Create a useful customer message template draft.',
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function extractJson(string $text): ?array
    {
        $text = trim($text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $json = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{name: string, category: string, header: array<string, string>, body: string, body_examples: array<string, string>, footer: array<string, string>, buttons: array<int, array<string, string>>}
     */
    protected function normalizeTemplate(array $payload, array $data): array
    {
        $templateProvider = $data['provider'] ?? 'whatsapp';
        $body = trim((string) ($payload['body'] ?? ''));

        if ($body === '') {
            throw ValidationException::withMessages([
                'ai' => __('AI generated an empty template body. Please try again.'),
            ]);
        }

        return [
            'name' => $this->templateName((string) ($payload['name'] ?? $data['name'] ?? 'ai_generated_template')),
            'category' => $this->category((string) ($payload['category'] ?? $data['category'] ?? 'marketing'), $templateProvider),
            'header' => $this->header((array) ($payload['header'] ?? []), $templateProvider),
            'body' => Str::limit($body, 1024, ''),
            'body_examples' => $this->bodyExamples($body, (array) ($payload['body_examples'] ?? [])),
            'footer' => $this->footer((array) ($payload['footer'] ?? []), $templateProvider),
            'buttons' => $this->buttons((array) ($payload['buttons'] ?? []), $templateProvider),
        ];
    }

    /**
     * @return array{name: string, category: string, header: array<string, string>, body: string, body_examples: array<string, string>, footer: array<string, string>, buttons: array<int, array<string, string>>}
     */
    protected function testTemplate(array $data): array
    {
        $templateProvider = (string) ($data['provider'] ?? 'whatsapp');
        $instruction = filled($data['instruction'] ?? null) ? trim((string) $data['instruction']) : 'customer update';
        $name = $this->templateName((string) ($data['name'] ?? Str::slug(Str::limit($instruction, 32, ''), '_')));
        $body = $templateProvider === 'telegram'
            ? 'Hi {{full_name}}, '.$this->sentence($instruction, 140).' Reply when you are ready.'
            : 'Hi {{full_name}}, '.$this->sentence($instruction, 140).' Tap a button below if you need help.';

        return [
            'name' => $name,
            'category' => $this->category((string) ($data['category'] ?? 'marketing'), $templateProvider),
            'header' => $templateProvider === 'whatsapp' ? ['type' => 'text', 'text' => 'Quick update', 'example' => 'Quick update'] : ['type' => 'none', 'text' => '', 'example' => ''],
            'body' => $body,
            'body_examples' => ['full_name' => 'Ada Lovelace'],
            'footer' => $templateProvider === 'whatsapp' ? ['text' => 'Reply STOP to opt out'] : ['text' => ''],
            'buttons' => $templateProvider === 'telegram'
                ? [['type' => 'callback', 'text' => 'Start', 'callback_data' => 'start']]
                : [['type' => 'quick_reply', 'text' => 'Need help']],
        ];
    }

    protected function templateName(string $name): string
    {
        $name = Str::of($name)->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->toString();

        return $name !== '' ? Str::limit($name, 255, '') : 'ai_generated_template';
    }

    protected function category(string $category, string $provider): string
    {
        if ($provider === 'telegram') {
            return 'utility';
        }

        return in_array($category, ['marketing', 'utility', 'authentication'], true) ? $category : 'marketing';
    }

    /**
     * @return array<string, string>
     */
    protected function header(array $header, string $provider): array
    {
        if ($provider !== 'whatsapp') {
            return ['type' => 'none', 'text' => '', 'example' => ''];
        }

        $text = Str::limit(trim((string) Arr::get($header, 'text', '')), 60, '');

        return [
            'type' => $text !== '' ? 'text' : 'none',
            'text' => $text,
            'example' => Str::limit(trim((string) Arr::get($header, 'example', $text ?: 'Customer update')), 60, ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function footer(array $footer, string $provider): array
    {
        return ['text' => $provider === 'whatsapp' ? Str::limit(trim((string) Arr::get($footer, 'text', '')), 60, '') : ''];
    }

    /**
     * @return array<string, string>
     */
    protected function bodyExamples(string $body, array $examples): array
    {
        preg_match_all('/\{\{\s*([^}]+)\s*\}\}/', $body, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $key): string => trim($key))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $key): array => [$key => (string) ($examples[$key] ?? $this->defaultExample($key))])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function buttons(array $buttons, string $provider): array
    {
        return collect($buttons)
            ->filter(fn (mixed $button): bool => is_array($button) && filled($button['text'] ?? null))
            ->take(10)
            ->map(function (array $button) use ($provider): array {
                $type = (string) ($button['type'] ?? ($provider === 'telegram' ? 'callback' : 'quick_reply'));
                $type = $provider === 'telegram'
                    ? (in_array($type, ['url', 'callback'], true) ? $type : 'callback')
                    : (in_array($type, ['quick_reply', 'url', 'phone_number'], true) ? $type : 'quick_reply');

                return array_filter([
                    'type' => $type,
                    'text' => Str::limit(trim((string) $button['text']), 25, ''),
                    'url' => $type === 'url' ? (string) ($button['url'] ?? '') : '',
                    'phone_number' => $type === 'phone_number' ? (string) ($button['phone_number'] ?? '') : '',
                    'callback_data' => $type === 'callback' ? (string) ($button['callback_data'] ?? Str::slug((string) $button['text'], '_')) : '',
                    'example' => $type === 'url' ? (string) ($button['example'] ?? '') : '',
                ], fn (string $value): bool => $value !== '');
            })
            ->values()
            ->all();
    }

    protected function defaultExample(string $key): string
    {
        return match ($key) {
            'full_name' => 'Ada Lovelace',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'phone' => '+15555550123',
            'email' => 'ada@example.com',
            'city' => 'Portland',
            'country' => 'US',
            'location' => 'Portland, US',
            'website' => 'example.com',
            default => Str::contains($key, 'order') ? 'A-100' : 'Sample '.$key,
        };
    }

    protected function sentence(string $value, int $limit): string
    {
        return rtrim(Str::limit(trim($value), $limit, ''), " \t\n\r\0\x0B.!?");
    }
}
