<?php

namespace App\Console\Commands;

use App\Modules\PaymentGateways\DataObjects\PaymentData;
use App\Modules\PaymentGateways\Services\PaymentGatewayManager;
use Illuminate\Console\Command;

class PaymentTestCommand extends Command
{
    protected $signature = 'payment:test {gateway? : Gateway to test (defaults to active gateway)}';

    protected $description = 'Test a payment gateway configuration';

    public function handle(PaymentGatewayManager $manager): int
    {
        $gatewayName = $this->argument('gateway') ?? payment_gateway_setting('payment_gateway', 'log');

        $this->info("Testing gateway: {$gatewayName}");
        $this->newLine();

        try {
            $driver = $manager->driver($gatewayName);

            // 1. Check name
            $this->line("  Driver name: <info>{$driver->name()}</info>");

            // 2. Check client config
            $config = $driver->getClientConfig();
            $this->line('  Client config: <info>'.json_encode($config).'</info>');

            // 3. Test payment creation (only for log driver)
            if ($driver->name() === 'log') {
                $data = PaymentData::make(1.00, currency_default_code());
                $response = $driver->createPayment($data);

                $this->line("  Test payment: <info>{$response->status}</info> (ID: {$response->gatewayPaymentId})");

                if ($response->isRedirect()) {
                    $this->line("  Flow: <comment>Redirect</comment> → {$response->redirectUrl}");
                } elseif ($response->requiresClientAction()) {
                    $this->line('  Flow: <comment>Client-side</comment> → '.json_encode($response->clientData));
                } else {
                    $this->line('  Flow: <comment>Immediate completion</comment>');
                }
            } else {
                $this->line('  <comment>Skipping test payment (use log driver for automatic testing)</comment>');
                $this->line('  <comment>For live gateways, verify credentials are set in Settings → Payment Gateways</comment>');
            }

            $this->newLine();
            $this->info('Gateway test passed!');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("Gateway test failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
