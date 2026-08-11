<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Jobs;

use App\Domain\Collaboration\Models\Notification;
use App\Support\Queue\AutomationJob;
use Illuminate\Contracts\Mail\Mailer;
use Throwable;

final class SendNotificationEmail extends AutomationJob
{
    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $notificationId) {}

    protected function execute(): void
    {
        $notification = Notification::query()->with('user')->findOrFail($this->notificationId);

        if ($notification->delivery_status === 'sent') {
            return;
        }

        $notification->update(['delivery_status' => 'pending', 'error' => null]);

        try {
            $productName = (string) config('app.name');
            $body = trans('collaboration.mail.template', [
                'product' => $productName,
                'body' => $notification->body,
            ]);
            $subject = trans('collaboration.mail.subject', [
                'product' => $productName,
                'title' => $notification->title,
            ]);

            app(Mailer::class)->raw($body, function ($message) use ($notification, $productName, $subject): void {
                $external = $notification->recipient_email !== null;
                $message->from((string) config('mail.from.address'), $productName)
                    ->to(
                        $external ? $notification->recipient_email : $notification->user->email,
                        $external ? $notification->recipient_name : $notification->user->name,
                    )
                    ->subject($subject);
            });
            $notification->update(['delivery_status' => 'sent', 'error' => null]);
        } catch (Throwable $exception) {
            $notification->update([
                'delivery_status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Notification::query()->whereKey($this->notificationId)->update([
            'delivery_status' => 'failed',
            'error' => mb_substr($exception?->getMessage() ?? trans('collaboration.mail.unknown_error'), 0, 2000),
        ]);
    }
}
