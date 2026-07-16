<?php

namespace App\Modules\Leads\Services;

use App\Models\User;
use App\Modules\AiSettings\Services\AiSettingsService;
use App\Modules\AiSettings\Services\AiUsageLogger;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Leads\Models\Lead;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Ai\AnonymousAgent;
use Throwable;

class LeadGenerationService
{
    public function __construct(
        protected AiSettingsService $settings,
        protected GooglePlacesLeadSourceService $places,
        protected AiUsageLogger $usageLogger,
    ) {}

    /**
     * @param  array{country: string, place?: string|null, category: string, audience?: string|null, channel?: string|null, count: int, notes?: string|null}  $criteria
     * @return array{leads: array<int, array<string, mixed>>, skipped_uncontactable: int, skipped_duplicates: int, metadata: array<string, mixed>}
     */
    public function generate(?User $user, array $criteria, int $workspaceId): array
    {
        $criteria['country'] = Str::upper((string) $criteria['country']);
        $criteria['count'] = max(1, min(25, (int) $criteria['count']));
        $metadata = $this->providerMetadataFor($user) ?? $this->noAiMetadata();
        $query = $this->suggestSearchQuery($user, $criteria, $workspaceId);
        $metadata['platform_ai_used'] = ($metadata['generated_by'] ?? null) === 'platform_ai' && filled($query);
        $metadata['ai_query_used'] = filled($query);

        $result = $this->places->search($workspaceId, $criteria, $query);
        $placesLeads = collect($result['leads'])
            ->map(fn (array $lead): array => array_merge($lead, [
                '_lead_source' => 'google_places',
                '_generation' => [
                    'generated_by' => 'google_places',
                    'provider' => null,
                    'model' => null,
                ],
            ]))
            ->values();
        $missingCount = max(0, $criteria['count'] - $placesLeads->count());
        $fallback = $missingCount > 0
            ? $this->aiFallback($user, $criteria, $workspaceId, $placesLeads->all(), $missingCount)
            : ['leads' => [], 'metadata' => ['ai_fallback_requested_count' => 0, 'ai_fallback_count' => 0, 'ai_fallback_platform_used' => false]];
        $fallbackLeads = collect($fallback['leads']);
        $leads = $placesLeads->merge($fallbackLeads)->take($criteria['count'])->values();
        $usedFallback = $fallbackLeads->isNotEmpty();
        $metadata['platform_ai_used'] = (bool) ($metadata['platform_ai_used'] ?? false) || (bool) ($fallback['metadata']['ai_fallback_platform_used'] ?? false);

        return [
            'leads' => $leads->all(),
            'skipped_uncontactable' => $result['skipped_uncontactable'],
            'skipped_duplicates' => $result['skipped_duplicates'] + max(0, $missingCount - $fallbackLeads->count()),
            'metadata' => array_merge($metadata, $result['metadata'], $fallback['metadata'], [
                'lead_source' => $usedFallback && $placesLeads->isNotEmpty()
                    ? 'mixed'
                    : ($usedFallback ? 'ai_fallback' : ($result['metadata']['lead_source'] ?? 'google_places')),
                'google_places_count' => $placesLeads->count(),
                'ai_fallback_count' => $fallbackLeads->count(),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<int, array<string, mixed>>  $selectedLeads
     * @return array{leads: array<int, array<string, mixed>>, metadata: array<string, mixed>}
     */
    protected function aiFallback(?User $user, array $criteria, int $workspaceId, array $selectedLeads, int $missingCount): array
    {
        $criteria['count'] = $missingCount;
        $fallback = collect();
        $fingerprints = $this->leadFingerprints($workspaceId, $selectedLeads);
        $platformUsed = false;

        $remainingCount = $missingCount;

        if ($remainingCount > 0) {
            $criteria['count'] = $remainingCount;
            $platformDrafts = $this->fromPlatformProvider($criteria, $workspaceId, $user) ?? [];
            $platformMetadata = $this->platformMetadata() ?? $this->noAiMetadata();
            $accepted = $this->uniqueFallbackDrafts($platformDrafts, $criteria, $fingerprints, $remainingCount, [
                'generated_by' => $platformMetadata['generated_by'] ?? 'platform_ai',
                'provider' => $platformMetadata['provider'] ?? null,
                'model' => $platformMetadata['model'] ?? null,
            ]);
            $platformUsed = $accepted !== [];
            $fallback = $fallback->merge($accepted);
        }

        return [
            'leads' => $fallback->take($missingCount)->values()->all(),
            'metadata' => [
                'ai_fallback_requested_count' => $missingCount,
                'ai_fallback_count' => $fallback->count(),
                'ai_fallback_platform_used' => $platformUsed,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $drafts
     * @param  array<string, mixed>  $criteria
     * @param  array<string, bool>  $fingerprints
     * @param  array<string, mixed>  $generation
     * @return array<int, array<string, mixed>>
     */
    protected function uniqueFallbackDrafts(array $drafts, array $criteria, array &$fingerprints, int $limit, array $generation): array
    {
        return collect($drafts)
            ->filter(fn (array $draft): bool => filled($draft['phone'] ?? null) || filled($draft['email'] ?? null))
            ->reject(function (array $draft) use (&$fingerprints): bool {
                $keys = $this->fingerprintsForLead($draft);
                $duplicate = collect($keys)->contains(fn (string $key): bool => isset($fingerprints[$key]));

                if (! $duplicate) {
                    foreach ($keys as $key) {
                        $fingerprints[$key] = true;
                    }
                }

                return $duplicate;
            })
            ->take($limit)
            ->map(fn (array $draft): array => array_merge($draft, [
                'external_source' => null,
                'external_id' => null,
                '_lead_source' => 'ai_fallback',
                '_generation' => $generation,
                'metadata' => array_merge((array) ($draft['metadata'] ?? []), [
                    'lead_source' => 'ai_fallback',
                ]),
                'notes' => Str::limit(trim((string) ($draft['notes'] ?? 'AI fallback lead generated from criteria.')), 2000, ''),
                'category' => ($draft['category'] ?? null) ?: $criteria['category'],
                'country' => ($draft['country'] ?? null) ?: $criteria['country'],
            ]))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $selectedLeads
     * @return array<string, bool>
     */
    protected function leadFingerprints(int $workspaceId, array $selectedLeads): array
    {
        $fingerprints = [];

        Lead::query()
            ->where('workspace_id', $workspaceId)
            ->get(['name', 'company', 'phone', 'email', 'place', 'category', 'metadata'])
            ->each(function (Lead $lead) use (&$fingerprints): void {
                foreach ($this->fingerprintsForLead($lead->toArray()) as $key) {
                    $fingerprints[$key] = true;
                }
            });

        Contact::query()
            ->where('workspace_id', $workspaceId)
            ->get(['name', 'phone', 'email', 'city'])
            ->each(function (Contact $contact) use (&$fingerprints): void {
                foreach ($this->fingerprintsForLead([
                    'name' => $contact->name,
                    'company' => null,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'place' => $contact->city,
                    'category' => null,
                    'metadata' => [],
                ]) as $key) {
                    $fingerprints[$key] = true;
                }
            });

        foreach ($selectedLeads as $lead) {
            foreach ($this->fingerprintsForLead($lead) as $key) {
                $fingerprints[$key] = true;
            }
        }

        return $fingerprints;
    }

    /**
     * @param  array<string, mixed>  $lead
     * @return array<int, string>
     */
    protected function fingerprintsForLead(array $lead): array
    {
        return collect([
            filled($lead['phone'] ?? null) ? 'phone:'.$this->normalizePhone($lead['phone']) : null,
            filled($lead['email'] ?? null) ? 'email:'.strtolower((string) $lead['email']) : null,
            filled(data_get($lead, 'metadata.website_uri')) ? 'domain:'.$this->domainFromUrl(data_get($lead, 'metadata.website_uri')) : null,
            $this->similarityFingerprint($lead),
        ])->filter()->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $lead
     */
    protected function similarityFingerprint(array $lead): ?string
    {
        $name = $this->normalizeText((string) (($lead['company'] ?? null) ?: ($lead['name'] ?? null)));

        if (blank($name)) {
            return null;
        }

        return 'similar:'.implode('|', array_filter([
            $name,
            $this->normalizeText((string) ($lead['place'] ?? null)),
            $this->normalizeText((string) ($lead['category'] ?? null)),
        ]));
    }

    protected function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower($value)));
    }

    protected function normalizePhone(mixed $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        return preg_replace('/\D+/', '', (string) $phone) ?: null;
    }

    protected function domainFromUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $host = parse_url((string) $url, PHP_URL_HOST);
        $host = $host ?: parse_url('https://'.ltrim((string) $url, '/'), PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerMetadataFor(?User $user): ?array
    {
        return $this->platformMetadata();
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<int, array<string, mixed>>|null
     */
    protected function fromPlatformProvider(array $criteria, int $workspaceId, ?User $user): ?array
    {
        if (! $this->hasConfiguredTextProvider()) {
            return null;
        }

        $provider = $this->settings->textProvider();
        $model = $this->settings->textModel();

        try {
            $agent = new AnonymousAgent(
                instructions: $this->instructions(),
                messages: [],
                tools: [],
            );
            $prompt = json_encode($criteria, JSON_THROW_ON_ERROR);

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'lead_generation_ai_fallback',
                    'workspace_id' => $workspaceId,
                    'user' => $user,
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $prompt,
                    'metadata' => [
                        'source' => 'platform',
                        'requested_count' => $criteria['count'] ?? null,
                    ],
                ],
                fn () => $agent->prompt(
                    prompt: $prompt,
                    provider: $provider,
                    model: $model,
                    timeout: 25,
                ),
            );
            $payload = $this->extractJson((string) $response->text);
            $leads = is_array($payload) ? (array) ($payload['leads'] ?? $payload) : [];

            return $this->normalizeDrafts($leads, $criteria);
        } catch (Throwable) {
            return null;
        }
    }

    protected function suggestSearchQuery(?User $user, array $criteria, int $workspaceId): ?string
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        return $this->queryFromPlatformProvider($criteria, $workspaceId, $user);
    }

    protected function queryFromPlatformProvider(array $criteria, int $workspaceId, ?User $user): ?string
    {
        if (! $this->hasConfiguredTextProvider()) {
            return null;
        }

        $provider = $this->settings->textProvider();
        $model = $this->settings->textModel();

        try {
            $agent = new AnonymousAgent(
                instructions: $this->queryInstructions(),
                messages: [],
                tools: [],
            );
            $prompt = json_encode($criteria, JSON_THROW_ON_ERROR);

            $response = $this->usageLogger->measure(
                [
                    'feature' => 'lead_generation_search_query',
                    'workspace_id' => $workspaceId,
                    'user' => $user,
                    'provider' => $provider,
                    'model' => $model,
                    'request' => $prompt,
                    'metadata' => [
                        'source' => 'platform',
                    ],
                ],
                fn () => $agent->prompt(
                    prompt: $prompt,
                    provider: $provider,
                    model: $model,
                    timeout: 15,
                ),
            );
            $payload = $this->extractJson((string) $response->text);
            $query = is_array($payload) ? (string) ($payload['query'] ?? '') : '';

            return filled($query) ? Str::limit($query, 240, '') : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<int, array<string, mixed>>|null
     */
    /**
     * @return array<string, mixed>|null
     */
    protected function platformMetadata(): ?array
    {
        if (! $this->hasConfiguredTextProvider()) {
            return null;
        }

        $provider = $this->settings->textProvider();

        return [
            'generated_by' => 'platform_ai',
            'provider' => $provider,
            'model' => $this->settings->textModel(),
            'platform_ai_available' => true,
            'platform_ai_used' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    protected function hasConfiguredTextProvider(): bool
    {
        return $this->settings->hasConfiguredProvider($this->settings->textProvider());
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
Generate draft sales leads as strict JSON only.
Return {"leads":[...]}.
Each lead must include: name, company, phone, email, country, city, place, category, score, notes.
The user supplies country, place, category, channel, count, audience, and notes.
These are unverified draft prospects for review, not opted-in contacts.
Prefer plausible business/company prospects. Only return leads with at least one usable contact route: a phone number or an email address. If neither is available, omit that lead from the response.
Scores are 0-100 and represent fit with the user's criteria.
PROMPT;
    }

    protected function queryInstructions(): string
    {
        return <<<'PROMPT'
Return strict JSON only in this shape: {"query":"..."}.
Create one concise Google Places Text Search query from the user's country, place, category, audience, channel, and notes.
Do not invent lead names, phone numbers, emails, or business records.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function noAiMetadata(): array
    {
        return [
            'generated_by' => 'google_places',
            'provider' => null,
            'model' => null,
            'platform_ai_used' => false,
            'ai_query_used' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<int, array<string, mixed>>
     */
    protected function testDrafts(array $criteria): array
    {
        $count = (int) $criteria['count'];
        $category = Str::headline((string) $criteria['category']);
        $place = filled($criteria['place'] ?? null) ? (string) $criteria['place'] : 'Target Area';

        return collect(range(1, $count))
            ->map(fn (int $index): array => [
                'name' => "{$category} Prospect {$index}",
                'company' => "{$place} {$category} {$index}",
                'phone' => $index % 2 === 0 ? null : '+1415555'.str_pad((string) (2600 + $index), 4, '0', STR_PAD_LEFT),
                'email' => $index % 2 === 0 ? 'lead'.$index.'@example.com' : null,
                'country' => $criteria['country'],
                'city' => $place,
                'place' => $place,
                'category' => $category,
                'score' => max(50, 92 - ($index * 3)),
                'notes' => trim('AI draft lead generated from criteria. '.(string) ($criteria['audience'] ?? '')),
            ])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $leads
     * @param  array<string, mixed>  $criteria
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDrafts(array $leads, array $criteria): array
    {
        return collect($leads)
            ->filter(fn (mixed $lead): bool => is_array($lead))
            ->map(fn (array $lead): array => [
                'name' => Str::limit((string) Arr::get($lead, 'name', ''), 255, ''),
                'company' => Str::limit((string) Arr::get($lead, 'company', ''), 255, ''),
                'phone' => blank(Arr::get($lead, 'phone')) ? null : Str::limit((string) Arr::get($lead, 'phone'), 32, ''),
                'email' => filter_var(Arr::get($lead, 'email'), FILTER_VALIDATE_EMAIL) ? (string) Arr::get($lead, 'email') : null,
                'country' => Str::upper(Str::limit((string) Arr::get($lead, 'country', $criteria['country']), 2, '')),
                'city' => Str::limit((string) Arr::get($lead, 'city', $criteria['place'] ?? ''), 255, ''),
                'place' => Str::limit((string) Arr::get($lead, 'place', $criteria['place'] ?? ''), 255, ''),
                'category' => Str::limit((string) Arr::get($lead, 'category', $criteria['category']), 255, ''),
                'score' => max(0, min(100, (int) Arr::get($lead, 'score', 60))),
                'notes' => Str::limit((string) Arr::get($lead, 'notes', 'AI draft lead generated from criteria.'), 2000, ''),
            ])
            ->filter(fn (array $lead): bool => filled($lead['phone']) || filled($lead['email']))
            ->take((int) $criteria['count'])
            ->values()
            ->all();
    }

    protected function extractJson(string $text): ?array
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?|```$/m', '', $text) ?: $text;
        }

        $decoded = json_decode(trim($text), true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }
}
