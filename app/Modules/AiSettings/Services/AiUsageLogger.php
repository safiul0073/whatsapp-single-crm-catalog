<?php

namespace App\Modules\AiSettings\Services;

use App\Models\User;
use App\Modules\AiSettings\Models\AiUsageLog;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class AiUsageLogger
{
    public function measure(array $data, callable $callback): mixed
    {
        $startedAt = hrtime(true);

        try {
            $response = $callback();

            $this->recordSuccess(array_merge(
                $data,
                $this->usageFromResponse($response),
                [
                    'duration_ms' => $this->durationMs($startedAt),
                    'response' => data_get($response, 'text'),
                ],
            ));

            return $response;
        } catch (Throwable $exception) {
            $this->recordFailure(array_merge($data, [
                'duration_ms' => $this->durationMs($startedAt),
            ]), $exception);

            throw $exception;
        }
    }

    public function recordSuccess(array $data): AiUsageLog
    {
        return $this->record(array_merge($data, ['status' => 'success']));
    }

    public function recordFailure(array $data, Throwable|string|null $exception = null): AiUsageLog
    {
        if ($exception instanceof Throwable) {
            $data['error_message'] = $exception::class.': '.$exception->getMessage();
        } elseif (is_string($exception)) {
            $data['error_message'] = $exception;
        }

        return $this->record(array_merge($data, ['status' => 'failed']));
    }

    public function record(array $data): AiUsageLog
    {
        return AiUsageLog::query()->create([
            'workspace_id' => $this->modelId($data['workspace'] ?? null) ?? $data['workspace_id'] ?? null,
            'user_id' => $this->modelId($data['user'] ?? null) ?? $data['user_id'] ?? null,
            'feature' => (string) ($data['feature'] ?? 'unknown'),
            'provider' => $this->nullableString($data['provider'] ?? null),
            'model' => $this->nullableString($data['model'] ?? null),
            'status' => (string) ($data['status'] ?? 'success'),
            'duration_ms' => $this->nullableInt($data['duration_ms'] ?? null),
            'input_tokens' => $this->nullableInt($data['input_tokens'] ?? null),
            'output_tokens' => $this->nullableInt($data['output_tokens'] ?? null),
            'total_tokens' => $this->nullableInt($data['total_tokens'] ?? null),
            'estimated_cost' => $data['estimated_cost'] ?? null,
            'request_excerpt' => $this->excerpt($data['request'] ?? $data['request_excerpt'] ?? null),
            'response_excerpt' => $this->excerpt($data['response'] ?? $data['response_excerpt'] ?? null),
            'error_message' => $this->excerpt($data['error_message'] ?? null, 1000),
            'metadata' => $this->sanitizeMetadata((array) ($data['metadata'] ?? [])),
        ]);
    }

    public function usageFromResponse(mixed $response): array
    {
        $candidates = [
            data_get($response, 'usage'),
            data_get($response, 'meta.usage'),
            data_get($response, 'metadata.usage'),
        ];

        foreach ($candidates as $usage) {
            if (! is_array($usage) && ! is_object($usage)) {
                continue;
            }

            $inputTokens = $this->nullableInt(
                data_get($usage, 'input_tokens') ??
                data_get($usage, 'prompt_tokens') ??
                data_get($usage, 'promptTokens')
            );

            $outputTokens = $this->nullableInt(
                data_get($usage, 'output_tokens') ??
                data_get($usage, 'completion_tokens') ??
                data_get($usage, 'completionTokens')
            );

            $totalTokens = $this->nullableInt(
                data_get($usage, 'total_tokens') ??
                data_get($usage, 'totalTokens')
            ) ?? (($inputTokens !== null || $outputTokens !== null) ? (($inputTokens ?? 0) + ($outputTokens ?? 0)) : null);

            return [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens,
            ];
        }

        return [
            'input_tokens' => null,
            'output_tokens' => null,
            'total_tokens' => null,
        ];
    }

    protected function modelId(mixed $value): ?int
    {
        if ($value instanceof Workspace || $value instanceof User) {
            return (int) $value->id;
        }

        return null;
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? Str::limit($value, 255, '') : null;
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    protected function durationMs(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    protected function excerpt(mixed $value, int $limit = 700): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return Str::limit($this->scrubSecrets((string) $value), $limit, '');
    }

    protected function sanitizeMetadata(array $metadata): array
    {
        $blocked = ['api_key', 'key', 'token', 'secret', 'credentials', 'password', 'authorization'];

        return collect($metadata)
            ->reject(fn (mixed $value, string|int $key): bool => in_array(Str::lower((string) $key), $blocked, true))
            ->map(function (mixed $value) use ($blocked): mixed {
                if (is_array($value)) {
                    return $this->sanitizeMetadata(Arr::except($value, $blocked));
                }

                return is_string($value) ? Str::limit($this->scrubSecrets($value), 500, '') : $value;
            })
            ->all();
    }

    protected function scrubSecrets(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/sk-[A-Za-z0-9_\-]+/', 'sk-***')
            ->replaceMatches('/Bearer\s+[A-Za-z0-9_\-.]+/i', 'Bearer ***')
            ->replaceMatches('/(["\']?(?:api_key|token|secret|password|authorization)["\']?\s*[:=]\s*)("[^"]+"|\'[^\']+\'|[^\s,}]+)/i', '$1***')
            ->toString();
    }
}
