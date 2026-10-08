<?php

namespace App\Modules\Commerce\Database\Factories;

use App\Models\User;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderPayment;
use App\Modules\Commerce\Services\OrderMoney;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OrderPayment> */
class OrderPaymentFactory extends Factory
{
    protected $model = OrderPayment::class;

    public function definition(): array
    {
        return ['amount' => '5.00', 'currency' => 'USD', 'method' => 'cash', 'reference' => null, 'submission_reference' => (string) Str::uuid(), 'payload_hash' => hash('sha256', Str::random()), 'recorded_at' => now(), 'change_amount' => 0];
    }

    public function forOrder(Order $order, User $staff): static
    {
        return $this->state(function (array $attributes) use ($order, $staff): array {
            $payload = ['order_id' => $order->id, 'amount' => OrderMoney::minor($attributes['amount'], $order->moneyPrecision()), 'currency' => $order->currency, 'method' => $attributes['method'], 'reference' => $attributes['reference'], 'tendered_amount' => null];

            return ['workspace_id' => $order->workspace_id, 'order_id' => $order->id, 'staff_id' => $staff->id, 'currency' => $order->currency, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))];
        });
    }
}
