<?php

namespace App\Modules\Leads\Services;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Leads\Models\Lead;
use App\Modules\PlaceApiSettings\Services\PlaceApiSettingsService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GooglePlacesLeadSourceService
{
    private const TEXT_SEARCH_ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

    private const FIELD_MASK = 'places.id,places.displayName,places.formattedAddress,places.addressComponents,places.internationalPhoneNumber,places.nationalPhoneNumber,places.websiteUri,places.googleMapsUri,places.rating,places.userRatingCount,places.businessStatus,places.types,nextPageToken';

    public function __construct(
        protected PlaceApiSettingsService $settings
    ) {}

    /**
     * @param  array{country: string, place?: string|null, category: string, audience?: string|null, channel?: string|null, count: int, notes?: string|null}  $criteria
     * @return array{leads: array<int, array<string, mixed>>, skipped_uncontactable: int, skipped_duplicates: int, metadata: array<string, mixed>}
     */
    public function search(int $workspaceId, array $criteria, ?string $query = null): array
    {
        $this->ensureConfigured();

        $status = $this->settings->status();
        $requestedCount = max(1, min((int) $criteria['count'], (int) $status['result_limit']));
        $places = $this->fetchPlaces($criteria, $requestedCount, $query);
        $normalized = collect($places)
            ->map(fn (array $place): array => $this->normalizePlace($place, $criteria))
            ->values();

        $contactable = $normalized
            ->filter(fn (array $lead): bool => filled($lead['phone']) || filled($lead['email']))
            ->values();

        $unique = $contactable
            ->reject(fn (array $lead): bool => $this->isDuplicate($workspaceId, $lead))
            ->values();
        $leads = $unique->take($requestedCount)->values();

        return [
            'leads' => $leads->all(),
            'skipped_uncontactable' => $normalized->count() - $contactable->count(),
            'skipped_duplicates' => $contactable->count() - $unique->count(),
            'metadata' => [
                'lead_source' => 'google_places',
                'place_provider' => 'google_places',
                'query' => $query ?: $this->buildQuery($criteria),
                'requested_count' => $requestedCount,
                'returned_count' => count($places),
                'language' => $status['language'],
                'region' => $status['region'],
                'field_mask' => self::FIELD_MASK,
            ],
        ];
    }

    public function ensureConfigured(): void
    {
        if (! $this->settings->isConfigured()) {
            throw ValidationException::withMessages([
                'place_api' => 'Admin has not configured Google Places API settings.',
            ]);
        }
    }

    public function buildQuery(array $criteria): string
    {
        return trim(collect([
            $criteria['category'] ?? null,
            $criteria['audience'] ?? null,
            $criteria['place'] ?? null,
            $criteria['country'] ?? null,
        ])->filter()->implode(' '));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchPlaces(array $criteria, int $requestedCount, ?string $query): array
    {
        $status = $this->settings->status();
        $payload = [
            'textQuery' => $query ?: $this->buildQuery($criteria),
            'pageSize' => min(20, max(1, $requestedCount * 2)),
        ];

        if ($status['language']) {
            $payload['languageCode'] = $status['language'];
        }

        if ($status['region']) {
            $payload['regionCode'] = $status['region'];
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->settings->apiKey(),
            'X-Goog-FieldMask' => self::FIELD_MASK,
        ])->timeout(20)->post(self::TEXT_SEARCH_ENDPOINT, $payload);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'place_api' => 'Google Places lead search failed. Please check the admin Place API settings.',
            ]);
        }

        return array_values((array) $response->json('places', []));
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizePlace(array $place, array $criteria): array
    {
        $displayName = (string) data_get($place, 'displayName.text', '');
        $phone = data_get($place, 'internationalPhoneNumber') ?: data_get($place, 'nationalPhoneNumber');
        $website = data_get($place, 'websiteUri');
        $email = $this->emailFromWebsite($website);
        $city = $this->addressComponent($place, ['locality', 'postal_town', 'administrative_area_level_2']) ?: ($criteria['place'] ?? null);
        $country = $this->addressComponent($place, ['country'], true) ?: Str::upper((string) $criteria['country']);
        $rating = (float) data_get($place, 'rating', 0);
        $ratingCount = (int) data_get($place, 'userRatingCount', 0);

        return [
            'name' => Str::limit($displayName, 255, ''),
            'company' => Str::limit($displayName, 255, ''),
            'phone' => filled($phone) ? Str::limit((string) $phone, 32, '') : null,
            'email' => $email,
            'country' => Str::upper(Str::limit((string) $country, 2, '')),
            'city' => Str::limit((string) $city, 255, ''),
            'place' => Str::limit((string) data_get($place, 'formattedAddress', $criteria['place'] ?? ''), 255, ''),
            'category' => Str::limit((string) ($criteria['category'] ?? Arr::first((array) data_get($place, 'types', []))), 255, ''),
            'score' => $this->score($rating, $ratingCount),
            'notes' => Str::limit('Google Places lead. '.(string) ($criteria['notes'] ?? ''), 2000, ''),
            'external_source' => 'google_places',
            'external_id' => data_get($place, 'id'),
            'metadata' => [
                'google_place_id' => data_get($place, 'id'),
                'google_maps_uri' => data_get($place, 'googleMapsUri'),
                'website_uri' => $website,
                'formatted_address' => data_get($place, 'formattedAddress'),
                'business_status' => data_get($place, 'businessStatus'),
                'rating' => $rating ?: null,
                'user_rating_count' => $ratingCount ?: null,
                'types' => data_get($place, 'types', []),
                'raw' => $place,
            ],
        ];
    }

    protected function isDuplicate(int $workspaceId, array $lead): bool
    {
        $externalSource = $lead['external_source'] ?? null;
        $externalId = $lead['external_id'] ?? null;
        $phone = $this->normalizePhone($lead['phone'] ?? null);
        $email = filled($lead['email'] ?? null) ? strtolower((string) $lead['email']) : null;
        $domain = $this->domainFromUrl(data_get($lead, 'metadata.website_uri'));

        $leadDuplicate = Lead::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($query) use ($externalSource, $externalId, $phone, $email, $domain, $lead): void {
                if (filled($externalSource) && filled($externalId)) {
                    $query->orWhere(fn ($inner) => $inner->where('external_source', $externalSource)->where('external_id', $externalId));
                }

                if (filled($phone)) {
                    $query->orWhere('phone', $lead['phone']);
                }

                if (filled($email)) {
                    $query->orWhere('email', $email);
                }

                if (filled($domain)) {
                    $query->orWhere('metadata->website_domain', $domain);
                }
            })
            ->exists();

        if ($leadDuplicate) {
            return true;
        }

        return Contact::query()
            ->where('workspace_id', $workspaceId)
            ->where(function ($query) use ($lead, $email): void {
                if (filled($lead['phone'] ?? null)) {
                    $query->orWhere('phone', $lead['phone']);
                }

                if (filled($email)) {
                    $query->orWhere('email', $email);
                }
            })
            ->exists();
    }

    protected function score(float $rating, int $ratingCount): int
    {
        if ($rating <= 0 && $ratingCount <= 0) {
            return 65;
        }

        return max(45, min(100, (int) round(($rating * 15) + min(25, $ratingCount / 10))));
    }

    protected function addressComponent(array $place, array $types, bool $short = false): ?string
    {
        foreach ((array) data_get($place, 'addressComponents', []) as $component) {
            if (array_intersect($types, (array) ($component['types'] ?? [])) !== []) {
                return $short
                    ? data_get($component, 'shortText')
                    : data_get($component, 'longText');
            }
        }

        return null;
    }

    protected function emailFromWebsite(?string $website): ?string
    {
        $domain = $this->domainFromUrl($website);

        return $domain ? 'info@'.$domain : null;
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

    protected function normalizePhone(mixed $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        return preg_replace('/\D+/', '', (string) $phone) ?: null;
    }
}
