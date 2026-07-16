<?php

namespace App\Modules\SupportTickets\Policies;

use App\Models\User;
use App\Modules\SupportTickets\Models\SupportTicket;

class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportTicket $supportTicket): bool
    {
        return $supportTicket->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function reply(User $user, SupportTicket $supportTicket): bool
    {
        return $supportTicket->user_id === $user->id;
    }
}
