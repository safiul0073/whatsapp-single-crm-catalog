<?php

namespace App\Modules\SupportTickets;

use App\Modules\Shared\Support\BasePanelModule;
use App\Modules\Shared\Support\NavigationBuilder;
use App\Modules\SupportTickets\Models\SupportTicket;
use App\Modules\SupportTickets\Policies\SupportTicketPolicy;

class Module extends BasePanelModule
{
    public function id(): string
    {
        return 'support-tickets';
    }

    public function permissions(): array
    {
        return [
            'admin' => [
                'support-tickets.view' => 'View Support Tickets',
                'support-tickets.reply' => 'Reply to Support Tickets',
                'support-tickets.manage' => 'Manage Support Ticket Status',
                'support-tickets.delete' => 'Delete Support Tickets',
            ],
        ];
    }

    public function policies(): array
    {
        return [
            SupportTicket::class => SupportTicketPolicy::class,
        ];
    }

    public function adminNavigation(NavigationBuilder $navigation): void
    {
        $navigation
            ->group('Support')
            ->item('Support Tickets', 'admin.support-tickets.*', 'ph-headset', 'support-tickets.view', 60);
    }

    public function userNavigation(NavigationBuilder $navigation): void
    {
        $navigation->item('Support Tickets', 'user.support-tickets.*', 'life-buoy', null, 30);
    }
}
