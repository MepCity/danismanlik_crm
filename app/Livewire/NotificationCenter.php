<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Collaboration\Models\Notification;
use App\Domain\Collaboration\Services\NotificationService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class NotificationCenter extends Component
{
    public bool $isOpen = false;

    public function toggleOpen(): void
    {
        $this->isOpen = ! $this->isOpen;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function markAsRead(int $notificationId): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        app(NotificationService::class)->markAsRead($user, $notificationId);
    }

    public function markAllAsRead(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        app(NotificationService::class)->markAllAsRead($user);
    }

    public function openNotification(int $notificationId): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $service = app(NotificationService::class);
        $service->markAsRead($user, $notificationId);

        $notification = Notification::query()
            ->where('user_id', $user->id)
            ->whereKey($notificationId)
            ->first();

        $this->isOpen = false;

        if ($notification !== null) {
            $url = $service->targetUrl($user, $notification);
            if ($url !== null) {
                $this->redirect($url, navigate: true);
            }
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $unreadCount = 0;
        $notifications = collect();

        if ($user instanceof User) {
            $service = app(NotificationService::class);
            $unreadCount = $service->unreadCount($user);
            $notifications = $service->listForUser($user, 20);
        }

        return view('livewire.notification-center', [
            'unreadCount' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }
}
