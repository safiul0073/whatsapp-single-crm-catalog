<?php

namespace App\Modules\SystemNotifications\Services;

use App\Modules\SystemNotifications\Models\SystemNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;

class SystemNotificationService
{
    /**
     * Send a notification to a single notifiable entity.
     *
     * @param  array{title: string, body: string, icon?: string, url?: string, type?: string}  $data
     */
    public function send(Model $notifiable, array $data, string $type = 'general'): SystemNotification
    {
        return SystemNotification::create([
            'type' => $type,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'data' => $data,
        ]);
    }

    /**
     * Send a notification to multiple notifiable entities.
     *
     * @param  array{title: string, body: string, icon?: string, url?: string, type?: string}  $data
     */
    public function sendToMany(BaseCollection $notifiables, array $data, string $type = 'general'): int
    {
        $count = 0;

        foreach ($notifiables as $notifiable) {
            $this->send($notifiable, $data, $type);
            $count++;
        }

        return $count;
    }

    /**
     * Get unread notification count for a notifiable.
     */
    public function getUnreadCount(Model $notifiable): int
    {
        return SystemNotification::forNotifiable($notifiable)
            ->unread()
            ->count();
    }

    /**
     * Get recent notifications for a notifiable.
     */
    public function getRecent(Model $notifiable, int $limit = 10): Collection
    {
        return SystemNotification::forNotifiable($notifiable)
            ->recent($limit)
            ->get();
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $id): SystemNotification
    {
        $notification = SystemNotification::findOrFail($id);
        $notification->markAsRead();

        return $notification;
    }

    /**
     * Mark all notifications as read for a notifiable.
     */
    public function markAllAsRead(Model $notifiable): int
    {
        return SystemNotification::forNotifiable($notifiable)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Get paginated notifications for a notifiable with optional filters.
     */
    public function listPaginated(Model $notifiable, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SystemNotification::forNotifiable($notifiable);

        if (isset($filters['status'])) {
            if ($filters['status'] === 'read') {
                $query->read();
            } elseif ($filters['status'] === 'unread') {
                $query->unread();
            }
        }

        if (! empty($filters['search'])) {
            $query->where('data', 'like', "%{$filters['search']}%");
        }

        $query->latest();

        return $query->paginate($perPage);
    }
}
