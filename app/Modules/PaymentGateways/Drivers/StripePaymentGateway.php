<?php

namespace App\Modules\PaymentGateways\Drivers;

use App\Modules\PaymentGateways\Contracts\PaymentGatewayInterface;
use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\DataObjects\WebhookResult;
use Illuminate\Http\Request;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function name(): string
    {
        return 'stripe';
    }

    /**
     * Ensure required Stripe credentials are configured.
     *
     * @throws RuntimeException
     */
    protected function ensureConfigured(): void
    {
        if (! class_exists(Stripe::class)) {
            throw new RuntimeException(
                'Stripe SDK is not installed. Run: composer require stripe/stripe-php'
            );
        }

        $secretKey = payment_gateway_setting('stripe_secret_key', '');

        if (empty($secretKey)) {
            throw new RuntimeException(
                'Stripe API keys are not configured. Set them in Settings → Payment Gateways.'
            );
        }
    }

    public function createPayment(PaymentData $data): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $secretKey = payment_gateway_setting('stripe_secret_key', '');
            Stripe::setApiKey($secretKey);

            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($data->currency),
                        'product_data' => [
                            'name' => $data->description ?: 'Plan Subscription',
                        ],
                        'unit_amount' => (int) round($data->amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $data->returnUrl.(str_contains($data->returnUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $data->cancelUrl,
                'metadata' => array_merge($data->metadata, array_filter([
                    'user_id' => $data->userId,
                    'user_type' => $data->userType,
                ])),
            ]);

            return PaymentResponse::redirect($session->id, $session->url);
        } catch (ApiErrorException $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyPayment(Request $request): PaymentResponse
    {
        $this->ensureConfigured();

        try {
            $secretKey = payment_gateway_setting('stripe_secret_key', '');
            Stripe::setApiKey($secretKey);

            $sessionId = $request->get('session_id');
            $paymentIntentId = $request->get('payment_intent');

            if ($sessionId) {
                $session = Session::retrieve($sessionId);

                if ($session->payment_status === 'paid') {
                    return PaymentResponse::completed($session->id, [
                        'amount' => $session->amount_total / 100,
                        'currency' => $session->currency,
                        'checkout_session_id' => $session->id,
                        'payment_intent_id' => $session->payment_intent,
                    ]);
                }

                return PaymentResponse::failed('Stripe session is unpaid.');
            }

            if (empty($paymentIntentId)) {
                return PaymentResponse::failed('Missing session_id or payment_intent parameter.');
            }

            $intent = PaymentIntent::retrieve($paymentIntentId);

            return match ($intent->status) {
                'succeeded' => PaymentResponse::completed($intent->id, [
                    'amount' => $intent->amount / 100,
                    'currency' => $intent->currency,
                    'payment_method' => $intent->payment_method,
                ]),
                'requires_action', 'requires_confirmation' => PaymentResponse::clientAction($intent->id, [
                    'client_secret' => $intent->client_secret,
                ]),
                'canceled' => PaymentResponse::failed('Payment was canceled.'),
                default => PaymentResponse::failed("Unexpected payment status: {$intent->status}"),
            };
        } catch (ApiErrorException $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $webhookSecret = payment_gateway_setting('stripe_webhook_secret', '');

        if (empty($webhookSecret)) {
            return false;
        }

        try {
            $signature = $request->header('Stripe-Signature', '');

            Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $webhookSecret
            );

            return true;
        } catch (SignatureVerificationException) {
            return false;
        }
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $payload = $request->all();
        $eventType = $payload['type'] ?? 'unknown';
        $object = $payload['data']['object'] ?? [];

        $gatewayPaymentId = $object['id'] ?? null;

        $status = match ($eventType) {
            'payment_intent.succeeded' => 'completed',
            'payment_intent.payment_failed' => 'failed',
            'payment_intent.canceled' => 'canceled',
            default => null,
        };

        return new WebhookResult(
            gatewayPaymentId: $gatewayPaymentId,
            status: $status,
            eventType: $eventType,
            metadata: $object,
        );
    }

    public function getClientConfig(): array
    {
        return [
            'publishable_key' => payment_gateway_setting('stripe_publishable_key', ''),
        ];
    }
}
