<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Jobs\SendNotificationEmail;
use App\Domain\Collaboration\Models\Notification;
use App\Models\User;

final class EmailNotificationService
{
    public function queue(
        User $recipient,
        string $type,
        string $title,
        string $body,
        ?SubjectReference $subject = null,
    ): Notification {
        $notification = Notification::query()->create([
            'user_id' => $recipient->id,
            'type' => $type,
            ...($subject?->columns() ?? ['company_id' => null, 'lead_id' => null, 'deal_id' => null, 'deal_document_id' => null]),
            'title' => $title,
            'body' => $body,
            'channel' => 'email',
            'delivery_status' => 'pending',
        ]);

        SendNotificationEmail::dispatch($notification->id)->afterCommit();

        return $notification;
    }

    public function queueExternal(
        string $email,
        ?string $name,
        string $type,
        string $title,
        string $body,
        ?SubjectReference $subject = null,
    ): Notification {
        $notification = Notification::query()->create([
            'recipient_email' => $email,
            'recipient_name' => $name,
            'type' => $type,
            ...($subject?->columns() ?? ['company_id' => null, 'lead_id' => null, 'deal_id' => null, 'deal_document_id' => null]),
            'title' => $title,
            'body' => $body,
            'channel' => 'email',
            'delivery_status' => 'pending',
        ]);

        SendNotificationEmail::dispatch($notification->id)->afterCommit();

        return $notification;
    }
}
