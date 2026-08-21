<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Crm\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class MarketingOperationsView
{
    /** @param Builder<Lead> $query
     * @return Collection<int, Lead>
     */
    public static function callsDue(Builder $query, string $filter = 'due'): Collection
    {
        $query
            ->whereNotNull('next_call_at')
            ->where('next_call_at', '<=', now()->endOfDay());

        match ($filter) {
            'today' => $query->whereBetween('next_call_at', [now()->startOfDay(), now()->endOfDay()]),
            'overdue' => $query->where('next_call_at', '<', now()->startOfDay()),
            default => null,
        };

        return $query
            ->with([
                'company.contacts' => fn ($contacts) => $contacts->where('is_active', true)->orderByDesc('is_primary')->orderBy('id'),
                'status',
                'interactions' => fn ($interactions) => $interactions->latest('occurred_at'),
            ])
            ->withCount('interactions')
            ->orderBy('next_call_at')
            ->get();
    }

    /** @param Builder<Lead> $query
     * @return Collection<array-key, mixed>
     */
    public static function board(Builder $query): Collection
    {
        return $query
            ->with([
                'company', 'owner', 'interestedProgramVersion.program', 'status.outgoingTransitions.toStatus',
                'interactions' => fn ($interactions) => $interactions->latest('occurred_at'),
            ])
            ->orderBy('created_at')
            ->get()
            ->groupBy('status_id');
    }
}
