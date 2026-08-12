<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\DealDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class DealOperationsView
{
    /** @param Builder<Deal> $query
     * @return Builder<Deal>
     */
    public static function dashboardFilter(Builder $query, string $filter, int $userId): Builder
    {
        return match ($filter) {
            'new_assignments' => $query
                ->where('pm_user_id', $userId)
                ->where('created_at', '>=', now()->subDays((int) config('reporting.new_assignment_days'))),
            'customer_response' => $query
                ->whereHas('status', static fn (Builder $status): Builder => $status->where('awaits_customer_response', true)),
            default => throw new InvalidArgumentException('Unknown dashboard filter.'),
        };
    }

    /**
     * @param  Builder<Deal>  $query
     * @return Collection<array-key, mixed>
     */
    public static function board(Builder $query): Collection
    {
        return $query
            ->with(['company', 'programVersion.program', 'projectManager', 'status'])
            ->withCount([
                'documents',
                'documents as received_documents_count' => fn (Builder $query): Builder => $query->whereIn('status', ['uploaded', 'under_review', 'accepted', 'rejected', 'new_version_expected', 'expired']),
                'documents as missing_documents_count' => fn (Builder $query): Builder => $query->where('required_snapshot', true)->whereIn('status', ['to_request', 'requested', 'rejected', 'new_version_expected']),
                'documents as review_documents_count' => fn (Builder $query): Builder => $query->where('status', 'under_review'),
                'documents as expired_documents_count' => fn (Builder $query): Builder => $query->where('status', 'expired'),
                'documents as pending_suggestions_count' => fn (Builder $query): Builder => $query->whereHas('requirementSuggestions', fn (Builder $suggestions): Builder => $suggestions->where('status', 'pending')),
            ])
            ->orderBy('status_changed_at')
            ->get()
            ->groupBy('status_id');
    }

    /** @return EloquentCollection<int, DealDocument> */
    public static function missingDocuments(Deal $deal): EloquentCollection
    {
        return $deal->documents()
            ->where('required_snapshot', true)
            ->whereNotIn('status', ['accepted', 'not_required'])
            ->orderBy('name_snapshot')
            ->get();
    }
}
