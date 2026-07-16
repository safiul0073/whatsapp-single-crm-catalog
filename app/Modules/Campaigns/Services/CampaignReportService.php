<?php

namespace App\Modules\Campaigns\Services;

use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignReportService
{
    public function refresh(Campaign $campaign): void
    {
        $counts = CampaignRecipientStatus::values();
        $aggregates = [];

        foreach ($counts as $status) {
            $aggregates["{$status}_count"] = $campaign->recipients()->where('status', $status)->count();
        }

        $total = $campaign->recipients()->count();
        $queued = $campaign->recipients()->where('status', CampaignRecipientStatus::Queued->value)->count();
        $sending = $campaign->recipients()->where('status', CampaignRecipientStatus::Sending->value)->count();

        $campaign->update([
            'total_recipients' => $total,
            'queued_count' => $queued,
            'sending_count' => $sending,
            'sent_count' => $aggregates[CampaignRecipientStatus::Sent->value.'_count'] ?? 0,
            'delivered_count' => $aggregates[CampaignRecipientStatus::Delivered->value.'_count'] ?? 0,
            'opened_count' => $aggregates[CampaignRecipientStatus::Opened->value.'_count'] ?? 0,
            'read_count' => $aggregates[CampaignRecipientStatus::Read->value.'_count'] ?? 0,
            'clicked_count' => $aggregates[CampaignRecipientStatus::Clicked->value.'_count'] ?? 0,
            'replied_count' => $aggregates[CampaignRecipientStatus::Replied->value.'_count'] ?? 0,
            'failed_count' => $aggregates[CampaignRecipientStatus::Failed->value.'_count'] ?? 0,
            'skipped_count' => (
                ($aggregates[CampaignRecipientStatus::SkippedOptOut->value.'_count'] ?? 0)
                + ($aggregates[CampaignRecipientStatus::SkippedBlocked->value.'_count'] ?? 0)
                + ($aggregates[CampaignRecipientStatus::SkippedInvalidPhone->value.'_count'] ?? 0)
                + ($aggregates[CampaignRecipientStatus::SkippedInvalid->value.'_count'] ?? 0)
                + ($aggregates[CampaignRecipientStatus::SkippedPolicy->value.'_count'] ?? 0)
            ),
            'skipped_opt_out_count' => ($aggregates[CampaignRecipientStatus::SkippedOptOut->value.'_count'] ?? 0),
            'skipped_invalid_count' => (
                ($aggregates[CampaignRecipientStatus::SkippedInvalidPhone->value.'_count'] ?? 0)
                + ($aggregates[CampaignRecipientStatus::SkippedInvalid->value.'_count'] ?? 0)
            ),
            'skipped_policy_count' => $aggregates[CampaignRecipientStatus::SkippedPolicy->value.'_count'] ?? 0,
        ]);

        $this->updateCampaignStatus($campaign);
    }

    public function summary(Campaign $campaign): array
    {
        $total = $campaign->total_recipients ?: 1;

        return [
            'total_recipients' => $campaign->total_recipients,
            'queued' => $campaign->queued_count,
            'sending' => $campaign->sending_count,
            'sent' => $campaign->sent_count,
            'delivered' => $campaign->delivered_count,
            'opened' => $campaign->opened_count,
            'read' => $campaign->read_count,
            'clicked' => $campaign->clicked_count,
            'replied' => $campaign->replied_count,
            'failed' => $campaign->failed_count,
            'skipped' => $campaign->skipped_count,
            'skipped_opt_out' => $campaign->skipped_opt_out_count,
            'skipped_invalid' => $campaign->skipped_invalid_count,
            'skipped_policy' => $campaign->skipped_policy_count,
            'delivery_rate' => $total > 0 ? round(($campaign->delivered_count / $total) * 100, 2) : 0,
            'read_rate' => $campaign->sent_count > 0 ? round(($campaign->read_count / $campaign->sent_count) * 100, 2) : 0,
            'reply_rate' => $campaign->sent_count > 0 ? round(($campaign->replied_count / $campaign->sent_count) * 100, 2) : 0,
        ];
    }

    public function exportCsv(Campaign $campaign): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="campaign-'.($campaign->uuid ?? $campaign->id).'-report.csv"',
        ];

        $columns = ['Contact', 'Address', 'Status', 'Provider Message ID', 'Sent At', 'Delivered At', 'Read At', 'Failed At', 'Error'];

        return response()->stream(function () use ($campaign, $columns): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $campaign->recipients()
                ->with('contact')
                ->chunkById(500, function ($recipients) use ($handle): void {
                    foreach ($recipients as $recipient) {
                        fputcsv($handle, [
                            $recipient->contact?->name ?? '',
                            $recipient->recipient_address ?? $recipient->to ?? '',
                            $recipient->status?->value ?? '',
                            $recipient->provider_message_id ?? '',
                            $recipient->sent_at?->toDateTimeString() ?? '',
                            $recipient->delivered_at?->toDateTimeString() ?? '',
                            $recipient->read_at?->toDateTimeString() ?? '',
                            $recipient->failed_at?->toDateTimeString() ?? '',
                            $recipient->error_message ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, Response::HTTP_OK, $headers);
    }

    protected function updateCampaignStatus(Campaign $campaign): void
    {
        if (in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled, CampaignStatus::Paused, CampaignStatus::Cancelled, CampaignStatus::Failed], true)) {
            return;
        }

        $active = $campaign->recipients()
            ->whereIn('status', [CampaignRecipientStatus::Queued, CampaignRecipientStatus::Sending])
            ->count();

        if ($active === 0) {
            $campaign->update([
                'status' => CampaignStatus::Completed->value,
                'completed_at' => now(),
            ]);
        }
    }
}
