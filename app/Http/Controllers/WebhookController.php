<?php

namespace App\Http\Controllers;

use App\Modules\PaymentGateways\Jobs\ProcessWebhook;
use App\Modules\PaymentGateways\Models\WebhookLog;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    /**
     * Handle incoming payment webhook from any gateway.
     *
     * URL: POST /webhooks/payments/{gateway}
     * Example: POST /webhooks/payments/stripe
     */
    public function handle(string $gateway, Request $request, PaymentGatewayManager $manager): Response
    {
        // Idempotency: check if this event was already processed
        $eventId = $request->header('X-Webhook-Id')
            ?? $request->header('Stripe-Event-Id')
            ?? $request->input('id')
            ?? $request->input('event_id');

        if ($eventId && WebhookLog::where('gateway_event_id', $eventId)->exists()) {
            return response('Already processed', 200);
        }

        // Verify webhook signature
        $driver = $manager->driver($gateway);
        if (! $driver->verifyWebhook($request)) {
            return response('Invalid signature', 403);
        }

        // Log the webhook
        $log = WebhookLog::create([
            'gateway' => $gateway,
            'event_type' => $request->input('type')
                ?? $request->input('event')
                ?? $request->input('event_type', 'unknown'),
            'gateway_event_id' => $eventId,
            'payload' => $request->all(),
        ]);

        // Dispatch to queue for async processing
        ProcessWebhook::dispatch($log);

        return response('OK', 200);
    }
}
