<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Collaboration\Models\Task;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only workspace query for the customer screen.
 *
 * Seeing a company does not imply seeing every lead, deal or task attached to
 * it. Each collection is guarded twice: the policy decides whether the viewer
 * may list that record type at all, and ScopedQuery narrows the rows to the
 * viewer's own/team/all data scope. Without the policy gate a viewer with a
 * matching data scope but no permission would still receive rows.
 *
 * Tabs, counters and the operations column all read from this single guarded
 * set — Blade never builds an access query of its own.
 */
final class CompanyWorkspaceSummary
{
    /** @var array<string, int> */
    private array $statusBreakdown = [];

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  Collection<int, Deal>  $deals
     * @param  Collection<int, Task>  $tasks
     */
    private function __construct(
        public readonly Collection $leads,
        public readonly Collection $deals,
        public readonly Collection $tasks,
        public readonly int $activeDeals,
        public readonly int $pendingDocuments,
        public readonly int $openLeads,
        public readonly int $openTasks,
        public readonly int $overdueTasks,
        public readonly int $completedTasks,
        public readonly ?string $ownerName,
        public readonly ?Carbon $lastActivityAt,
    ) {}

    public static function for(Company $company, User $viewer): self
    {
        $scope = app(ScopedQuery::class);
        $companyId = $company->getKey();

        $gate = Gate::forUser($viewer);

        /** @var Collection<int, Deal> $deals */
        $deals = $gate->allows('viewAny', Deal::class)
            ? $scope->apply(Deal::query(), $viewer, 'viewAny')
                ->where('company_id', $companyId)
                ->with(['status', 'projectManager', 'programVersion.program'])
                ->withCount(['documents as pending_documents_count' => fn (Builder $query): Builder => $query->whereIn('status', [
                    'requested', 'uploaded', 'under_review', 'rejected', 'new_version_expected', 'expired',
                ])])
                ->latest()
                ->get()
            : Deal::query()->whereRaw('1 = 0')->get();

        /** @var Collection<int, Lead> $leads */
        $leads = $gate->allows('viewAny', Lead::class)
            ? $scope->apply(Lead::query(), $viewer, 'viewAny')
                ->where('company_id', $companyId)
                ->with(['status', 'owner', 'interestedProgramVersion.program', 'primaryContact'])
                ->latest()
                ->get()
            : Lead::query()->whereRaw('1 = 0')->get();

        /** @var Collection<int, Task> $tasks */
        $tasks = $gate->allows('viewAny', Task::class)
            ? $scope->apply(Task::query(), $viewer, 'viewAny')
                ->where('company_id', $companyId)
                ->with('assignee')
                ->latest()
                ->get()
            : Task::query()->whereRaw('1 = 0')->get();

        $summary = new self(
            leads: $leads,
            deals: $deals,
            tasks: $tasks,
            activeDeals: $deals->filter(fn (Deal $deal): bool => $deal->status->is_terminal !== true)->count(),
            pendingDocuments: (int) $deals->sum('pending_documents_count'),
            openLeads: $leads->whereNull('converted_deal_id')->count(),
            openTasks: $tasks->whereNull('completed_at')->count(),
            overdueTasks: $tasks->filter(
                fn (Task $task): bool => $task->completed_at === null
                    && $task->due_at !== null
                    && $task->due_at->isPast()
            )->count(),
            completedTasks: $tasks->whereNotNull('completed_at')->count(),
            ownerName: $company->owner?->name,
            lastActivityAt: $deals->max('updated_at') ?? $company->updated_at,
        );

        $summary->statusBreakdown = $deals
            ->groupBy(fn (Deal $deal): string => (string) $deal->status->label)
            ->map(fn ($group): int => $group->count())
            ->all();

        return $summary;
    }

    /** @return array<string, int> */
    public function statusBreakdown(): array
    {
        return $this->statusBreakdown;
    }

    /** @return Collection<int, Deal> */
    public function pendingDocumentDeals(): Collection
    {
        return $this->deals
            ->filter(fn (Deal $deal): bool => (int) $deal->getAttribute('pending_documents_count') > 0)
            ->values();
    }

    /** @return Collection<int, Task> */
    public function overdueTaskRows(): Collection
    {
        return $this->tasks->filter(
            fn (Task $task): bool => $task->completed_at === null
                && $task->due_at !== null
                && $task->due_at->isPast()
        )->values();
    }

    /** @return Collection<int, Task> */
    public function openTaskRows(): Collection
    {
        return $this->tasks->filter(
            fn (Task $task): bool => $task->completed_at === null
                && ! ($task->due_at !== null && $task->due_at->isPast())
        )->values();
    }

    /** @return Collection<int, Task> */
    public function completedTaskRows(): Collection
    {
        return $this->tasks->filter(fn (Task $task): bool => $task->completed_at !== null)->values();
    }

    /** @return Collection<int, Lead> */
    public function openLeadRows(): Collection
    {
        return $this->leads->whereNull('converted_deal_id')->values();
    }
}
