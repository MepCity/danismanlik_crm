<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

final class ActivityTranslator
{
    /** @param array<string, mixed> $payload */
    public function sentence(string $action, array $payload, ?string $subjectLabel = null): string
    {
        $parameters = match ($action) {
            'deal.status_changed', 'lead.status_changed' => [
                'from' => $this->snapshotLabel($payload['from_status'] ?? null),
                'to' => $this->snapshotLabel($payload['to_status'] ?? null),
            ],
            'document.status_changed' => [
                'document' => $payload['document_name'] ?? $subjectLabel ?? trans('collaboration.activity.document'),
                'from' => $this->documentStatus($payload['from_status'] ?? null),
                'to' => $this->documentStatus($payload['to_status'] ?? null),
            ],
            'document.uploaded' => [
                'document' => $payload['document_name'] ?? $subjectLabel ?? trans('collaboration.activity.document'),
                'file' => $payload['original_name'] ?? trans('collaboration.activity.file'),
                'version' => $payload['version_no'] ?? '?',
            ],
            'document.ad_hoc_created', 'document.requirement_suggested', 'document.requirement_decided' => [
                'document' => $this->snapshotLabel($payload['document'] ?? null),
            ],
            'deal.documents_requested', 'deal.checklist_generated',
            'deal.documents_archive_requested', 'deal.documents_archive_downloaded' => [
                'count' => $payload['document_count'] ?? 0,
            ],
            'deal.condition_documents_added' => ['documents' => implode(', ', (array) ($payload['document_names'] ?? []))],
            'document.access_requested', 'document.downloaded' => [
                'document' => $subjectLabel ?? trans('collaboration.activity.document'),
                'version' => $payload['version_no'] ?? '?',
            ],
            'document.scan_completed' => ['document' => $subjectLabel ?? trans('collaboration.activity.document')],
            'task.created', 'task.completed', 'task.reopened' => ['task' => $this->snapshotLabel($payload['task'] ?? null)],
            'task.assigned' => [
                'task' => $this->snapshotLabel($payload['task'] ?? null),
                'assignee' => $this->snapshotLabel($payload['to_assignee'] ?? null),
            ],
            default => [],
        };
        $key = 'collaboration.activity.actions.'.str_replace('.', '_', $action);

        if (trans()->has($key)) {
            return trans($key, $parameters);
        }

        return trans('collaboration.activity.unknown', ['action' => str_replace(['.', '_'], ' ', $action)]);
    }

    public function actor(?string $actorName, string $source): string
    {
        return $source === 'automation' || $actorName === null
            ? trans('collaboration.activity.system')
            : $actorName;
    }

    private function snapshotLabel(mixed $snapshot): string
    {
        if (is_array($snapshot)) {
            return (string) ($snapshot['label'] ?? $snapshot['name'] ?? $snapshot['title'] ?? trans('collaboration.activity.unknown_value'));
        }

        return is_scalar($snapshot) ? (string) $snapshot : trans('collaboration.activity.unknown_value');
    }

    private function documentStatus(mixed $status): string
    {
        $value = $this->snapshotLabel($status);
        $key = 'collaboration.document_statuses.'.$value;

        return trans()->has($key) ? trans($key) : $value;
    }
}
