<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Access\DTOs\BreakGlassNotification;
use App\Domain\Access\Services\BreakGlassNotifier;
use App\Domain\Collaboration\Models\Notification;
use App\Models\User;

final class DatabaseBreakGlassNotifier implements BreakGlassNotifier
{
    public function granted(BreakGlassNotification $notification): void
    {
        User::role('Şirket Yetkilisi')->where('is_active', true)->each(
            static function (User $officer) use ($notification): void {
                Notification::query()->create([
                    'user_id' => $officer->id,
                    'type' => 'access.break_glass_granted',
                    'title' => trans('access.notifications.break_glass_title'),
                    'body' => trans('access.notifications.break_glass_body', [
                        'user' => $notification->userName,
                        'expires_at' => $notification->expiresAt->format('d.m.Y H:i'),
                        'reason' => $notification->reason,
                    ]),
                    'channel' => 'in_app',
                ]);
            },
        );
    }
}
