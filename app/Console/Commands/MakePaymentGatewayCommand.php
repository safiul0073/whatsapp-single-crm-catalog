<?php

namespace App\Console\Commands;

use App\Modules\PaymentGatewaySettings\Database\Seeders\PaymentGatewaySettingsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakePaymentGatewayCommand extends Command
{
    protected $signature = 'make:payment-gateway {name : Gateway name in PascalCase (e.g. Mollie)}';

    protected $description = 'Scaffold a new payment gateway driver with config tab and manager registration';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $slug = Str::lower($name);
        $className = "{$name}PaymentGateway";
        $humanName = Str::headline($name);

        $classPath = app_path("Modules/PaymentGateways/Drivers/{$className}.php");

        if (File::exists($classPath)) {
            $this->error("Gateway driver already exists: {$classPath}");

            return self::FAILURE;
        }

        $stub = File::get(base_path('stubs/payment-gateway/PaymentGateway.stub'));
        $stub = str_replace(['{{ name }}', '{{ slug }}'], [$name, $slug], $stub);

        File::put($classPath, $stub);
        $this->info("Created driver: {$classPath}");

        $this->addToManager($name, $slug, $className);
        $this->addSettingsTab($slug, $humanName);
        $this->addToGatewaySelect($slug, $humanName);

        $this->call('config:clear');

        $this->call('db:seed', [
            '--class' => PaymentGatewaySettingsSeeder::class,
            '--no-interaction' => true,
        ]);
        $this->info('Seeded payment gateway settings into database.');

        $this->newLine();
        $this->info('Next steps:');
        $this->line("  1. Edit <comment>{$classPath}</comment> to implement the gateway methods");
        $this->line('  2. Customize credential fields in <comment>app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php</comment> for the '.$slug.' tab');
        $this->line('  3. Install the SDK if needed: <comment>composer require vendor/package</comment>');

        return self::SUCCESS;
    }

    protected function addToManager(string $name, string $slug, string $className): void
    {
        $managerPath = app_path('Modules/PaymentGateways/Services/PaymentGatewayManager.php');

        if (! File::exists($managerPath)) {
            $this->warn('PaymentGatewayManager not found. Register the driver manually.');

            return;
        }

        $content = File::get($managerPath);

        if (Str::contains($content, $className)) {
            $this->line('  Driver already referenced in PaymentGatewayManager.');

            return;
        }

        $useStatement = "use App\\Modules\\PaymentGateways\\Drivers\\{$className};";
        if (! Str::contains($content, $useStatement)) {
            $content = str_replace(
                'use App\\Modules\\PaymentGateways\\Drivers\\LogPaymentGateway;',
                "use App\\Modules\\PaymentGateways\\Drivers\\LogPaymentGateway;\nuse App\\Modules\\PaymentGateways\\Drivers\\{$className};",
                $content
            );
        }

        $matchEntry = "            '{$slug}' => app({$className}::class),";
        $content = str_replace(
            '            default => app(LogPaymentGateway::class),',
            "{$matchEntry}\n            default => app(LogPaymentGateway::class),",
            $content
        );

        File::put($managerPath, $content);
        $this->info("Registered in PaymentGatewayManager: '{$slug}'");
    }

    protected function addSettingsTab(string $slug, string $humanName): void
    {
        $configPath = base_path('app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php');

        if (! File::exists($configPath)) {
            $this->warn('Payment gateway settings config not found. Add the settings tab manually.');

            return;
        }

        $content = File::get($configPath);

        if (Str::contains($content, "'{$slug}' => [")) {
            $this->line('  Settings tab already exists.');

            return;
        }

        $entry = <<<PHP

    '{$slug}' => [
        'label' => '{$humanName}',
        'icon' => 'ph ph-credit-card',
        'description' => '{$humanName} payment gateway credentials',
        'webhook_url' => true,
        'settings' => [
            '{$slug}_api_key' => [
                'type' => 'text',
                'label' => 'API Key',
                'hint' => 'Your {$humanName} API key',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
            '{$slug}_api_secret' => [
                'type' => 'password',
                'label' => 'API Secret',
                'hint' => 'Your {$humanName} API secret',
                'default' => '',
                'rules' => 'nullable|string|max:255',
            ],
        ],
    ],
PHP;

        $content = preg_replace('/\n\];\s*\z/', $entry."\n\n];\n", $content);
        File::put($configPath, $content);
        $this->info("Added settings tab: app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php for '{$slug}'");
    }

    protected function addToGatewaySelect(string $slug, string $humanName): void
    {
        $configPath = base_path('app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php');
        $content = File::get($configPath);

        $newOption = "                    '{$slug}' => '{$humanName}',";
        $marker = "'flutterwave' => 'Flutterwave',";

        if (Str::contains($content, "'{$slug}' => '{$humanName}'")) {
            return;
        }

        if (Str::contains($content, $marker)) {
            $content = str_replace($marker, $marker."\n".$newOption, $content);
        }

        File::put($configPath, $content);
        $this->info("Added to gateway select: '{$slug}' => '{$humanName}'");
    }
}
