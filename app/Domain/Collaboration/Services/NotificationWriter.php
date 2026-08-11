<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\Models\Notification;

final class NotificationWriter
{
    public function conditionDocumentsAdded(int $userId, int $dealId, int $count, string $documentNames): void
    {
        Notification::query()->create([
            'user_id' => $userId,
            'type' => 'checklist.condition_documents_added',
            'deal_id' => $dealId,
            'title' => trans('documents.notifications.condition_added_title'),
            'body' => trans('documents.notifications.condition_added_body', [
                'count' => $count,
                'documents' => $documentNames,
            ]),
            'channel' => 'in_app',
        ]);
    }
}
