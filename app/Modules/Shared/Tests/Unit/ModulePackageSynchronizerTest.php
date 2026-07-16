<?php

namespace App\Modules\Shared\Tests\Unit;

use App\Modules\Shared\Support\ModuleManifest;
use App\Modules\Shared\Support\ModulePackageSynchronizer;
use PHPUnit\Framework\TestCase;

class ModulePackageSynchronizerTest extends TestCase
{
    public function test_it_merges_module_managed_packages_and_preserves_unmanaged_packages(): void
    {
        $synchronizer = new ModulePackageSynchronizer;

        $composer = [
            'require' => [
                'laravel/framework' => '^13.0',
                'stripe/stripe-php' => '^19.0',
            ],
            'require-dev' => [],
            'extra' => [
                'module-package-sync' => [
                    'managed' => [
                        'require' => ['stripe/stripe-php'],
                        'require-dev' => [],
                    ],
                ],
            ],
        ];

        $manifest = ModuleManifest::fromArray([
            'name' => 'PaymentGateways',
            'alias' => 'payment-gateways',
            'providers' => [],
            'requires' => [],
            'packages' => [
                'require' => [
                    'stripe/stripe-php' => '^20.0',
                ],
                'require-dev' => [],
            ],
            'active' => true,
        ], __DIR__.'/../../../PaymentGateways');

        $result = $synchronizer->synchronize($composer, [[
            'alias' => 'payment-gateways',
            'manifest' => $manifest,
        ]]);

        $this->assertSame('^13.0', $result['composer']['require']['laravel/framework']);
        $this->assertSame('^20.0', $result['composer']['require']['stripe/stripe-php']);
        $this->assertSame(['stripe/stripe-php'], $result['composer']['extra']['module-package-sync']['managed']['require']);
        $this->assertSame(['payment-gateways'], $result['composer']['extra']['module-package-sync']['owners']['require']['stripe/stripe-php']);
    }

    public function test_it_detects_conflicting_constraints(): void
    {
        $synchronizer = new ModulePackageSynchronizer;

        $first = ModuleManifest::fromArray([
            'name' => 'FirstModule',
            'alias' => 'first-module',
            'providers' => [],
            'requires' => [],
            'packages' => [
                'require' => [
                    'stripe/stripe-php' => '^20.0',
                ],
                'require-dev' => [],
            ],
            'active' => true,
        ], __DIR__.'/../../../FirstModule');

        $second = ModuleManifest::fromArray([
            'name' => 'SecondModule',
            'alias' => 'second-module',
            'providers' => [],
            'requires' => [],
            'packages' => [
                'require' => [
                    'stripe/stripe-php' => '^21.0',
                ],
                'require-dev' => [],
            ],
            'active' => true,
        ], __DIR__.'/../../../SecondModule');

        $result = $synchronizer->synchronize(['require' => [], 'require-dev' => []], [
            ['alias' => 'first-module', 'manifest' => $first],
            ['alias' => 'second-module', 'manifest' => $second],
        ]);

        $this->assertNotEmpty($result['conflicts']);
        $this->assertStringContainsString('stripe/stripe-php', $result['conflicts'][0]);
    }
}
