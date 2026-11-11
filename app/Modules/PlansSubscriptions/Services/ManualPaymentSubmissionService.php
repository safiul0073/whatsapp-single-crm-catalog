<?php

namespace App\Modules\PlansSubscriptions\Services;

use App\Modules\Media\Services\MediaService;
use App\Modules\PaymentGateways\Models\Payment;
use Illuminate\Http\UploadedFile;

class ManualPaymentSubmissionService
{
    public function __construct(
        protected MediaService $media,
    ) {}

    public function isManual(Payment $payment): bool
    {
        return data_get($payment->metadata, 'driver') === 'manual';
    }

    public function canSubmit(Payment $payment): bool
    {
        return $this->isManual($payment) && $payment->status === 'pending';
    }

    /**
     * Store the customer's payment details and proof files for admin review.
     *
     * @param  array<int, array{label?: string, type?: string, required?: bool}>  $definitions
     * @param  array<int, mixed>  $values
     */
    public function submit(Payment $payment, array $definitions, array $values): Payment
    {
        $submitted = [];
        $proofMediaIds = [];

        foreach ($definitions as $index => $field) {
            $label = (string) ($field['label'] ?? __('Field').' '.($index + 1));
            $value = $values[$index] ?? null;

            if ($value instanceof UploadedFile) {
                $proofMediaIds[] = $this->media->upload($value)->id;
                $submitted[$label] = $value->getClientOriginalName();

                continue;
            }

            if ($value !== null && $value !== '') {
                $submitted[$label] = (string) $value;
            }
        }

        $payment->update([
            'metadata' => array_merge($payment->metadata ?? [], [
                'user_submitted_data' => $submitted,
                'proof_media_ids' => $proofMediaIds,
                'submitted_at' => now()->toIso8601String(),
            ]),
        ]);

        return $payment;
    }

    /**
     * Admin-entered HTML instructions as plain text with line breaks kept.
     */
    public function plainInstructions(Payment $payment): string
    {
        $html = (string) data_get($payment->metadata, 'instructions', '');
        $withBreaks = preg_replace('/<\s*(br\s*\/?|\/p|\/li|\/div|\/h[1-6])\s*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }
}
