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
        $count = 0;
        $lastId = null;
        $chunkSize = 100;

        while (true) {
            $query = Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', 'in_app')
                ->whereNull('read_at')
                ->orderByDesc('id')
                ->limit($chunkSize);

            if ($lastId !== null) {
                $query->where('id', '<', $lastId);
            }

            /** @var Collection<int, Notification> $chunk */
            $chunk = $query->get();

            if ($chunk->isEmpty()) {
                break;
            }

            $lastId = (int) $chunk->last()->id;
            $accessible = $resolver->filterAccessible($user, $chunk);
            $count += $accessible->count();
        }

        return $count;
    }

    /** @return Collection<int, Notification> */
    public function listForUser(User $user, int $limit = 20): Collection
    {
        $resolver = app(NotificationUrlResolver::class);
        $result = new Collection;
        $chunkSize = max($limit, 50);
        $lastId = null;

        while ($result->count() < $limit) {
            $query = Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', 'in_app')
                ->orderByDesc('id')
                ->limit($chunkSize);

            if ($lastId !== null) {
                $query->where('id', '<', $lastId);
            }

            /** @var Collection<int, Notification> $chunk */
            $chunk = $query->get();

            if ($chunk->isEmpty()) {
                break;
            }

            $lastId = (int) $chunk->last()->id;
            $accessible = $resolver->filterAccessible($user, $chunk);

            foreach ($accessible as $notification) {
                $result->push($notification);
                if ($result->count() >= $limit) {
                    break;
                }
            }
        }

        return $result;
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
        $lastId = null;
        $chunkSize = 100;

        while (true) {
            $query = Notification::query()
                ->where('user_id', $user->id)
                ->where('channel', 'in_app')
                ->whereNull('read_at')
                ->orderByDesc('id')
                ->limit($chunkSize);

            if ($lastId !== null) {
                $query->where('id', '<', $lastId);
            }

            /** @var Collection<int, Notification> $chunk */
            $chunk = $query->get();

            if ($chunk->isEmpty()) {
                break;
            }

            $lastId = (int) $chunk->last()->id;
            $accessible = $resolver->filterAccessible($user, $chunk);

            if ($accessible->isNotEmpty()) {
                Notification::query()
                    ->whereIn('id', $accessible->pluck('id')->all())
                    ->update(['read_at' => now()]);
            }
        }
    }

    public function targetUrl(User $user, Notification $notification): ?string
    {
        return app(NotificationUrlResolver::class)->resolve($user, $notification);
    }
}
