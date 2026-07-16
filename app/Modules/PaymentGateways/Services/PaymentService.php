<?php

namespace App\Modules\PaymentGateways\Services;

use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\DataObjects\PaymentResponse;
use App\Modules\PaymentGateways\Events\PaymentCreated;
use App\Modules\PaymentGateways\Events\PaymentFailed;
use App\Modules\PaymentGateways\Events\PaymentSucceeded;
use App\Modules\PaymentGateways\Exceptions\PaymentException;
use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\Shared\Traits\HasCrudOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentService
{
    use HasCrudOperations;

    protected string $model = Payment::class;

    /** @var array<string> */
    protected array $searchable = ['uuid', 'gateway_payment_id', 'description'];

    /** @var array<string> */
    protected array $filterable = ['status', 'gateway', 'currency'];

    protected string $defaultSortBy = 'created_at';

    protected string $defaultSortOrder = 'desc';

    public function __construct(
        protected PaymentGatewayManager $manager
    ) {}

    /**
     * Create a new payment through the active (or specified) gateway.
     *
     * Usage:
     *   $payment = app(PaymentService::class)->charge(29.99, 'USD', [
     *       'description' => 'Pro Plan',
     *       'gateway' => 'stripe',          // optional, defaults to active gateway
     *       'user_id' => auth()->id(),       // optional, auto-detected
     *       'return_url' => route('payment.return'),
     *       'cancel_url' => route('payment.cancel'),
     *       'metadata' => ['plan_id' => 5],
     *   ]);
     *
     * @return array{payment: Payment, response: PaymentResponse}
     */
    public function charge(float $amount, string $currency = '', array $options = []): array
    {
        $currency = $currency ?: currency_default_code();

        $user = auth()->user();
        $data = new PaymentData(
            amount: $amount,
            currency: strtoupper($currency),
            description: $options['description'] ?? null,
            paymentMethod: $options['payment_method'] ?? null,
            userId: $options['user_id'] ?? $user?->getKey(),
            userType: $options['user_type'] ?? $user?->getMorphClass(),
            metadata: $options['metadata'] ?? [],
            returnUrl: $options['return_url'] ?? null,
            cancelUrl: $options['cancel_url'] ?? null,
        );

        $gateway = $this->manager->driver($options['gateway'] ?? null);

        $payment = Payment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $data->userId,
            'user_type' => $data->userType,
            'gateway' => $gateway->name(),
            'amount' => $data->amount,
            'currency' => $data->currency,
            'status' => 'pending',
            'payment_method' => $data->paymentMethod,
            'description' => $data->description,
            'metadata' => $data->metadata,
        ]);

        try {
            $response = $gateway->createPayment($data);

            $payment->update([
                'gateway_payment_id' => $response->gatewayPaymentId,
                'status' => $response->status,
                'paid_at' => $response->isComplete() ? now() : null,
                'metadata' => array_merge($payment->metadata ?? [], $response->metadata),
            ]);

            if ($response->isComplete()) {
                event(new PaymentSucceeded($payment));
            } else {
                event(new PaymentCreated($payment));
            }

            return ['payment' => $payment->fresh(), 'response' => $response];
        } catch (\Throwable $e) {
            $payment->update([
                'status' => 'failed',
                'metadata' => array_merge($payment->metadata ?? [], ['error' => $e->getMessage()]),
            ]);

            event(new PaymentFailed($payment));

            throw new PaymentException(
                message: $e->getMessage(),
                gatewayName: $gateway->name(),
                previous: $e,
            );
        }
    }

    /**
     * Verify a payment after user returns from redirect or JS callback.
     */
    public function verify(Request $request, ?string $gateway = null): Payment
    {
        $driver = $this->manager->driver($gateway);
        $response = $driver->verifyPayment($request);

        if (! $response->gatewayPaymentId) {
            throw new PaymentException('No gateway payment ID in verification response.', $driver->name());
        }

        $payment = Payment::where('gateway_payment_id', $response->gatewayPaymentId)->firstOrFail();

        $payment->update([
            'status' => $response->status,
            'paid_at' => $response->isComplete() ? now() : $payment->paid_at,
            'metadata' => array_merge($payment->metadata ?? [], $response->metadata),
        ]);

        if ($response->isComplete()) {
            event(new PaymentSucceeded($payment));
        } elseif ($response->isFailed()) {
            event(new PaymentFailed($payment));
        }

        return $payment->fresh();
    }
}
