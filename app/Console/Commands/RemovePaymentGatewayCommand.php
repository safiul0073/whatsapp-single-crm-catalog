<?php

namespace App\Console\Commands;

use App\Modules\PaymentGatewaySettings\Models\PaymentGatewaySetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RemovePaymentGatewayCommand extends Command
{
    protected $signature = 'remove:payment-gateway {name : Gateway name (e.g. Mollie or mollie)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Remove a payment gateway driver, manager registration, config tab, and DB settings';

    public function handle(): int
    {
        $input = $this->argument('name');
        $name = Str::studly($input);
        $slug = Str::lower($name);
        $className = "{$name}PaymentGateway";

        $classPath = app_path("Modules/PaymentGateways/Drivers/{$className}.php");

        $hasClass = File::exists($classPath);
        $hasConfig = $this->configTabExists($slug);
        $hasManagerEntry = $this->managerEntryExists($className);

        if (! $hasClass && ! $hasConfig && ! $hasManagerEntry) {
            $this->error("Payment gateway '{$input}' not found.");
            $this->newLine();
            $this->line('Available gateways:');
            $this->listAvailable();

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->info("=== Remove Payment Gateway: {$name} ===");
            $this->newLine();

            $this->line('<fg=yellow>Will be removed:</>');
            if ($hasClass) {
                $this->line("  - Driver: app/Modules/PaymentGateways/Drivers/{$className}.php");
            }
            if ($hasManagerEntry) {
                $this->line('  - Manager entry in PaymentGatewayManager.php');
            }
            if ($hasConfig) {
                $this->line("  - Settings tab: app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php for '{$slug}'");
                $this->line("  - Select option: '{$slug}' from gateway dropdown");
            }
            $this->line("  - Database settings with prefix: '{$slug}_*'");

            $this->newLine();
            if (! $this->confirm('Proceed with removal?', false)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        if ($hasClass) {
            File::delete($classPath);
            $this->info("Removed driver: {$className}.php");
        }

        if ($hasManagerEntry) {
            $this->removeFromManager($className, $slug);
            $this->info("Removed from PaymentGatewayManager: '{$slug}'");
        }

        if ($hasConfig) {
            $this->removeConfigTab($slug);
            $this->info("Removed settings tab: '{$slug}'");
        }

        $this->removeFromGatewaySelect($slug);

        $deleted = PaymentGatewaySetting::where('key', 'like', "{$slug}_%")->delete();
        if ($deleted > 0) {
            $this->info("Removed {$deleted} database setting(s) with prefix '{$slug}_'");
        }

        $this->call('config:clear');

        $this->newLine();
        $this->info("Payment gateway '{$name}' has been removed.");

        return self::SUCCESS;
    }

    protected function configTabExists(string $slug): bool
    {
        $configPath = base_path('app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php');

        if (! File::exists($configPath)) {
            return false;
        }

        return Str::contains(File::get($configPath), "'{$slug}' => [");
    }

    protected function managerEntryExists(string $className): bool
    {
        $managerPath = app_path('Modules/PaymentGateways/Services/PaymentGatewayManager.php');

        if (! File::exists($managerPath)) {
            return false;
        }

        return Str::contains(File::get($managerPath), $className);
    }

    protected function removeFromManager(string $className, string $slug): void
    {
        $managerPath = app_path('Modules/PaymentGateways/Services/PaymentGatewayManager.php');
        $content = File::get($managerPath);

        $content = preg_replace("/use App\\\\Modules\\\\PaymentGateways\\\\Drivers\\\\{$className};\n/", '', $content);
        $content = preg_replace("/\s*'{$slug}'\s*=>\s*app\({$className}::class\),\n/", '', $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        File::put($managerPath, $content);
    }

    protected function removeConfigTab(string $slug): void
    {
        $configPath = base_path('app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php');
        $content = File::get($configPath);

        $key = "'{$slug}' => [";
        $startPos = strpos($content, $key);

        if ($startPos === false) {
            return;
        }

        $searchStart = $startPos;
        while ($searchStart > 0 && in_array($content[$searchStart - 1], [' ', "\t"], true)) {
            $searchStart--;
        }
        if ($searchStart > 0 && $content[$searchStart - 1] === "\n") {
            $searchStart--;
        }

        $bracketDepth = 0;
        $openBracketPos = strpos($content, '[', $startPos);
        $endPos = $openBracketPos;

        for ($i = $openBracketPos; $i < strlen($content); $i++) {
            if ($content[$i] === '[') {
                $bracketDepth++;
            }
            if ($content[$i] === ']') {
                $bracketDepth--;
            }
            if ($bracketDepth === 0) {
                $endPos = $i + 1;
                if ($endPos < strlen($content) && $content[$endPos] === ',') {
                    $endPos++;
                }
                if ($endPos < strlen($content) && $content[$endPos] === "\n") {
                    $endPos++;
                }
                break;
            }
        }

        $content = substr($content, 0, $searchStart).substr($content, $endPos);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        File::put($configPath, $content);
    }

    protected function removeFromGatewaySelect(string $slug): void
    {
        $configPath = base_path('app/Modules/PaymentGatewaySettings/Config/payment-gateway-settings.php');
        $content = File::get($configPath);

        $content = preg_replace("/\s*'{$slug}'\s*=>\s*'[^']*',\n/", "\n", $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        File::put($configPath, $content);
    }

    protected function listAvailable(): void
    {
        $driversDir = app_path('Modules/PaymentGateways/Drivers');
        if (! File::exists($driversDir)) {
            $this->line('  (none found)');

            return;
        }

        foreach (File::files($driversDir) as $file) {
            $name = $file->getFilenameWithoutExtension();
            if ($name === 'LogPaymentGateway') {
                continue;
            }
            $slug = Str::lower(Str::before($name, 'PaymentGateway'));
            $this->line("  - {$slug} <fg=gray>({$name})</>");
        }
    }
}
