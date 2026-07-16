<?php

namespace App\Modules\Newsletter\Policies;

use App\Modules\Newsletter\Models\Subscriber;
use Illuminate\Contracts\Auth\Authenticatable;

class SubscriberPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('newsletter.view');
    }

    public function view(Authenticatable $user, Subscriber $subscriber): bool
    {
        return $user->can('newsletter.view');
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, Subscriber $subscriber): bool
    {
        return $user->can('newsletter.delete'); // or custom permission, but toggle active is part of view/delete
    }

    public function delete(Authenticatable $user, Subscriber $subscriber): bool
    {
        return $user->can('newsletter.delete');
    }
}
