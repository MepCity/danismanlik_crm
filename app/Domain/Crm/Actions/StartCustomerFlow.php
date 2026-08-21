<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Collaboration\Services\NotificationWriter;
use App\Domain\Crm\Models\Company;
use App\Domain\Deal\Services\ChecklistDealGateway;
use App\Domain\Document\Services\ChecklistGeneratorContract;
use App\Domain\Program\Services\ActiveProgramVersionReader;
use App\Models\User;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class StartCustomerFlow
{
    public function __construct(
        private ChecklistDealGateway $deals,
        private ChecklistGeneratorContract $checklists,
        private ActivityRecorder $activities,
        private NotificationWriter $notifications,
        private CollaborationTransaction $transactions,
        private ActiveProgramVersionReader $programVersions,
    ) {}

    public function execute(int $companyId, int $programVersionId, User $actor): int
    {
        abort_unless($actor->can('deal.create'), 403);

        $company = Company::query()->findOrFail($companyId);
        Gate::forUser($actor)->authorize('view', $company);

        if (! $company->is_active) {
            throw ValidationException::withMessages(['company_id' => trans('panel.customers.validation.company')]);
        }

        $programVersion = $this->programVersions->find($programVersionId);

        if ($programVersion === null) {
            throw ValidationException::withMessages(['program_version_id' => trans('panel.customers.validation.program')]);
        }

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($company, $programVersion, $actor): int {
            $reference = 'BLF-'.Str::ulid();
            $deal = $this->deals->createAwaitingAssignment(
                $company->id,
                $programVersion->id,
                $programVersion->workflowSnapshot,
                $actor->id,
                $reference,
                trans('panel.customers.started'),
            );

            $this->checklists->generate($deal->id, $actor->id);
            $this->activities->record(
                action: 'company.customer_flow_started',
                payload: [
                    'deal_id' => $deal->id,
                    'deal_reference' => $deal->reference,
                    'program_version_id' => $programVersion->id,
                ],
                actorId: $actor->id,
                defaultSource: 'user',
                companyId: $company->id,
            );
            $this->activities->record(
                action: 'deal.created',
                payload: [
                    'company' => ['id' => $company->id, 'name' => $company->legal_name],
                    'program_version_id' => $programVersion->id,
                ],
                actorId: $actor->id,
                dealId: $deal->id,
                defaultSource: 'user',
            );

            User::permission('deal.assign')->where('is_active', true)->each(
                fn (User $user) => $this->notifications->assignmentPending($user->id, $deal->id, $deal->reference),
            );

            return $deal->id;
        });
    }
}
