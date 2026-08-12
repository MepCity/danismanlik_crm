<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class DailyDigestService
{
    public function __construct(private EmailNotificationService $emails) {}

    public function run(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $sent = 0;

        User::query()->where('is_active', true)->orderBy('id')->each(function (User $user) use ($now, &$sent): void {
            $tasks = DB::table('tasks')
                ->leftJoin('deals', 'deals.id', '=', 'tasks.deal_id')
                ->leftJoin('deal_documents', 'deal_documents.id', '=', 'tasks.deal_document_id')
                ->leftJoin('deals as document_deals', 'document_deals.id', '=', 'deal_documents.deal_id')
                ->leftJoin('leads', 'leads.id', '=', 'tasks.lead_id')
                ->leftJoin('companies', function ($join): void {
                    $join->on('companies.id', '=', DB::raw('coalesce(tasks.company_id, deals.company_id, document_deals.company_id, leads.company_id)'));
                })
                ->where('tasks.assigned_to', $user->id)
                ->whereNull('tasks.completed_at')
                ->orderBy('tasks.due_at')
                ->get([
                    'companies.legal_name',
                    DB::raw('coalesce(deals.reference_no, document_deals.reference_no) as reference_no'),
                    'tasks.due_at',
                ]);
            $deadlines = DB::table('deal_documents')
                ->join('deals', 'deals.id', '=', 'deal_documents.deal_id')
                ->join('companies', 'companies.id', '=', 'deals.company_id')
                ->where('deals.pm_user_id', $user->id)
                ->whereNull('deal_documents.received_at')
                ->whereBetween('deal_documents.due_at', [$now, $now->copy()->addDays(7)])
                ->orderBy('deal_documents.due_at')
                ->get(['companies.legal_name', 'deals.reference_no', 'deal_documents.due_at']);
            $pending = $user->can('deal.assign')
                ? DB::table('deals')->join('companies', 'companies.id', '=', 'deals.company_id')
                    ->whereNull('deals.pm_user_id')->orderBy('deals.id')
                    ->get(['companies.legal_name', 'deals.reference_no'])
                : collect();

            if ($tasks->isEmpty() && $deadlines->isEmpty() && $pending->isEmpty()) {
                return;
            }

            $lines = [trans('collaboration.digest.intro')];
            foreach ($tasks as $task) {
                $lines[] = trans('collaboration.digest.task_line', [
                    'company' => $task->legal_name ?? trans('collaboration.digest.unknown_company'),
                    'reference' => $task->reference_no ?? trans('collaboration.digest.lead_reference'),
                    'due_at' => Carbon::parse($task->due_at)->format('d.m.Y'),
                ]);
            }
            foreach ($deadlines as $document) {
                $lines[] = trans('collaboration.digest.deadline_line', [
                    'company' => $document->legal_name,
                    'reference' => $document->reference_no,
                    'due_at' => Carbon::parse($document->due_at)->format('d.m.Y'),
                ]);
            }
            foreach ($pending as $deal) {
                $lines[] = trans('collaboration.digest.assignment_line', [
                    'company' => $deal->legal_name,
                    'reference' => $deal->reference_no,
                ]);
            }

            $this->emails->queue($user, 'daily_digest', trans('collaboration.digest.subject'), implode("\n", $lines));
            $sent++;
        });

        return $sent;
    }
}
