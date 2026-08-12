<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\Models\Notification;

final class NotificationWriter
{
    public function assignmentPending(int $userId, int $dealId, string $reference): void
    {
        Notification::query()->create([
            'user_id' => $userId,
            'type' => 'deal.assignment_pending',
            'deal_id' => $dealId,
            'title' => trans('marketing.notifications.assignment_title'),
            'body' => trans('marketing.notifications.assignment_body', ['reference' => $reference]),
            'channel' => 'in_app',
        ]);
    }

    public function dealAssigned(int $userId, int $dealId, string $reference): void
    {
        Notification::query()->create([
            'user_id' => $userId,
            'type' => 'deal.assigned',
            'deal_id' => $dealId,
            'title' => trans('operations.assignment.notification_title'),
            'body' => trans('operations.assignment.notification_body', ['reference' => $reference]),
            'channel' => 'in_app',
        ]);
    }

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

    public function documentExpired(int $userId, int $dealId, int $documentId, string $documentName): void
    {
        Notification::query()->create([
            'user_id' => $userId,
            'type' => 'document.expired',
            'deal_document_id' => $documentId,
            'title' => trans('documents.notifications.expired_title'),
            'body' => trans('documents.notifications.expired_body', ['document' => $documentName]),
            'channel' => 'in_app',
        ]);
    }
}
