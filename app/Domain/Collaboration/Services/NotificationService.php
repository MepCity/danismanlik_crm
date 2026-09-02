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
        $resolver = app(NotificationUrlResolver::class);

        $unread = Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->get();

        return $unread->filter(fn (Notification $n) => $resolver->isAccessible($user, $n))->count();
    }

    /** @return Collection<int, Notification> */
    public function listForUser(User $user, int $limit = 20): Collection
    {
        $resolver = app(NotificationUrlResolver::class);

        /** @var Collection<int, Notification> $notifications */
        $notifications = Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->orderByDesc('id')
            ->limit($limit * 2)
            ->get()
            ->filter(fn (Notification $n) => $resolver->isAccessible($user, $n))
            ->take($limit)
            ->values();

        return $notifications;
    }

    public function markAsRead(User $user, int $notificationId): void
    {
        $resolver = app(NotificationUrlResolver::class);

        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->whereKey($notificationId)
            ->first();

        if ($notification !== null && $notification->read_at === null && $resolver->isAccessible($user, $notification)) {
            $notification->update(['read_at' => now()]);
        }
    }

    public function markAllAsRead(User $user): void
    {
        $resolver = app(NotificationUrlResolver::class);

        $unread = Notification::query()
            ->where('user_id', $user->id)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->get()
            ->filter(fn (Notification $n) => $resolver->isAccessible($user, $n));

        if ($unread->isNotEmpty()) {
            Notification::query()
                ->whereIn('id', $unread->pluck('id')->all())
                ->update(['read_at' => now()]);
        }
    }

    public function targetUrl(User $user, Notification $notification): ?string
    {
        return app(NotificationUrlResolver::class)->resolve($user, $notification);
    }
}
