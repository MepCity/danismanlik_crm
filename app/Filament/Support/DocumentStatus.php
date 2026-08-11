<?php

declare(strict_types=1);

namespace App\Filament\Support;

final class DocumentStatus
{
    /** @return array<string, array{label: string, token: string}> */
    public static function all(): array
    {
        return [
            'to_request' => ['label' => __('operations.documents.statuses.to_request'), 'token' => 'neutral'],
            'requested' => ['label' => __('operations.documents.statuses.requested'), 'token' => 'waiting'],
            'uploaded' => ['label' => __('operations.documents.statuses.uploaded'), 'token' => 'info'],
            'under_review' => ['label' => __('operations.documents.statuses.under_review'), 'token' => 'waiting'],
            'accepted' => ['label' => __('operations.documents.statuses.accepted'), 'token' => 'success'],
            'rejected' => ['label' => __('operations.documents.statuses.rejected'), 'token' => 'danger'],
            'new_version_expected' => ['label' => __('operations.documents.statuses.new_version_expected'), 'token' => 'danger'],
            'not_required' => ['label' => __('operations.documents.statuses.not_required'), 'token' => 'neutral'],
            'expired' => ['label' => __('operations.documents.statuses.expired'), 'token' => 'danger'],
        ];
    }

    /** @return array{label: string, token: string} */
    public static function get(string $status): array
    {
        return self::all()[$status] ?? ['label' => $status, 'token' => 'neutral'];
    }
}
