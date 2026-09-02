<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\Models\Notification;
use App\Models\User;
use App\Support\Collaboration\NotificationUrlResolver;
use Illuminate\Database\Eloquent\Collection;

final class NotificationService
{
    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->count();
    }

    /** @return Collection<int, Notification> */
    public function listForUser(User $user, int $limit = 20): Collection
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function markAsRead(User $user, int $notificationId): void
    {
        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->whereKey($notificationId)
            ->first();

        if ($notification !== null && $notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllAsRead(User $user): void
    {
        Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function targetUrl(User $user, Notification $notification): ?string
    {
        return app(NotificationUrlResolver::class)->resolve($user, $notification);
    }
}
