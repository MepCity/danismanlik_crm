<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Access\Enums\DataScope;
use App\Domain\Access\Services\EffectiveScopeResolver;
use App\Domain\Collaboration\Services\CollaborationReportGateway;
use App\Domain\Crm\Services\CrmReportGateway;
use App\Domain\Deal\Services\DealReportGateway;
use App\Domain\Document\Services\DocumentReportGateway;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final readonly class DashboardQuery
{
    public function __construct(
        private EffectiveScopeResolver $scopeResolver,
        private CrmReportGateway $crm,
        private DealReportGateway $deals,
        private DocumentReportGateway $documents,
        private CollaborationReportGateway $collaboration,
    ) {}

    /** @return list<array{key: string, label: string, count: int, state: string}> */
    public function cards(User $user): array
    {
        if ($this->scopeResolver->resolve($user) === DataScope::None) {
            return [];
        }

        $cards = [];

        if ($user->can('lead.manage')) {
            $leads = $this->crm->visibleLeads($user);
            $cards[] = $this->card('today_calls', (clone $leads)->whereBetween('next_call_at', [now()->startOfDay(), now()->endOfDay()])->count(), 'info');
            $cards[] = $this->card('overdue_followups', (clone $leads)->where('next_call_at', '<', now()->startOfDay())->count(), 'danger');
        }

        if ($user->can('deal.view')) {
            $deals = $this->deals->visibleDeals($user);

            if ($user->can('deal.transition')) {
                $cards[] = $this->card(
                    'new_assignments',
                    (clone $deals)->where('pm_user_id', $user->id)->where('created_at', '>=', now()->subDays((int) config('reporting.new_assignment_days')))->count(),
                    'info',
                );
            }

            if ($user->can('document.view')) {
                $documents = $this->documents->visibleDocuments($user);
                $cards[] = $this->card(
                    'missing_documents',
                    (clone $documents)->where('required_snapshot', true)->whereNotIn('status', ['accepted', 'not_required'])->distinct()->count('deal_id'),
                    'waiting',
                );
            }

            $cards[] = $this->card(
                'upcoming_deadlines',
                (clone $deals)->whereHas('status', static fn (Builder $query): Builder => $query->where('is_terminal', false))
                    ->whereHas('programVersion', static fn (Builder $query): Builder => $query->whereBetween('application_closes_at', [now(), now()->addDays((int) config('reporting.upcoming_deadline_days'))]))
                    ->count(),
                'danger',
            );
            $cards[] = $this->card(
                'customer_response',
                (clone $deals)->whereHas('status', static fn (Builder $query): Builder => $query->where('awaits_customer_response', true))->count(),
                'waiting',
            );

            if ($user->can('deal.assign')) {
                $cards[] = $this->card('unassigned_deals', (clone $deals)->whereNull('pm_user_id')->count(), 'danger');
            }
        }

        return $cards;
    }

    /** @return Collection<int, array{actor: string, sentence: string, occurred_at: non-falsy-string}> */
    public function recentActivities(User $user): Collection
    {
        return $this->collaboration->recentActivities($user, (int) config('reporting.recent_activities'));
    }

    /** @return array{key: string, label: string, count: int, state: string} */
    private function card(string $key, int $count, string $state): array
    {
        return [
            'key' => $key,
            'label' => __("reporting.dashboard.cards.{$key}"),
            'count' => $count,
            'state' => $state,
        ];
    }
}
