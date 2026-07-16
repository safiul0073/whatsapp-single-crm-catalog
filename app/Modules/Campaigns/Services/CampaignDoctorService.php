<?php

namespace App\Modules\Campaigns\Services;

use App\Models\User;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\Contacts\Models\Contact;
use App\Modules\MarketingChannels\Models\ChannelAccount;
use App\Modules\MarketingChannels\Services\WorkspaceResolver;
use App\Modules\MessageTemplates\Models\MessageTemplate;
use App\Modules\PlansSubscriptions\Services\PlanLimitService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CampaignDoctorService
{
    public const FEATURE = 'campaign_ai_doctor';

    public function __construct(
        protected WorkspaceResolver $workspaces,
        protected PlanLimitService $limits,
        protected AudienceResolver $audiences,
    ) {}

    public function canUse(int $workspaceId): bool
    {
        return $this->limits->featureEnabled($workspaceId, self::FEATURE);
    }

    public function diagnose(?User $user, array $data): array
    {
        $workspace = $this->workspaces->current($user);

        if (! $this->canUse($workspace->id)) {
            return [
                'enabled' => false,
                'score' => null,
                'summary' => 'AI Campaign Doctor is available on premium plans. Upgrade to review campaign risk before sending.',
                'items' => [],
                'cost' => null,
                'best_send_time' => null,
            ];
        }

        $channel = ChannelAccount::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail((int) $data['channel_account_id']);
        $provider = $channel->provider;
        $audience = $this->audienceFromData($data);
        $contacts = $this->audiences->contacts($workspace->id, $audience);
        $sendableContacts = $contacts
            ->filter(fn (Contact $contact): bool => $this->audiences->sendability($contact, $provider) === null)
            ->values();
        $template = $this->templateFor($workspace->id, $data);
        $messageText = $this->messageText($data, $template);

        $items = collect()
            ->merge($this->optInItems($contacts, $provider))
            ->merge($this->templateItems($data, $template, $messageText))
            ->merge($this->blockRiskItems($messageText))
            ->merge($this->fatigueItems($workspace->id, $contacts))
            ->merge($this->costItems($provider, $sendableContacts, $template));

        $bestSendTime = $this->bestSendTime((string) ($data['timezone'] ?? config('app.timezone')));
        $items->push([
            'key' => 'best_send_time',
            'label' => 'Best send time',
            'severity' => 'info',
            'message' => "Best time: {$bestSendTime['label']}",
            'meta' => $bestSendTime,
        ]);

        $score = $this->score($items);

        return [
            'enabled' => true,
            'score' => $score,
            'summary' => $this->summary($score, $items),
            'items' => $items->values()->all(),
            'cost' => $this->costForecast($provider, $sendableContacts, $template),
            'best_send_time' => $bestSendTime,
        ];
    }

    protected function audienceFromData(array $data): array
    {
        return [
            'audience_type' => $data['audience_type'] ?? 'groups',
            'audience_ids' => array_values(array_filter((array) ($data['audience_ids'] ?? []))),
            'segment_id' => $data['segment_id'] ?? null,
        ];
    }

    protected function templateFor(int $workspaceId, array $data): ?MessageTemplate
    {
        if (($data['message_type'] ?? 'custom') !== 'template' || blank($data['message_template_id'] ?? null)) {
            return null;
        }

        return MessageTemplate::query()
            ->where('workspace_id', $workspaceId)
            ->find((int) $data['message_template_id']);
    }

    protected function messageText(array $data, ?MessageTemplate $template): string
    {
        if ($template) {
            $body = (string) ($template->body ?: data_get(collect($template->components ?? [])->firstWhere('type', 'BODY'), 'text', ''));

            return trim($template->name.' '.$template->subject.' '.$body);
        }

        return trim((string) ($data['message_subject'] ?? '').' '.(string) ($data['message_body'] ?? ''));
    }

    protected function optInItems(Collection $contacts, string $provider): array
    {
        $blocked = $contacts->whereNotNull('blocked_at')->count();
        $missingOptIn = $contacts->filter(function (Contact $contact): bool {
            $status = $contact->opt_in_status instanceof \BackedEnum
                ? $contact->opt_in_status->value
                : (string) $contact->opt_in_status;

            return $status !== 'subscribed' || $contact->opt_in_at === null;
        })->count();
        $unsendable = $contacts
            ->filter(fn (Contact $contact): bool => $this->audiences->sendability($contact, $provider) !== null)
            ->count();
        $items = [];

        if ($missingOptIn > 0 || $blocked > 0 || $unsendable > 0) {
            $parts = [];

            if ($missingOptIn > 0) {
                $parts[] = number_format($missingOptIn).' contacts have no confirmed opt-in source';
            }

            if ($blocked > 0) {
                $parts[] = number_format($blocked).' contacts are blocked';
            }

            if ($unsendable > 0) {
                $parts[] = number_format($unsendable).' contacts are currently unsendable';
            }

            $items[] = [
                'key' => 'opt_in_risk',
                'label' => 'Opt-in risk',
                'severity' => $blocked > 0 || $missingOptIn >= 10 ? 'high' : 'medium',
                'message' => implode('; ', $parts).'.',
                'meta' => compact('blocked', 'missingOptIn', 'unsendable'),
            ];
        } else {
            $items[] = [
                'key' => 'opt_in_risk',
                'label' => 'Opt-in risk',
                'severity' => 'success',
                'message' => 'No opt-in issues found in the selected audience.',
                'meta' => ['blocked' => 0, 'missingOptIn' => 0, 'unsendable' => 0],
            ];
        }

        return $items;
    }

    protected function templateItems(array $data, ?MessageTemplate $template, string $messageText): array
    {
        if (($data['message_type'] ?? 'custom') !== 'template') {
            return [];
        }

        if (! $template) {
            return [[
                'key' => 'template_risk',
                'label' => 'Template risk',
                'severity' => 'high',
                'message' => 'Selected template could not be found for this workspace.',
                'meta' => [],
            ]];
        }

        $category = Str::lower((string) $template->category);
        $promotionalMatches = $this->matchedTerms($messageText, (array) config('campaign-doctor.promotional_words', []));

        if ($category === 'utility' && $promotionalMatches !== []) {
            return [[
                'key' => 'template_risk',
                'label' => 'Template risk',
                'severity' => 'medium',
                'message' => 'This utility template sounds promotional.',
                'meta' => ['category' => $category, 'matches' => $promotionalMatches],
            ]];
        }

        return [[
            'key' => 'template_risk',
            'label' => 'Template risk',
            'severity' => 'success',
            'message' => 'Template category and copy look aligned.',
            'meta' => ['category' => $category],
        ]];
    }

    protected function blockRiskItems(string $messageText): array
    {
        $matches = $this->matchedTerms($messageText, (array) config('campaign-doctor.risky_words', []));

        if ($matches === []) {
            return [[
                'key' => 'block_risk',
                'label' => 'Block risk',
                'severity' => 'success',
                'message' => 'No high-risk words detected.',
                'meta' => ['matches' => []],
            ]];
        }

        return [[
            'key' => 'block_risk',
            'label' => 'Block risk',
            'severity' => count($matches) >= 3 ? 'high' : 'medium',
            'message' => 'High-risk words detected: '.implode(', ', $matches).'.',
            'meta' => ['matches' => $matches],
        ]];
    }

    protected function fatigueItems(int $workspaceId, Collection $contacts): array
    {
        if ($contacts->isEmpty()) {
            return [[
                'key' => 'audience_fatigue',
                'label' => 'Audience fatigue',
                'severity' => 'success',
                'message' => 'No audience contacts selected yet.',
                'meta' => ['fatigued_contacts' => 0],
            ]];
        }

        $threshold = (int) config('campaign-doctor.fatigue_threshold', 3);
        $since = now()->subDays((int) config('campaign-doctor.fatigue_window_days', 7));
        $fatigued = CampaignRecipient::query()
            ->selectRaw('contact_id, count(*) as touches')
            ->where('workspace_id', $workspaceId)
            ->whereIn('contact_id', $contacts->pluck('id')->all())
            ->where(function ($query) use ($since): void {
                $query->where('queued_at', '>=', $since)
                    ->orWhere('sent_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            })
            ->groupBy('contact_id')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->get();

        if ($fatigued->isEmpty()) {
            return [[
                'key' => 'audience_fatigue',
                'label' => 'Audience fatigue',
                'severity' => 'success',
                'message' => 'No audience fatigue detected this week.',
                'meta' => ['fatigued_contacts' => 0, 'threshold' => $threshold],
            ]];
        }

        return [[
            'key' => 'audience_fatigue',
            'label' => 'Audience fatigue',
            'severity' => 'medium',
            'message' => number_format($fatigued->count())." contacts were messaged {$threshold}+ times this week.",
            'meta' => ['fatigued_contacts' => $fatigued->count(), 'threshold' => $threshold],
        ]];
    }

    protected function costItems(string $provider, Collection $sendableContacts, ?MessageTemplate $template): array
    {
        $cost = $this->costForecast($provider, $sendableContacts, $template);

        if (! $cost['available']) {
            return [[
                'key' => 'cost_forecast',
                'label' => 'Cost forecast',
                'severity' => 'info',
                'message' => 'Meta cost forecast is available for WhatsApp campaigns.',
                'meta' => $cost,
            ]];
        }

        return [[
            'key' => 'cost_forecast',
            'label' => 'Cost forecast',
            'severity' => 'info',
            'message' => 'Estimated Meta cost: '.$cost['formatted_total'],
            'meta' => $cost,
        ]];
    }

    protected function costForecast(string $provider, Collection $sendableContacts, ?MessageTemplate $template): array
    {
        $currency = (string) config('campaign-doctor.whatsapp_rates.currency', 'USD');

        if ($provider !== 'whatsapp') {
            return [
                'available' => false,
                'currency' => $currency,
                'total' => null,
                'formatted_total' => 'Unavailable',
                'sendable_count' => $sendableContacts->count(),
            ];
        }

        $category = Str::lower((string) ($template?->category ?: config('campaign-doctor.whatsapp_rates.default_category', 'marketing')));
        $rate = $this->rateFor($sendableContacts, $category);
        $total = round($rate * $sendableContacts->count(), 2);

        return [
            'available' => true,
            'currency' => $currency,
            'category' => $category,
            'rate' => $rate,
            'sendable_count' => $sendableContacts->count(),
            'total' => $total,
            'formatted_total' => '$'.number_format($total, 2),
        ];
    }

    protected function rateFor(Collection $sendableContacts, string $category): float
    {
        $country = (string) ($sendableContacts->pluck('country')->filter()->countBy()->sortDesc()->keys()->first()
            ?? config('campaign-doctor.whatsapp_rates.default_country', 'default'));
        $countryRates = (array) config("campaign-doctor.whatsapp_rates.countries.{$country}", []);
        $defaultRates = (array) config('campaign-doctor.whatsapp_rates.countries.default', []);

        return (float) ($countryRates[$category] ?? $defaultRates[$category] ?? $defaultRates['marketing'] ?? 0);
    }

    protected function bestSendTime(string $timezone): array
    {
        $safeTimezone = in_array($timezone, timezone_identifiers_list(), true) ? $timezone : config('app.timezone');
        $label = (string) config('campaign-doctor.best_send_time.label', '9-11 AM local time');
        $now = Carbon::now($safeTimezone);

        return [
            'label' => $label,
            'timezone' => $safeTimezone,
            'local_date' => $now->toDateString(),
            'start_hour' => (int) config('campaign-doctor.best_send_time.start_hour', 9),
            'end_hour' => (int) config('campaign-doctor.best_send_time.end_hour', 11),
        ];
    }

    protected function matchedTerms(string $text, array $terms): array
    {
        $lower = Str::lower($text);

        return collect($terms)
            ->map(fn (string $term): string => trim($term))
            ->filter(fn (string $term): bool => $term !== '' && Str::contains($lower, Str::lower($term)))
            ->values()
            ->all();
    }

    protected function score(Collection $items): int
    {
        $penalty = $items->sum(fn (array $item): int => match ($item['severity']) {
            'high' => 30,
            'medium' => 15,
            'info' => 0,
            default => 0,
        });

        return max(0, 100 - $penalty);
    }

    protected function summary(int $score, Collection $items): string
    {
        $high = $items->where('severity', 'high')->count();
        $medium = $items->where('severity', 'medium')->count();

        if ($high > 0) {
            return "Campaign risk score {$score}/100. Review high-risk findings before sending.";
        }

        if ($medium > 0) {
            return "Campaign risk score {$score}/100. A few warnings need review before launch.";
        }

        return "Campaign risk score {$score}/100. No major risks found.";
    }
}
