<?php

namespace App\Modules\PaymentGateways;

use App\Modules\PaymentGateways\Models\Payment;
use App\Modules\PaymentGateways\Models\WebhookLog;
use App\Modules\PaymentGateways\Policies\PaymentPolicy;
use App\Modules\PaymentGateways\Policies\WebhookLogPolicy;
use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'payment-gateways';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'payments.view' => 'View payments',
                'payments.approve' => 'Approve payments',
                'webhook-logs.view' => 'View webhook logs',
            ],
        ];
    }

    public function policies(): array
    {
        return [
            Payment::class => PaymentPolicy::class,
            WebhookLog::class => WebhookLogPolicy::class,
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Management')
            ->item(label: 'Payments', route: 'admin.payments.*')
            ->icon('ph-credit-card')
            ->permission('payments.view')
            ->children([
                ['label' => 'All Payments', 'route' => 'admin.payments.*', 'permission' => 'payments.view'],
                ['label' => 'Webhook Logs', 'route' => 'admin.webhook-logs.*', 'permission' => 'webhook-logs.view'],
            ])
            ->order(240);
    }
}
