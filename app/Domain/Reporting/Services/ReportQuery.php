<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Services;

use App\Domain\Access\Enums\DataScope;
use App\Domain\Access\Services\EffectiveScopeResolver;
use App\Domain\Crm\Services\CrmReportGateway;
use App\Domain\Deal\Services\DealReportGateway;
use App\Domain\Document\Services\DocumentReportGateway;
use App\Domain\Reporting\DTOs\ReportColumn;
use App\Domain\Reporting\DTOs\ReportTable;
use App\Domain\Reporting\Enums\ReportType;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use stdClass;

final readonly class ReportQuery
{
    public function __construct(
        private EffectiveScopeResolver $scopeResolver,
        private CrmReportGateway $crm,
        private DealReportGateway $deals,
        private DocumentReportGateway $documents,
    ) {}

    public function table(ReportType $type, User $user): ReportTable
    {
        $query = $this->query($type, $user);
        $total = DB::query()->fromSub(clone $query, 'report_rows')->count();
        $rows = $query->limit((int) config('reporting.page_rows'))->get();

        return new ReportTable($type, $this->columns($type), $rows, $total);
    }

    /** @return LazyCollection<int, stdClass> */
    public function cursor(ReportType $type, User $user): LazyCollection
    {
        return $this->query($type, $user)->cursor();
    }

    /** @return list<ReportColumn> */
    public function columns(ReportType $type): array
    {
        $keys = match ($type) {
            ReportType::DealBoard => [
                ['reference_no', false], ['company_name', false], ['program_name', false],
                ['status_label', false], ['project_manager', false], ['status_days', true],
                ['document_collection_days', true],
            ],
            ReportType::PendingAssignments => [
                ['reference_no', false], ['company_name', false], ['program_name', false],
                ['status_label', false], ['waiting_days', true],
            ],
            ReportType::ProjectManagerWorkload => [
                ['project_manager', false], ['active_deals', true], ['missing_documents', true],
                ['average_collection_days', true], ['availability', false],
            ],
            ReportType::MissingDocuments => [
                ['reference_no', false], ['company_name', false], ['project_manager', false],
                ['document_name', false], ['document_status', false], ['due_at', false],
            ],
            ReportType::UpcomingDeadlines => [
                ['reference_no', false], ['company_name', false], ['program_name', false],
                ['call_period', false], ['application_closes_at', false], ['remaining_days', true],
            ],
            ReportType::ConversionFunnel => [
                ['program_name', false], ['lead_count', true], ['call_count', true],
                ['conversation_count', true], ['converted_count', true], ['approved_count', true],
                ['call_to_conversation_rate', true], ['approval_rate', true],
            ],
        };

        return array_map(
            static fn (array $definition): ReportColumn => new ReportColumn(
                $definition[0],
                __("reporting.columns.{$definition[0]}"),
                $definition[1],
            ),
            $keys,
        );
    }

    private function query(ReportType $type, User $user): Builder
    {
        return match ($type) {
            ReportType::DealBoard => $this->dealBoard($user),
            ReportType::PendingAssignments => $this->pendingAssignments($user),
            ReportType::ProjectManagerWorkload => $this->projectManagerWorkload($user),
            ReportType::MissingDocuments => $this->missingDocuments($user),
            ReportType::UpcomingDeadlines => $this->upcomingDeadlines($user),
            ReportType::ConversionFunnel => $this->conversionFunnel($user),
        };
    }

    private function dealBoard(User $user): Builder
    {
        return DB::query()
            ->fromSub($this->deals->visibleDeals($user), 'visible_deals')
            ->join('companies', 'companies.id', '=', 'visible_deals.company_id')
            ->join('program_versions', 'program_versions.id', '=', 'visible_deals.program_version_id')
            ->join('programs', 'programs.id', '=', 'program_versions.program_id')
            ->join('statuses', 'statuses.id', '=', 'visible_deals.status_id')
            ->leftJoin('users as project_managers', 'project_managers.id', '=', 'visible_deals.pm_user_id')
            ->select([
                'visible_deals.id', 'visible_deals.reference_no', 'companies.legal_name as company_name',
                'programs.name as program_name', 'statuses.label as status_label',
                'project_managers.name as project_manager',
            ])
            ->selectRaw($this->statusDurationSql().' AS status_days')
            ->selectRaw($this->documentCollectionSql().' AS document_collection_days')
            ->orderBy('statuses.sort_order')
            ->orderBy('visible_deals.reference_no');
    }

    private function pendingAssignments(User $user): Builder
    {
        return DB::query()
            ->fromSub($this->deals->visibleDeals($user), 'visible_deals')
            ->join('companies', 'companies.id', '=', 'visible_deals.company_id')
            ->join('program_versions', 'program_versions.id', '=', 'visible_deals.program_version_id')
            ->join('programs', 'programs.id', '=', 'program_versions.program_id')
            ->join('statuses', 'statuses.id', '=', 'visible_deals.status_id')
            ->whereNull('visible_deals.pm_user_id')
            ->select([
                'visible_deals.id', 'visible_deals.reference_no', 'companies.legal_name as company_name',
                'programs.name as program_name', 'statuses.label as status_label',
            ])
            ->selectRaw($this->statusDurationSql().' AS waiting_days')
            ->orderByDesc('waiting_days')
            ->orderBy('visible_deals.reference_no');
    }

    private function projectManagerWorkload(User $user): Builder
    {
        $eligibleManagers = DB::table('users')
            ->where('users.is_active', true)
            ->whereExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
                    ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('roles.default_scope', DataScope::Team->value)
                    ->where('permissions.name', 'deal.transition');
            })
            ->select(['users.id', 'users.name']);

        $scope = $this->scopeResolver->resolve($user);
        if ($scope === DataScope::None) {
            $eligibleManagers->whereRaw('1 = 0');
        } elseif ($scope === DataScope::Own) {
            $eligibleManagers->where('users.id', $user->id);
        } elseif ($scope === DataScope::Team) {
            $teamIds = DB::table('team_members')->where('user_id', $user->id)->select('team_id');
            $eligibleManagers->where(function (Builder $query) use ($user, $teamIds): void {
                $query->where('users.id', $user->id)
                    ->orWhereIn('users.id', DB::table('team_members')->whereIn('team_id', $teamIds)->select('user_id'));
            });
        }

        $activeDeals = $this->deals->visibleDeals($user)
            ->join('statuses as active_statuses', 'active_statuses.id', '=', 'deals.status_id')
            ->where('active_statuses.is_terminal', false)
            ->select('deals.*');

        return DB::query()
            ->fromSub($eligibleManagers, 'project_managers')
            ->leftJoinSub($activeDeals, 'active_deals', 'active_deals.pm_user_id', '=', 'project_managers.id')
            ->leftJoin('deal_documents', function ($join): void {
                $join->on('deal_documents.deal_id', '=', 'active_deals.id')
                    ->where('deal_documents.required_snapshot', true)
                    ->whereNotIn('deal_documents.status', ['accepted', 'not_required']);
            })
            ->select(['project_managers.id', 'project_managers.name as project_manager'])
            ->selectRaw('COUNT(DISTINCT active_deals.id)::bigint AS active_deals')
            ->selectRaw('COUNT(DISTINCT deal_documents.id)::bigint AS missing_documents')
            ->selectRaw('ROUND(AVG(EXTRACT(EPOCH FROM (active_deals.all_required_accepted_at - active_deals.document_requested_at)) / 86400.0) FILTER (WHERE active_deals.document_requested_at IS NOT NULL AND active_deals.all_required_accepted_at IS NOT NULL), 2) AS average_collection_days')
            ->selectRaw('CASE WHEN COUNT(DISTINCT active_deals.id) = 0 THEN ? ELSE ? END AS availability', [__('reporting.availability.idle'), __('reporting.availability.busy')])
            ->groupBy('project_managers.id', 'project_managers.name')
            ->orderByDesc('active_deals')
            ->orderBy('project_managers.name');
    }

    private function missingDocuments(User $user): Builder
    {
        return DB::query()
            ->fromSub($this->documents->visibleDocuments($user), 'visible_documents')
            ->join('deals', 'deals.id', '=', 'visible_documents.deal_id')
            ->join('companies', 'companies.id', '=', 'deals.company_id')
            ->leftJoin('users as project_managers', 'project_managers.id', '=', 'deals.pm_user_id')
            ->where('visible_documents.required_snapshot', true)
            ->whereNotIn('visible_documents.status', ['accepted', 'not_required'])
            ->select([
                'visible_documents.id', 'deals.reference_no', 'companies.legal_name as company_name',
                'project_managers.name as project_manager', 'visible_documents.name_snapshot as document_name',
                'visible_documents.status as document_status', 'visible_documents.due_at',
            ])
            ->orderByRaw('visible_documents.due_at ASC NULLS LAST')
            ->orderBy('deals.reference_no')
            ->orderBy('visible_documents.name_snapshot');
    }

    private function upcomingDeadlines(User $user): Builder
    {
        $deadlineDays = (int) config('reporting.upcoming_deadline_days');

        return DB::query()
            ->fromSub($this->deals->visibleDeals($user), 'visible_deals')
            ->join('companies', 'companies.id', '=', 'visible_deals.company_id')
            ->join('program_versions', 'program_versions.id', '=', 'visible_deals.program_version_id')
            ->join('programs', 'programs.id', '=', 'program_versions.program_id')
            ->join('statuses', 'statuses.id', '=', 'visible_deals.status_id')
            ->where('statuses.is_terminal', false)
            ->whereBetween('program_versions.application_closes_at', [now(), now()->addDays($deadlineDays)])
            ->select([
                'visible_deals.id', 'visible_deals.reference_no', 'companies.legal_name as company_name',
                'programs.name as program_name', 'program_versions.call_period',
                'program_versions.application_closes_at',
            ])
            ->selectRaw('GREATEST(0, CEIL(EXTRACT(EPOCH FROM (program_versions.application_closes_at - CURRENT_TIMESTAMP)) / 86400.0))::bigint AS remaining_days')
            ->orderBy('program_versions.application_closes_at')
            ->orderBy('visible_deals.reference_no');
    }

    private function conversionFunnel(User $user): Builder
    {
        $calls = DB::table('interactions')
            ->where('type', 'call')
            ->whereNotNull('lead_id')
            ->groupBy('lead_id')
            ->select('lead_id')
            ->selectRaw('COUNT(*)::bigint AS call_count')
            ->selectRaw("COUNT(*) FILTER (WHERE outcome IS NOT NULL AND outcome <> 'unreachable')::bigint AS conversation_count");

        return DB::query()
            ->fromSub($this->crm->visibleLeads($user), 'visible_leads')
            ->leftJoin('program_versions', 'program_versions.id', '=', 'visible_leads.interested_program_version_id')
            ->leftJoin('programs', 'programs.id', '=', 'program_versions.program_id')
            ->leftJoinSub($calls, 'call_results', 'call_results.lead_id', '=', 'visible_leads.id')
            ->leftJoin('deals as converted_deals', 'converted_deals.id', '=', 'visible_leads.converted_deal_id')
            ->selectRaw('COALESCE(programs.name, ?) AS program_name', [__('reporting.no_program')])
            ->selectRaw('COUNT(DISTINCT visible_leads.id)::bigint AS lead_count')
            ->selectRaw('COALESCE(SUM(call_results.call_count), 0)::bigint AS call_count')
            ->selectRaw('COALESCE(SUM(call_results.conversation_count), 0)::bigint AS conversation_count')
            ->selectRaw('COUNT(DISTINCT visible_leads.converted_deal_id)::bigint AS converted_count')
            ->selectRaw("COUNT(DISTINCT converted_deals.id) FILTER (WHERE converted_deals.result_outcome = 'approved')::bigint AS approved_count")
            ->selectRaw('COALESCE(ROUND(100.0 * SUM(call_results.conversation_count) / NULLIF(SUM(call_results.call_count), 0), 1), 0) AS call_to_conversation_rate')
            ->selectRaw("COALESCE(ROUND(100.0 * COUNT(DISTINCT converted_deals.id) FILTER (WHERE converted_deals.result_outcome = 'approved') / NULLIF(COUNT(DISTINCT visible_leads.converted_deal_id), 0), 1), 0) AS approval_rate")
            ->groupBy('programs.id', 'programs.name')
            ->orderBy('program_name');
    }

    private function statusDurationSql(): string
    {
        return <<<'SQL'
            COALESCE((
                SELECT ROUND(SUM(EXTRACT(EPOCH FROM (COALESCE(history.exited_at, CURRENT_TIMESTAMP) - history.entered_at))) / 86400.0, 2)
                FROM status_history AS history
                WHERE history.deal_id = visible_deals.id
                  AND history.status_id = visible_deals.status_id
            ), 0)
            SQL;
    }

    private function documentCollectionSql(): string
    {
        return 'ROUND(EXTRACT(EPOCH FROM (visible_deals.all_required_accepted_at - visible_deals.document_requested_at)) / 86400.0, 2)';
    }
}
