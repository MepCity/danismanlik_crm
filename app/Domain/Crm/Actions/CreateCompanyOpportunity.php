<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Services\LeadInitializationGateway;
use App\Domain\Program\Services\ActiveProgramVersionReader;
use App\Models\User;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class CreateCompanyOpportunity
{
    public function __construct(
        private LeadInitializationGateway $workflow,
        private ActiveProgramVersionReader $programVersions,
        private ActivityRecorder $activities,
        private CollaborationTransaction $transactions,
    ) {}

    public function execute(
        int $companyId,
        int $programVersionId,
        User $actor,
        ?int $contactId = null,
        ?string $nextCallAt = null,
    ): Lead {
        Gate::forUser($actor)->authorize('create', Lead::class);
        $company = Company::query()->findOrFail($companyId);
        Gate::forUser($actor)->authorize('view', $company);

        if (! $company->is_active) {
            throw ValidationException::withMessages(['company_id' => trans('marketing.company_opportunity.validation.company')]);
        }
        if ($this->programVersions->find($programVersionId) === null) {
            throw ValidationException::withMessages(['program_version_id' => trans('marketing.company_opportunity.validation.program')]);
        }

        $contact = $contactId === null
            ? null
            : Contact::query()->where('company_id', $company->id)->where('is_active', true)->find($contactId);
        if ($contactId !== null && ! $contact instanceof Contact) {
            throw ValidationException::withMessages(['contact_id' => trans('marketing.company_opportunity.validation.contact')]);
        }
        if (filled($nextCallAt) && Carbon::parse((string) $nextCallAt)->isPast()) {
            throw ValidationException::withMessages(['next_call_at' => trans('marketing.company_opportunity.validation.next_call')]);
        }

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($company, $programVersionId, $actor, $contact, $nextCallAt): Lead {
            $initial = $this->workflow->initialState();
            $lead = Lead::query()->create([
                'company_id' => $company->id,
                'primary_contact_id' => $contact?->id,
                'owner_user_id' => $actor->id,
                'source' => 'manual',
                'interested_program_version_id' => $programVersionId,
                'status_id' => $initial->statusId,
                'next_call_at' => filled($nextCallAt) ? $nextCallAt : null,
            ]);
            $this->workflow->recordInitialHistory($lead->id, $initial, $actor->id);
            $this->activities->record(
                action: 'lead.created',
                payload: [
                    'company' => ['id' => $company->id, 'name' => $company->legal_name],
                    'contact' => $contact === null ? null : ['id' => $contact->id, 'name' => $contact->full_name],
                    'program_version_id' => $programVersionId,
                    'initial_status' => ['id' => $initial->statusId, 'label' => $initial->statusLabel],
                ],
                actorId: $actor->id,
                leadId: $lead->id,
                defaultSource: 'user',
            );

            return $lead->refresh();
        });
    }
}
