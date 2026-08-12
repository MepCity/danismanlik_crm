<?php

declare(strict_types=1);

namespace App\Support\Demo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class DemoDataPurger
{
    /** @return array{companies: int, deals: int, users: int, files: int} */
    public function purge(): array
    {
        $companyIds = $this->ids('companies', 'source', 'demo');
        $userIds = DB::table('users')->where('email', 'like', '%@demo.invalid')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($companyIds === [] && $userIds === []) {
            return ['companies' => 0, 'deals' => 0, 'users' => 0, 'files' => 0];
        }

        $dealIds = $this->relatedIds('deals', 'company_id', $companyIds);
        $leadIds = $this->relatedIds('leads', 'company_id', $companyIds);
        $documentIds = $this->relatedIds('deal_documents', 'deal_id', $dealIds);
        $fileRows = DB::table('files')->whereIn('deal_document_id', $documentIds)->get(['id', 'storage_key']);

        $this->assertDemoUsersAreIsolated($userIds, $companyIds);
        $this->deleteStoredFiles($fileRows->pluck('storage_key')->map(fn (mixed $key): string => (string) $key)->all());

        DB::transaction(function () use ($companyIds, $userIds, $dealIds, $leadIds, $documentIds, $fileRows): void {
            DB::statement('SET LOCAL session_replication_role = replica');

            $this->deleteAuditRows($companyIds, $userIds, $dealIds, $leadIds, $documentIds, $fileRows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
            DB::table('notifications')->whereIn('deal_document_id', $documentIds)->orWhereIn('deal_id', $dealIds)->orWhereIn('lead_id', $leadIds)->orWhereIn('user_id', $userIds)->delete();
            DB::table('outbox')->whereIn('actor_id', $userIds)->delete();
            DB::table('report_exports')->whereIn('actor_id', $userIds)->delete();
            DB::table('comments')->whereIn('deal_document_id', $documentIds)->orWhereIn('deal_id', $dealIds)->orWhereIn('lead_id', $leadIds)->orWhereIn('user_id', $userIds)->delete();
            DB::table('tasks')->whereIn('deal_document_id', $documentIds)->orWhereIn('deal_id', $dealIds)->orWhereIn('lead_id', $leadIds)->orWhereIn('assigned_to', $userIds)->orWhereIn('created_by', $userIds)->delete();
            DB::table('activities')->whereIn('deal_document_id', $documentIds)->orWhereIn('deal_id', $dealIds)->orWhereIn('lead_id', $leadIds)->orWhereIn('actor_id', $userIds)->delete();
            DB::table('status_history')->whereIn('deal_id', $dealIds)->orWhereIn('lead_id', $leadIds)->delete();
            DB::table('document_requirement_suggestions')->whereIn('deal_document_id', $documentIds)->delete();
            DB::table('files')->whereIn('deal_document_id', $documentIds)->delete();
            DB::table('deal_documents')->whereIn('deal_id', $dealIds)->delete();
            DB::table('interactions')->whereIn('deal_id', $dealIds)->orWhereIn('lead_id', $leadIds)->orWhereIn('user_id', $userIds)->delete();
            DB::table('communication_consents')->whereIn('contact_id', $this->relatedIds('contacts', 'company_id', $companyIds))->delete();
            DB::table('leads')->whereIn('id', $leadIds)->delete();
            DB::table('deals')->whereIn('id', $dealIds)->delete();
            DB::table('contacts')->whereIn('company_id', $companyIds)->delete();
            DB::table('companies')->whereIn('id', $companyIds)->delete();
            DB::table('team_members')->whereIn('user_id', $userIds)->orWhereIn('team_id', DB::table('teams')->where('name', 'like', 'Demo %')->select('id'))->delete();
            DB::table('teams')->where('name', 'like', 'Demo %')->delete();
            DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->whereIn('model_id', $userIds)->delete();
            DB::table('model_has_permissions')->where('model_type', 'App\\Models\\User')->whereIn('model_id', $userIds)->delete();
            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            DB::table('users')->whereIn('id', $userIds)->delete();
        });

        return [
            'companies' => count($companyIds),
            'deals' => count($dealIds),
            'users' => count($userIds),
            'files' => $fileRows->count(),
        ];
    }

    /** @param list<int> $userIds
     * @param  list<int>  $companyIds
     */
    private function assertDemoUsersAreIsolated(array $userIds, array $companyIds): void
    {
        if ($userIds === []) {
            return;
        }

        $leaks = DB::table('leads')->whereIn('owner_user_id', $userIds)->whereNotIn('company_id', $companyIds)->exists()
            || DB::table('deals')->whereNotIn('company_id', $companyIds)->where(function ($query) use ($userIds): void {
                $query->whereIn('opened_by_user_id', $userIds)->orWhereIn('pm_user_id', $userIds);
            })->exists();

        if ($leaks) {
            throw new RuntimeException((string) trans('demo_cleanup.shared_user'));
        }
    }

    /** @param list<string> $keys */
    private function deleteStoredFiles(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        try {
            if (! Storage::disk((string) config('documents.disk'))->delete($keys)) {
                throw new RuntimeException((string) trans('demo_cleanup.storage_failed'));
            }
        } catch (Throwable $exception) {
            throw new RuntimeException((string) trans('demo_cleanup.storage_failed'), previous: $exception);
        }
    }

    /** @param list<int> $companyIds
     * @param  list<int>  $userIds
     * @param  list<int>  $dealIds
     * @param  list<int>  $leadIds
     * @param  list<int>  $documentIds
     * @param  list<int>  $fileIds
     */
    private function deleteAuditRows(array $companyIds, array $userIds, array $dealIds, array $leadIds, array $documentIds, array $fileIds): void
    {
        DB::table('audit_log')->whereIn('actor_id', $userIds)->orWhere(function ($query) use ($companyIds, $dealIds, $leadIds, $documentIds, $fileIds): void {
            foreach (['companies' => $companyIds, 'leads' => $leadIds, 'deals' => $dealIds, 'deal_documents' => $documentIds, 'files' => $fileIds] as $table => $ids) {
                $query->orWhere(fn ($row) => $row->where('table_name', $table)->whereIn('row_id', $ids));
            }
        })->delete();
    }

    /** @return list<int> */
    private function ids(string $table, string $column, string $value): array
    {
        return DB::table($table)->where($column, $value)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /** @param list<int> $ids
     * @return list<int>
     */
    private function relatedIds(string $table, string $column, array $ids): array
    {
        return DB::table($table)->whereIn($column, $ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
